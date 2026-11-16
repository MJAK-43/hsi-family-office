<?php

namespace App\Enums;

enum FundMovementType: string
{
    case ContributionAllocation = 'contribution_allocation';
    case ManualIncome = 'manual_income';
    case ManualExpense = 'manual_expense';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';
    case ProjectFunding = 'project_funding';
    case LoanRepayment = 'loan_repayment';
    case LoanInterest = 'loan_interest';
    case PenaltyIncome = 'penalty_income';
    case WithdrawalRefund = 'withdrawal_refund';

    public function label(): string
    {
        return match ($this) {
            self::ContributionAllocation => 'Allocation cotisation',
            self::ManualIncome => 'Entrée manuelle',
            self::ManualExpense => 'Sortie manuelle',
            self::TransferIn => 'Transfert entrant',
            self::TransferOut => 'Transfert sortant',
            self::Adjustment => 'Ajustement',
            self::ProjectFunding => 'Financement projet',
            self::LoanRepayment => 'Remboursement de prêt',
            self::LoanInterest => 'Intérêts de prêt',
            self::PenaltyIncome => 'Pénalité de retard',
            self::WithdrawalRefund => 'Remboursement membre sortant',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ContributionAllocation => 'gold',
            self::ManualIncome => 'success',
            self::ManualExpense => 'danger',
            self::TransferIn => 'info',
            self::TransferOut => 'warning',
            self::Adjustment => 'neutral',
            self::ProjectFunding => 'gold',
            self::LoanRepayment => 'success',
            self::LoanInterest => 'success',
            self::PenaltyIncome => 'warning',
            self::WithdrawalRefund => 'danger',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }
}
