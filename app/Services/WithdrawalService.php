<?php

namespace App\Services;

use App\Enums\FundMovementType;
use App\Enums\MemberStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\WithdrawalInstallment;
use App\Models\WithdrawalRequest;
use App\Notifications\FundNotification;
use App\Support\FundRules;
use App\Support\Notifier;
use App\Support\ProjectFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Retrait d'un membre (section 8) : préavis de 2 mois, remboursement de 95 % des cotisations,
 * calendrier communiqué sous 2 semaines, versements validés comme toute sortie d'argent.
 */
class WithdrawalService
{
    public function __construct(private EligibilityService $eligibility, private ApprovalService $approvals, private FundService $funds) {}

    public function request(User $user, string $reason): WithdrawalRequest
    {
        if (WithdrawalRequest::where('user_id', $user->id)->whereIn('status', ['requested', 'scheduled'])->exists()) {
            throw new RuntimeException('Une demande de retrait est déjà en cours.');
        }
        $total = $this->eligibility->totalContributions($user);
        $percent = FundRules::get('withdrawal_refund_percent');
        $withdrawal = WithdrawalRequest::create([
            'user_id' => $user->id, 'reason' => $reason, 'requested_on' => now()->toDateString(),
            'effective_date' => now()->addMonthsNoOverflow(FundRules::get('withdrawal_notice_months'))->toDateString(),
            'total_contributions' => $total, 'refund_percent' => $percent,
            'refund_amount' => round($total * $percent / 100, 2),
            'funds_engaged_in_loans' => $this->eligibility->ongoingLoans($user)->isNotEmpty(),
            'status' => 'requested',
        ]);
        activity('members')->performedOn($withdrawal)->causedBy($user)->event('withdrawal.requested')
            ->withProperties(['refund_amount' => (float) $withdrawal->refund_amount])->log('Demande de retrait du fonds');

        Notifier::send([...Notifier::treasurers(), ...\App\Models\User::role(['president', 'super_admin'])->get()], new FundNotification(
            ['Demande de retrait : :name', ['name' => $user->name]],
            [
                ['Départ prévu le :date (préavis de :months mois).', ['date' => $withdrawal->effective_date->format('d/m/Y'), 'months' => FundRules::get('withdrawal_notice_months')]],
                ['Montant à rembourser : :amount (:percent % de :total).', ['amount' => ProjectFormatter::money($withdrawal->refund_amount), 'percent' => $percent, 'total' => ProjectFormatter::money($total)]],
                ['Le calendrier de remboursement doit être communiqué sous deux semaines.'],
            ],
            route('withdrawals.index'), 'Préparer le calendrier', 'withdrawal',
        ));

        return $withdrawal;
    }

    /** @param array<int, array{due_date:string, amount:float|string}> $installments */
    public function schedule(WithdrawalRequest $withdrawal, User $user, array $installments, bool $engaged, ?string $notes): WithdrawalRequest
    {
        if (! in_array($withdrawal->status, ['requested', 'scheduled'], true) || $withdrawal->installments()->whereNotNull('paid_at')->exists()) {
            throw new RuntimeException('Le calendrier ne peut plus être modifié.');
        }
        if ($withdrawal->user_id === $user->id) {
            throw new RuntimeException('Vous ne pouvez pas préparer votre propre calendrier de remboursement.');
        }
        $total = round(collect($installments)->sum(fn ($row) => (float) $row['amount']), 2);
        if (abs($total - (float) $withdrawal->refund_amount) > 0.01) {
            throw new RuntimeException('La somme des versements ('.ProjectFormatter::money($total).') doit être égale au montant à rembourser ('.ProjectFormatter::money($withdrawal->refund_amount).').');
        }
        $limitMonths = $engaged ? FundRules::get('withdrawal_max_refund_months') : 6;
        $limit = CarbonImmutable::parse($withdrawal->effective_date)->addMonthsNoOverflow($limitMonths);
        foreach ($installments as $row) {
            if (CarbonImmutable::parse($row['due_date'])->gt($limit)) {
                throw new RuntimeException('Chaque versement doit avoir lieu au plus tard le '.$limit->format('d/m/Y').' ('.$limitMonths.' mois après le départ).');
            }
        }

        DB::transaction(function () use ($withdrawal, $user, $installments, $engaged, $notes) {
            $withdrawal->installments()->delete();
            foreach ($installments as $row) {
                $withdrawal->installments()->create(['due_date' => $row['due_date'], 'amount' => round((float) $row['amount'], 2)]);
            }
            $withdrawal->update(['status' => 'scheduled', 'scheduled_at' => now(), 'scheduled_by' => $user->id, 'funds_engaged_in_loans' => $engaged, 'notes' => $notes]);
            activity('members')->performedOn($withdrawal)->causedBy($user)->event('withdrawal.scheduled')
                ->withProperties(['installments' => $installments])->log('Calendrier de remboursement du départ communiqué');
        });

        $list = collect($installments)->map(fn ($row) => CarbonImmutable::parse($row['due_date'])->format('d/m/Y').' : '.ProjectFormatter::money($row['amount']))->implode(' · ');
        Notifier::send([$withdrawal->user], new FundNotification(
            ['Votre calendrier de remboursement'],
            [['Montant total : :amount.', ['amount' => ProjectFormatter::money($withdrawal->refund_amount)]], ['Versements prévus : :list', ['list' => $list]]],
            route('withdrawals.index'), 'Voir ma demande', 'withdrawal',
        ));

        return $withdrawal->fresh('installments');
    }

    public function requestPayment(WithdrawalInstallment $installment, User $user, int $fundAccountId): ApprovalRequest
    {
        $withdrawal = $installment->withdrawal;
        if ($installment->paid_at || $withdrawal->status !== 'scheduled') {
            throw new RuntimeException('Ce versement n’est pas à payer.');
        }
        if ($installment->approval_request_id && ApprovalRequest::where('id', $installment->approval_request_id)->where('status', 'pending')->exists()) {
            throw new RuntimeException('Ce versement attend déjà une validation.');
        }
        $request = $this->approvals->request('withdrawal_refund', 'Remboursement de départ — '.$withdrawal->user->name, $user,
            (float) $installment->amount, ['fund_account_id' => $fundAccountId, 'installment_id' => $installment->id], $installment, $withdrawal->user);
        $installment->update(['approval_request_id' => $request->id]);

        return $request;
    }

    public function executePayment(ApprovalRequest $request, User $user): array
    {
        $installment = WithdrawalInstallment::with('withdrawal.user')->findOrFail($request->payload['installment_id']);
        $movement = $this->funds->createMovement([
            'fund_account_id' => $request->payload['fund_account_id'], 'type' => FundMovementType::WithdrawalRefund,
            'amount' => (string) $request->amount, 'currency' => \App\Support\FundCurrency::code(), 'direction' => 'debit',
            'label' => 'Remboursement de départ — '.$installment->withdrawal->user->name,
            'reference' => 'VAL-'.$request->id, 'movement_date' => now()->toDateString(),
            'created_by' => $request->requested_by, 'validated_by' => $user->id,
        ]);
        $installment->update(['paid_at' => now(), 'fund_movement_id' => $movement->id]);

        $withdrawal = $installment->withdrawal->fresh('installments');
        if ($withdrawal->installments->every(fn ($i) => $i->paid_at)) {
            $withdrawal->update(['status' => 'completed']);
            $withdrawal->user->memberProfile?->update(['status' => MemberStatus::Inactive]);
            activity('members')->performedOn($withdrawal)->causedBy($user)->event('withdrawal.completed')->log('Départ remboursé intégralement');
        }

        return ['movement_id' => $movement->id];
    }
}
