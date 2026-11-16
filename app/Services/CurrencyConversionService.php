<?php

namespace App\Services;

use App\Models\ApplicationSetting;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\FundCurrency;
use App\Support\FundRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Change la devise du fonds et convertit tous les montants enregistrés au taux donné.
 * Les montants restent stockés avec deux décimales ; les égalités (échéance payée = montant dû,
 * capital + intérêts = échéance, somme des versements = remboursement) sont conservées après arrondi.
 */
class CurrencyConversionService
{
    /** Colonnes de montants simples, converties une à une. */
    public const SIMPLE_COLUMNS = [
        'contribution_plans' => ['amount'],
        'contribution_payments' => ['amount'],
        'contribution_dues' => ['amount', 'penalty_amount', 'carried_penalty'],
        'fund_accounts' => ['current_balance'],
        'fund_movements' => ['amount'],
        'projects' => ['total_budget', 'personal_contribution', 'requested_amount', 'funded_amount', 'loan_total_due'],
        'project_milestones' => ['planned_amount', 'spent_amount'],
        'project_progress_reports' => ['spent_amount'],
        'approval_requests' => ['amount'],
        'loan_repayments' => ['amount'],
        'withdrawal_requests' => ['total_contributions'],
    ];

    /** Tables dont la colonne « currency » prend le nouveau code. */
    public const CURRENCY_TABLES = ['contribution_plans', 'contribution_payments', 'contribution_dues', 'fund_accounts', 'fund_movements', 'projects', 'approval_requests'];

    /** Règles du fonds exprimées en montant. */
    public const MONEY_SETTINGS = ['contribution_quarterly_amount', 'contribution_penalty_amount'];

    public function convert(string $to, float $rate, User $user): array
    {
        $from = FundCurrency::code();
        $to = strtoupper($to);
        if (! array_key_exists($to, FundCurrency::SUPPORTED)) throw new RuntimeException('Devise non prise en charge.');
        if ($to === $from) throw new RuntimeException('Le fonds utilise déjà cette devise.');
        if ($rate <= 0) throw new RuntimeException('Le taux de conversion doit être positif.');
        if (ApprovalRequest::where('status', 'pending')->exists()) {
            throw new RuntimeException('Des opérations attendent encore des validations : validez-les ou annulez-les avant de changer de devise.');
        }

        $counts = DB::transaction(function () use ($from, $to, $rate, $user) {
            $counts = [];
            foreach (self::SIMPLE_COLUMNS as $table => $columns) {
                if (! Schema::hasTable($table)) continue;
                $columns = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
                $counts[$table] = 0;
                DB::table($table)->orderBy('id')->select(['id', ...$columns])->chunkById(500, function ($rows) use ($table, $columns, $rate, &$counts) {
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($columns as $column) {
                            if ($row->{$column} !== null) $values[$column] = $this->apply($row->{$column}, $rate);
                        }
                        if ($values) DB::table($table)->where('id', $row->id)->update($values);
                        $counts[$table]++;
                    }
                });
            }

            $counts['loan_installments'] = $this->convertInstallments($rate);
            $counts['withdrawal_installments'] = $this->convertWithdrawals($rate);

            foreach (self::CURRENCY_TABLES as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'currency')) DB::table($table)->update(['currency' => $to]);
            }

            foreach (self::MONEY_SETTINGS as $key) {
                $setting = ApplicationSetting::where('key', $key)->first();
                $current = $setting?->value ?? FundRules::DEFINITIONS[$key]['default'];
                $value = (string) $this->apply($current, $rate);
                ApplicationSetting::updateOrCreate(['key' => $key], [
                    'group' => $setting?->group ?? 'fund_rules', 'label' => $setting?->label ?? FundRules::DEFINITIONS[$key]['label'],
                    'type' => $setting?->type ?? 'decimal', 'value' => $value, 'updated_by' => $user->id,
                ]);
            }

            ApplicationSetting::updateOrCreate(['key' => 'default_currency'], [
                'group' => 'general', 'label' => 'Devise par défaut', 'type' => 'string', 'value' => $to, 'is_public' => true, 'updated_by' => $user->id,
            ]);

            activity('administration')->causedBy($user)->event('administration.currency.converted')
                ->withProperties(['from' => $from, 'to' => $to, 'rate' => $rate, 'rows' => $counts])
                ->log("Devise du fonds changée : {$from} → {$to} (1 {$from} = {$rate} {$to})");

            return $counts;
        });

        FundCurrency::flush();
        FundRules::flush();

        return ['from' => $from, 'to' => $to, 'rate' => $rate, 'rows' => $counts];
    }

    private function apply(mixed $amount, float $rate): float
    {
        return round((float) $amount * $rate, 2);
    }

    /** Échéances de prêt : capital + intérêts = échéance, et une échéance soldée le reste. */
    private function convertInstallments(float $rate): int
    {
        if (! Schema::hasTable('loan_installments')) return 0;
        $count = 0;
        DB::table('loan_installments')->orderBy('id')->chunkById(500, function ($rows) use ($rate, &$count) {
            foreach ($rows as $row) {
                $amount = $this->apply($row->amount, $rate);
                $principal = $this->apply($row->principal, $rate);
                $penalty = $this->apply($row->penalty_amount, $rate);
                $paid = (float) $row->paid_amount >= (float) $row->amount ? $amount : min($amount, $this->apply($row->paid_amount, $rate));
                $penaltyPaid = (float) $row->penalty_paid >= (float) $row->penalty_amount ? $penalty : min($penalty, $this->apply($row->penalty_paid, $rate));
                DB::table('loan_installments')->where('id', $row->id)->update([
                    'amount' => $amount, 'principal' => $principal, 'interest' => round($amount - $principal, 2),
                    'paid_amount' => $paid, 'penalty_amount' => $penalty, 'penalty_paid' => $penaltyPaid,
                ]);
                $count++;
            }
        });

        return $count;
    }

    /** Retraits : la somme des versements prévus reste égale au montant à rembourser. */
    private function convertWithdrawals(float $rate): int
    {
        if (! Schema::hasTable('withdrawal_requests')) return 0;
        $count = 0;
        foreach (DB::table('withdrawal_requests')->get(['id', 'refund_amount']) as $request) {
            $refund = $this->apply($request->refund_amount, $rate);
            DB::table('withdrawal_requests')->where('id', $request->id)->update(['refund_amount' => $refund]);
            $installments = DB::table('withdrawal_installments')->where('withdrawal_request_id', $request->id)->orderBy('id')->get(['id', 'amount']);
            $sumBefore = round((float) $installments->sum('amount'), 2);
            $running = 0.0;
            foreach ($installments->values() as $i => $installment) {
                $last = $i === $installments->count() - 1;
                $value = $last && abs($sumBefore - (float) $request->refund_amount) < 0.01
                    ? round($refund - $running, 2)
                    : $this->apply($installment->amount, $rate);
                $running = round($running + $value, 2);
                DB::table('withdrawal_installments')->where('id', $installment->id)->update(['amount' => $value]);
                $count++;
            }
        }

        return $count;
    }
}
