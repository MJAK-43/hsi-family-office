<?php

namespace App\Services;

use App\Enums\ContributionPaymentStatus;
use App\Enums\FundMovementStatus;
use App\Enums\FundMovementType;
use App\Models\ContributionPayment;
use App\Models\FundAccount;
use App\Models\FundAllocationRule;
use App\Models\FundMovement;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ContributionStatusNotification;
use App\ValueObjects\Currency;
use App\ValueObjects\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class FundService
{
    public function __construct(private FinancialOperationService $operations) {}

    public function validateAndAllocateContributionPayment(
        ContributionPayment $payment,
        User $validator,
        ?string $idempotencyKey = null,
    ): ContributionPayment {
        return DB::transaction(function () use ($payment, $validator, $idempotencyKey): ContributionPayment {
            $payment = ContributionPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $idempotencyKey ??= "contribution-validation:{$payment->id}";
            $claim = $this->operations->claim('contribution_validation', $idempotencyKey, [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ], $validator);

            if ($claim->replayed) {
                return $payment;
            }

            if (! in_array($payment->status, [ContributionPaymentStatus::Pending, ContributionPaymentStatus::Late], true)) {
                throw new RuntimeException('Cette cotisation ne peut plus être validée.');
            }

            $payment->update([
                'status' => ContributionPaymentStatus::Validated,
                'validated_by' => $validator->id,
                'validated_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'updated_by' => $validator->id,
            ]);

            activity('contributions')
                ->performedOn($payment)
                ->causedBy($validator)
                ->event('contribution.payment.validated')
                ->withProperties([
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => ContributionPaymentStatus::Validated->value,
                    'member' => $payment->user?->name,
                ])
                ->log('Cotisation validée');

            $movements = $this->allocateContributionPayment(
                $payment->fresh(),
                $validator,
                $claim->operation->id,
                app(ContributionDueService::class)->penaltyPortion($payment->fresh()),
            );

            app(ContributionDueService::class)->markPaid($payment);
            $payment->user?->notify(new ContributionStatusNotification($payment, 'validated'));

            $this->operations->complete($claim->operation, [
                'payment_id' => $payment->id,
                'movement_ids' => $movements->pluck('id')->all(),
            ]);

            return $payment->fresh();
        });
    }

    public function allocateContributionPayment(
        ContributionPayment $payment,
        ?User $causer = null,
        ?int $financialOperationId = null,
        float $penaltyPortion = 0.0,
    ): Collection {
        return DB::transaction(function () use ($payment, $causer, $financialOperationId, $penaltyPortion) {
            $payment = ContributionPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== ContributionPaymentStatus::Validated) {
                throw new RuntimeException('Seules les cotisations validées peuvent être allouées au fonds familial.');
            }

            if (FundMovement::query()
                ->where('contribution_payment_id', $payment->id)
                ->where('type', FundMovementType::ContributionAllocation)
                ->exists()) {
                return collect();
            }

            $rule = FundAllocationRule::query()
                ->where('is_default', true)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $rule) {
                throw new RuntimeException('Aucune règle d’allocation par défaut active.');
            }

            $items = $rule->items()->with('account')->orderBy('fund_account_id')->get();

            if ($items->isEmpty()) {
                throw new RuntimeException('Aucune règle d’allocation par défaut active.');
            }

            $basisPoints = $this->percentageBasisPoints($items->pluck('percentage')->all());
            // La part qui règle des pénalités (section 4.3) est inscrite à part, sur la trésorerie ; le reste suit la règle d'allocation.
            $penaltyMoney = $penaltyPortion > 0 ? Money::fromDecimal(number_format($penaltyPortion, 2, '.', ''), $payment->currency) : null;
            $paymentMoney = Money::fromDecimal($payment->amount, $payment->currency);
            if ($penaltyMoney) $paymentMoney = $paymentMoney->subtract($penaltyMoney);
            $allocations = $paymentMoney->allocateByBasisPoints($basisPoints);
            $movements = collect();
            if ($penaltyMoney && ! $penaltyMoney->isZero()) {
                $penaltyAccount = FundAccount::where('slug', 'treasury')->where('is_active', true)->first() ?? $items->first()->account;
                $movements->push($this->createMovement([
                    'fund_account_id' => $penaltyAccount->id,
                    'contribution_payment_id' => $payment->id,
                    'financial_operation_id' => $financialOperationId,
                    'type' => FundMovementType::PenaltyIncome,
                    'amount' => $penaltyMoney->decimal(),
                    'currency' => $penaltyMoney->currency->code,
                    'direction' => 'credit',
                    'label' => 'Pénalité de retard de cotisation — '.($payment->user?->name ?? ''),
                    'description' => "Pénalités réglées avec la cotisation #{$payment->id}",
                    'reference' => $payment->reference,
                    'movement_date' => $payment->validated_at?->toDateString() ?? now()->toDateString(),
                    'created_by' => $causer?->id ?? $payment->validated_by,
                    'validated_by' => $causer?->id ?? $payment->validated_by,
                ], logActivity: false));
            }

            foreach ($items as $index => $item) {
                $allocatedAmount = $allocations[$index];

                if ($allocatedAmount->isZero()) {
                    continue;
                }

                if (! $item->account
                    || ! Currency::from($item->account->currency)->equals($paymentMoney->currency)) {
                    throw new RuntimeException('La devise de la cotisation doit correspondre à celle de chaque compte alloué.');
                }

                $movements->push($this->createMovement([
                    'fund_account_id' => $item->fund_account_id,
                    'contribution_payment_id' => $payment->id,
                    'financial_operation_id' => $financialOperationId,
                    'type' => FundMovementType::ContributionAllocation,
                    'amount' => $allocatedAmount->decimal(),
                    'currency' => $paymentMoney->currency->code,
                    'direction' => 'credit',
                    'label' => 'Allocation cotisation — '.$item->account->name,
                    'description' => "Allocation automatique ({$item->percentage}%) de la cotisation #{$payment->id}",
                    'reference' => $payment->reference,
                    'movement_date' => $payment->validated_at?->toDateString() ?? now()->toDateString(),
                    'created_by' => $causer?->id ?? $payment->validated_by,
                    'validated_by' => $causer?->id ?? $payment->validated_by,
                ], logActivity: false));
            }

            activity('funds')
                ->performedOn($payment)
                ->causedBy($causer)
                ->event('fund.allocation.created')
                ->withProperties([
                    'contribution_payment_id' => $payment->id,
                    'amount' => $paymentMoney->decimal(),
                    'currency' => $paymentMoney->currency->code,
                    'movements_count' => $movements->count(),
                ])
                ->log('Allocation de cotisation au fonds familial');

            return $movements;
        });
    }

    public function createMovement(array $data, bool $logActivity = true): FundMovement
    {
        return DB::transaction(function () use ($data, $logActivity) {
            $account = FundAccount::query()->lockForUpdate()->findOrFail($data['fund_account_id']);
            $amount = $this->money($data['amount'] ?? null, $data['currency'] ?? $account->currency);

            if ($amount->isNegative() || $amount->isZero()) {
                throw new InvalidArgumentException('Le montant du mouvement doit être strictement positif.');
            }

            if (! $amount->currency->equals(Currency::from($account->currency))) {
                throw new InvalidArgumentException('La devise du mouvement doit correspondre à celle du compte.');
            }

            if (($data['direction'] ?? '') === 'debit'
                && $this->recalculateAccountBalance($account)->compare($amount) < 0) {
                throw new RuntimeException('Solde insuffisant pour ce mouvement.');
            }

            $movement = FundMovement::create([
                ...$data,
                'amount' => $amount->decimal(),
                'currency' => $amount->currency->code,
                'status' => $data['status'] ?? FundMovementStatus::Validated,
                'validated_at' => $data['validated_at'] ?? now(),
                'movement_date' => $data['movement_date'] ?? now()->toDateString(),
            ]);

            $this->recalculateAccountBalance($account);

            if ($logActivity) {
                activity('funds')
                    ->performedOn($movement)
                    ->causedBy($movement->creator)
                    ->event('fund.movement.created')
                    ->withProperties([
                        'type' => $movement->type->value,
                        'amount' => $amount->decimal(),
                        'currency' => $amount->currency->code,
                        'direction' => $movement->direction,
                        'fund_account_id' => $movement->fund_account_id,
                    ])
                    ->log('Mouvement financier créé');
            }

            return $movement;
        });
    }

    public function createProjectFundingMovement(
        Project $project,
        User $user,
        ?int $financialOperationId = null,
        mixed $amountToFund = null,
    ): FundMovement {
        $amount = Money::fromDecimal($amountToFund ?? $project->requested_amount, $project->currency ?: \App\Support\FundCurrency::code());

        if ($amount->compare(Money::fromDecimal('1.00', $amount->currency)) < 0) {
            throw new InvalidArgumentException('Le montant demandé à financer est invalide.');
        }

        if (FundMovement::query()
            ->where('project_id', $project->id)
            ->where('type', FundMovementType::ProjectFunding)
            ->where('status', FundMovementStatus::Validated)
            ->exists()) {
            throw new RuntimeException('Un financement existe déjà pour ce projet.');
        }

        // Le projet est versé par le compte de fonds choisi au dépôt (à défaut : « Projets familiaux »).
        $account = FundAccount::query()
            ->when($project->fund_account_id, fn ($q) => $q->whereKey($project->fund_account_id), fn ($q) => $q->where('slug', 'family-projects'))
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if (! $account) {
            throw new RuntimeException('Le compte de fonds du projet est introuvable ou inactif.');
        }

        return $this->createMovement([
            'fund_account_id' => $account->id,
            'project_id' => $project->id,
            'financial_operation_id' => $financialOperationId,
            'type' => FundMovementType::ProjectFunding,
            'operation_leg_key' => 'project-funding:'.$project->id,
            'amount' => $amount->decimal(),
            'currency' => $amount->currency->code,
            'direction' => 'debit',
            'label' => 'Financement projet — '.$project->title,
            'description' => 'Débit du compte '.$account->name.' pour le projet #'.$project->id,
            'reference' => 'PRF-'.$project->id.'-'.now()->format('YmdHis'),
            'created_by' => $user->id,
            'validated_by' => $user->id,
        ]);
    }

    public function transfer(
        FundAccount $from,
        FundAccount $to,
        mixed $amount,
        string $description,
        User $user,
        ?string $idempotencyKey = null,
    ): array {
        if ($from->id === $to->id) {
            throw new InvalidArgumentException('Les comptes source et destination doivent être différents.');
        }

        return DB::transaction(function () use ($from, $to, $amount, $description, $user, $idempotencyKey) {
            $accounts = FundAccount::query()
                ->whereIn('id', [$from->id, $to->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $from = $accounts->get($from->id);
            $to = $accounts->get($to->id);

            if (! $from || ! $to) {
                throw new RuntimeException('Un compte de transfert est introuvable.');
            }

            if (! Currency::from($from->currency)->equals(Currency::from($to->currency))) {
                throw new InvalidArgumentException('Les comptes source et destination doivent utiliser la même devise.');
            }

            $transferAmount = $this->money($amount, $from->currency);
            $idempotencyKey ??= (string) Str::uuid();
            $claim = $this->operations->claim('fund_transfer', $idempotencyKey, [
                'from_account_id' => $from->id,
                'to_account_id' => $to->id,
                'amount' => $transferAmount->decimal(),
                'currency' => $transferAmount->currency->code,
                'description' => $description,
            ], $user);

            if ($claim->replayed) {
                $movementIds = $claim->operation->result['movement_ids'] ?? [];

                return FundMovement::query()
                    ->whereIn('id', $movementIds)
                    ->orderByRaw('CASE WHEN direction = ? THEN 0 ELSE 1 END', ['debit'])
                    ->get()
                    ->all();
            }

            if ($transferAmount->compare(Money::fromDecimal('1.00', $from->currency)) < 0) {
                throw new InvalidArgumentException('Le montant doit être d’au moins 1.');
            }

            if ($this->recalculateAccountBalance($from)->compare($transferAmount) < 0) {
                throw new RuntimeException('Solde insuffisant sur le compte source.');
            }

            $reference = 'TRF-'.Str::uuid();

            $outMovement = FundMovement::create([
                'fund_account_id' => $from->id,
                'related_fund_account_id' => $to->id,
                'financial_operation_id' => $claim->operation->id,
                'operation_leg_key' => $reference.':'.FundMovementType::TransferOut->value,
                'type' => FundMovementType::TransferOut,
                'status' => FundMovementStatus::Validated,
                'amount' => $transferAmount->decimal(),
                'currency' => $from->currency,
                'direction' => 'debit',
                'label' => 'Transfert vers '.$to->name,
                'description' => $description,
                'reference' => $reference,
                'movement_date' => now()->toDateString(),
                'created_by' => $user->id,
                'validated_by' => $user->id,
                'validated_at' => now(),
            ]);

            $inMovement = FundMovement::create([
                'fund_account_id' => $to->id,
                'related_fund_account_id' => $from->id,
                'financial_operation_id' => $claim->operation->id,
                'operation_leg_key' => $reference.':'.FundMovementType::TransferIn->value,
                'type' => FundMovementType::TransferIn,
                'status' => FundMovementStatus::Validated,
                'amount' => $transferAmount->decimal(),
                'currency' => $to->currency,
                'direction' => 'credit',
                'label' => 'Transfert depuis '.$from->name,
                'description' => $description,
                'reference' => $reference,
                'movement_date' => now()->toDateString(),
                'created_by' => $user->id,
                'validated_by' => $user->id,
                'validated_at' => now(),
            ]);

            $this->recalculateAccountBalance($from);
            $this->recalculateAccountBalance($to);

            activity('funds')
                ->performedOn($outMovement)
                ->causedBy($user)
                ->event('fund.transfer.created')
                ->withProperties([
                    'from_account_id' => $from->id,
                    'to_account_id' => $to->id,
                    'amount' => $transferAmount->decimal(),
                    'currency' => $transferAmount->currency->code,
                    'reference' => $reference,
                ])
                ->log('Transfert entre poches du fonds');

            $this->operations->complete($claim->operation, [
                'movement_ids' => [$outMovement->id, $inMovement->id],
                'reference' => $reference,
            ]);

            return [$outMovement, $inMovement];
        });
    }

    public function recalculateAccountBalance(FundAccount $account): Money
    {
        $balance = FundMovement::query()
            ->where('fund_account_id', $account->id)
            ->where('status', FundMovementStatus::Validated)
            ->get(['amount', 'currency', 'direction'])
            ->reduce(function (Money $balance, FundMovement $movement): Money {
                $amount = Money::fromDecimal($movement->amount, $movement->currency);

                return $movement->direction === 'credit'
                    ? $balance->add($amount)
                    : $balance->subtract($amount);
            }, Money::zero($account->currency));

        $account->update(['current_balance' => $balance->decimal()]);

        return $balance;
    }

    public function recalculateAllBalances(): void
    {
        FundAccount::query()->each(fn (FundAccount $account) => $this->recalculateAccountBalance($account));

        activity('funds')
            ->causedBy(auth()->user())
            ->event('fund.balance.recalculated')
            ->log('Recalcul des soldes du fonds familial');
    }

    public function cancelMovement(FundMovement $movement, string $reason, User $user): FundMovement
    {
        if ($movement->status === FundMovementStatus::Cancelled) {
            throw new RuntimeException('Ce mouvement est déjà annulé.');
        }

        if ($movement->type === FundMovementType::ProjectFunding) {
            throw new RuntimeException('Les mouvements de financement de projet ne peuvent pas être annulés depuis le module fonds.');
        }

        return DB::transaction(function () use ($movement, $reason, $user) {
            $this->cancelSingleMovement($movement, $reason, $user);

            if (in_array($movement->type, [FundMovementType::TransferIn, FundMovementType::TransferOut], true)
                && $movement->reference) {
                $pairedType = $movement->type === FundMovementType::TransferOut
                    ? FundMovementType::TransferIn
                    : FundMovementType::TransferOut;

                $paired = FundMovement::query()
                    ->where('reference', $movement->reference)
                    ->where('type', $pairedType)
                    ->where('status', FundMovementStatus::Validated)
                    ->where('id', '!=', $movement->id)
                    ->first();

                if ($paired) {
                    $this->cancelSingleMovement($paired, $reason, $user, logActivity: false);
                }
            }

            activity('funds')
                ->performedOn($movement)
                ->causedBy($user)
                ->event('fund.movement.cancelled')
                ->withProperties([
                    'reason' => $reason,
                    'amount' => $movement->amount,
                    'reference' => $movement->reference,
                ])
                ->log('Mouvement financier annulé');

            return $movement->fresh();
        });
    }

    private function cancelSingleMovement(FundMovement $movement, string $reason, User $user, bool $logActivity = true): void
    {
        if ($movement->status === FundMovementStatus::Cancelled) {
            return;
        }

        $movement->update([
            'status' => FundMovementStatus::Cancelled,
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        $this->recalculateAccountBalance($movement->account);

        if ($movement->related_fund_account_id) {
            $related = FundAccount::find($movement->related_fund_account_id);
            if ($related) {
                $this->recalculateAccountBalance($related);
            }
        }

        if ($logActivity && ! in_array($movement->type, [FundMovementType::TransferIn, FundMovementType::TransferOut], true)) {
            activity('funds')
                ->performedOn($movement)
                ->causedBy($user)
                ->event('fund.movement.cancelled')
                ->withProperties([
                    'reason' => $reason,
                    'amount' => $movement->amount,
                ])
                ->log('Mouvement financier annulé');
        }
    }

    public function assertRulePercentages(array $percentages): void
    {
        $this->percentageBasisPoints($percentages);
    }

    /** @return array<int, int> */
    private function percentageBasisPoints(array $percentages): array
    {
        $basisPoints = array_map(function (mixed $percentage): int {
            if (! is_int($percentage) && ! is_string($percentage)) {
                throw new InvalidArgumentException('Chaque pourcentage doit être fourni sans nombre flottant.');
            }

            $normalized = trim((string) $percentage);

            if (! preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
                throw new InvalidArgumentException('Chaque pourcentage doit avoir au maximum deux décimales.');
            }

            $points = ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');

            if ($points < 1 || $points > 10_000) {
                throw new InvalidArgumentException('Chaque pourcentage doit être compris entre 0,01 et 100.');
            }

            return $points;
        }, $percentages);

        if (array_sum($basisPoints) !== 10_000) {
            throw new InvalidArgumentException('La somme des pourcentages doit être exactement 100%.');
        }

        return $basisPoints;
    }

    private function money(mixed $amount, Currency|string $currency): Money
    {
        return $amount instanceof Money
            ? $amount
            : Money::fromDecimal($amount, $currency);
    }
}
