<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\TransactionType;
use App\Models\User;
use App\Notifications\FundNotification;
use App\Support\Notifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Circuit de validation des transactions : chaque type de transaction exige un nombre de validations
 * (ex. 2 trésoriers pour un décaissement). Une même personne ne valide qu'une fois,
 * et le bénéficiaire d'une transaction ne peut jamais la valider.
 */
class ApprovalService
{
    /** Exécuteurs par code de type. Les types créés par l'administration produisent un mouvement du fonds. */
    private array $executors = [];

    public function registerExecutor(string $code, callable $executor): void
    {
        $this->executors[$code] = $executor;
    }

    public function type(string $code): TransactionType
    {
        $type = TransactionType::where('code', $code)->first();
        if (! $type) {
            throw new RuntimeException("Type de transaction « {$code} » introuvable.");
        }
        if (! $type->is_active) {
            throw new RuntimeException("Le type de transaction « {$type->name} » est désactivé.");
        }

        return $type;
    }

    public function requiredApprovals(string $code): int
    {
        return (int) (TransactionType::where('code', $code)->value('required_approvals') ?? 1);
    }

    public function canApprove(User $user, ApprovalRequest $request): bool
    {
        return $request->status === 'pending'
            && $user->hasAnyRole($request->approver_roles ?: ['treasurer', 'president', 'super_admin'])
            && $user->id !== $request->beneficiary_id
            && ! $request->decisions()->where('user_id', $user->id)->exists();
    }

    /**
     * Crée une demande. La personne qui la crée compte comme première validation si elle en a le droit.
     * Si le nombre de validations est atteint, la transaction est exécutée immédiatement.
     */
    public function request(string $code, string $title, User $requester, ?float $amount = null, array $payload = [], ?Model $subject = null, ?User $beneficiary = null): ApprovalRequest
    {
        $type = $this->type($code);

        $request = DB::transaction(function () use ($type, $title, $requester, $amount, $payload, $subject, $beneficiary) {
            $request = ApprovalRequest::create([
                'transaction_type_id' => $type->id,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'title' => $title,
                'amount' => $amount,
                'currency' => \App\Support\FundCurrency::code(),
                'payload' => $payload,
                'status' => 'pending',
                'required_approvals' => max(1, $type->required_approvals),
                'approver_roles' => $type->approver_roles ?: ['treasurer', 'president', 'super_admin'],
                'requested_by' => $requester->id,
                'beneficiary_id' => $beneficiary?->id,
            ]);
            activity('approvals')->performedOn($request)->causedBy($requester)->event('approval.requested')
                ->withProperties(['type' => $type->code, 'amount' => $amount, 'required' => $request->required_approvals])
                ->log('Transaction soumise à validation : '.$title);

            return $request;
        });

        if ($this->canApprove($requester, $request)) {
            return $this->approve($request, $requester, null, notifyOthers: true);
        }
        $this->notifyApprovers($request, $requester);

        return $request->fresh();
    }

    public function approve(ApprovalRequest $request, User $user, ?string $comment = null, bool $notifyOthers = false): ApprovalRequest
    {
        $request = DB::transaction(function () use ($request, $user, $comment) {
            $request = ApprovalRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'pending') {
                throw new RuntimeException('Cette transaction n’est plus en attente de validation.');
            }
            if ($user->id === $request->beneficiary_id) {
                throw new RuntimeException('Vous ne pouvez pas valider une transaction dont vous êtes le bénéficiaire.');
            }
            if (! $user->hasAnyRole($request->approver_roles ?: [])) {
                throw new RuntimeException('Votre rôle ne permet pas de valider ce type de transaction.');
            }
            if ($request->decisions()->where('user_id', $user->id)->exists()) {
                throw new RuntimeException('Vous avez déjà validé cette transaction : une autre personne doit le faire.');
            }
            $request->decisions()->create(['user_id' => $user->id, 'decision' => 'approve', 'comment' => $comment]);
            activity('approvals')->performedOn($request)->causedBy($user)->event('approval.approved')
                ->withProperties(['approvals' => $request->approvalsCount(), 'required' => $request->required_approvals, 'comment' => $comment])
                ->log('Transaction validée ('.$request->approvalsCount().'/'.$request->required_approvals.')');

            return $request;
        });

        if ($request->approvalsCount() >= $request->required_approvals) {
            return $this->execute($request, $user);
        }
        if ($notifyOthers) {
            $this->notifyApprovers($request, $user);
        }

        return $request->fresh();
    }

    public function reject(ApprovalRequest $request, User $user, string $reason): ApprovalRequest
    {
        if ($request->status !== 'pending') {
            throw new RuntimeException('Cette transaction n’est plus en attente de validation.');
        }
        if (! $user->hasAnyRole($request->approver_roles ?: []) && $user->id !== $request->requested_by) {
            throw new RuntimeException('Vous ne pouvez pas refuser cette transaction.');
        }
        DB::transaction(function () use ($request, $user, $reason) {
            $request->decisions()->updateOrCreate(['user_id' => $user->id], ['decision' => 'reject', 'comment' => $reason]);
            $request->update(['status' => $user->id === $request->requested_by ? 'cancelled' : 'rejected', 'rejected_by' => $user->id, 'rejected_reason' => $reason]);
            activity('approvals')->performedOn($request)->causedBy($user)->event('approval.rejected')
                ->withProperties(['reason' => $reason])->log('Transaction refusée : '.$request->title);
        });
        if ($request->requester && $request->requester->id !== $user->id) {
            Notifier::send([$request->requester], new FundNotification(
                ['Transaction refusée : :title', ['title' => $request->title]],
                [['Refusée par :name.', ['name' => $user->name]], ['Motif : :reason', ['reason' => $reason]]],
                route('approvals.index'), 'Voir les validations', 'approval',
            ));
        }

        return $request->fresh();
    }

    private function execute(ApprovalRequest $request, User $user): ApprovalRequest
    {
        $code = $request->type->code;
        $executor = $this->executors[$code] ?? [app(GenericTransactionExecutor::class), 'execute'];

        try {
            DB::transaction(function () use ($request, $executor, $user) {
                $result = $executor($request, $user);
                $request->update(['status' => 'executed', 'executed_at' => now(), 'result' => is_array($result) ? $result : null, 'failure_reason' => null]);
            });
        } catch (Throwable $e) {
            $request->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            activity('approvals')->performedOn($request)->causedBy($user)->event('approval.failed')
                ->withProperties(['error' => $e->getMessage()])->log('Échec de l’exécution : '.$request->title);
            throw new RuntimeException('Validation enregistrée, mais l’exécution a échoué : '.$e->getMessage(), previous: $e);
        }

        activity('approvals')->performedOn($request)->causedBy($user)->event('approval.executed')
            ->log('Transaction exécutée : '.$request->title);

        return $request->fresh();
    }

    /** Relance une transaction dont l'exécution a échoué (ex. solde insuffisant), une fois le problème réglé. */
    public function retry(ApprovalRequest $request, User $user): ApprovalRequest
    {
        if ($request->status !== 'failed' || ! $user->hasAnyRole($request->approver_roles ?: [])) {
            throw new RuntimeException('Cette transaction ne peut pas être relancée.');
        }
        $request->update(['status' => 'pending']);

        return $this->execute($request->fresh(), $user);
    }

    private function notifyApprovers(ApprovalRequest $request, User $actor): void
    {
        $approvers = \App\Models\User::role($request->approver_roles ?: ['treasurer'])->get()
            ->reject(fn (User $u) => $u->id === $actor->id || $u->id === $request->beneficiary_id
                || $request->decisions()->where('user_id', $u->id)->exists());
        Notifier::send($approvers, new FundNotification(
            ['Validation demandée : :title', ['title' => $request->title]],
            [
                ['Une transaction attend votre validation (:done/:required).', ['done' => $request->approvalsCount(), 'required' => $request->required_approvals]],
                ['Demandée par :name.', ['name' => $actor->name]],
            ],
            route('approvals.index'), 'Valider la transaction', 'approval',
        ));
    }
}
