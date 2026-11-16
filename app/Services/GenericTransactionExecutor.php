<?php

namespace App\Services;

use App\Enums\FundMovementType;
use App\Models\ApprovalRequest;
use App\Models\FundAccount;
use App\Models\User;

/**
 * Exécution d'une transaction « simple » une fois validée : un mouvement d'entrée ou de sortie
 * sur un compte du fonds, ou un virement entre deux comptes.
 */
class GenericTransactionExecutor
{
    public function __construct(private FundService $funds) {}

    public function execute(ApprovalRequest $request, User $user): array
    {
        $payload = $request->payload ?? [];
        $type = $request->type;

        if ($type->direction === 'internal') {
            $from = FundAccount::findOrFail($payload['from_fund_account_id'] ?? 0);
            $to = FundAccount::findOrFail($payload['to_fund_account_id'] ?? 0);
            [$out, $in] = $this->funds->transfer($from, $to, (string) $request->amount, $payload['description'] ?? $request->title, $user, 'approval-'.$request->id);

            return ['movement_ids' => [$out->id, $in->id]];
        }

        $movement = $this->funds->createMovement([
            'fund_account_id' => $payload['fund_account_id'] ?? 0,
            'type' => $type->direction === 'in' ? FundMovementType::ManualIncome : FundMovementType::ManualExpense,
            'amount' => (string) $request->amount,
            'currency' => \App\Support\FundCurrency::code(),
            'direction' => $type->direction === 'in' ? 'credit' : 'debit',
            'label' => $request->title,
            'description' => trim(($payload['description'] ?? '').' — '.$type->name, ' —'),
            'reference' => $payload['reference'] ?? 'VAL-'.$request->id,
            'movement_date' => $payload['movement_date'] ?? now()->toDateString(),
            'created_by' => $request->requested_by,
            'validated_by' => $user->id,
        ]);

        return ['movement_id' => $movement->id];
    }
}
