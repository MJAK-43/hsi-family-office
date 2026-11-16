<?php

namespace App\Services;

use App\Enums\ContributionPaymentStatus;
use App\Enums\FundMovementType;
use App\Enums\PaymentMethod;
use App\Models\ApprovalRequest;
use App\Models\ContributionPayment;
use App\Models\TransactionType;
use App\Enums\ProjectStatus;
use App\Models\FundAccount;
use App\Models\FundMovement;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use App\Support\FundCurrency;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Projets et mouvements fictifs pour illustrer les flux par compte de fonds.
 * Tout est marqué (is_example + titre « Exemple — ») et se supprime d'un coup, soldes recalculés.
 */
class ExampleDataService
{
    public const PREFIX = 'Exemple — ';

    /** Deux projets par compte : [titre, secteur, montant, état du projet financé ou non]. */
    private const SCENARIOS = [
        'family-projects' => [
            ['Boulangerie familiale à Douala', 'commerce', 3000, ProjectStatus::Completed],
            ['Atelier de couture à Bafoussam', 'commerce', 1800, ProjectStatus::ApprovedByFamily],
        ],
        'solidarity' => [
            ['Aide aux frais médicaux d’un membre', 'health', 600, ProjectStatus::InProgress],
            ['Soutien après l’incendie d’une maison', 'other', 900, ProjectStatus::ApprovedByFamily],
        ],
        'education' => [
            ['Bourse d’études supérieures', 'education', 1200, ProjectStatus::Completed],
            ['Formation en informatique pour 3 jeunes', 'education', 700, ProjectStatus::ApprovedByFamily],
        ],
        'long-term-investment' => [
            ['Achat d’un terrain à Yaoundé', 'real_estate', 4000, ProjectStatus::InProgress],
            ['Parts dans une coopérative agricole', 'agriculture', 2500, ProjectStatus::ApprovedByFamily],
        ],
    ];

    private const GENERIC = [
        ['Petit commerce de quartier', 'commerce', 800, ProjectStatus::InProgress],
        ['Équipement pour une activité familiale', 'other', 500, ProjectStatus::ApprovedByFamily],
    ];

    public function __construct(private FundService $funds) {}

    public function create(User $user): int
    {
        if (Project::where('is_example', true)->exists()) {
            throw new RuntimeException('Des données d’exemple existent déjà : supprimez-les avant d’en recréer.');
        }
        $type = ProjectType::where('code', 'investment')->first() ?? ProjectType::where('is_loan', false)->first();
        $accounts = FundAccount::where('is_active', true)->where('slug', '!=', 'treasury')->orderBy('sort_order')->get();
        $currency = FundCurrency::code();
        $rate = $currency === 'XAF' ? 600 : 1; // montants lisibles en francs CFA
        $created = 0;

        DB::transaction(function () use ($accounts, $type, $user, $currency, $rate, &$created) {
            foreach ($accounts as $account) {
                foreach (self::SCENARIOS[$account->slug] ?? self::GENERIC as $i => [$title, $sector, $amount, $status]) {
                    $amount *= $rate;
                    $decidedAt = now()->subMonths(4 - $i)->subDays(3);
                    $project = Project::create([
                        'owner_id' => $user->id, 'project_type_id' => $type?->id, 'fund_account_id' => $account->id, 'is_example' => true,
                        'title' => self::PREFIX.$title, 'short_description' => 'Projet fictif pour illustrer les flux du compte « '.$account->name.' ».',
                        'description' => 'Donnée d’exemple : peut être supprimée depuis Fonds familial → Données d’exemple.',
                        'sector' => $sector, 'total_budget' => $amount * 2, 'personal_contribution' => $amount, 'requested_amount' => $amount,
                        'currency' => $currency, 'status' => ProjectStatus::ApprovedByFamily, 'created_by' => $user->id,
                        'submitted_at' => $decidedAt->copy()->subMonth(), 'decided_at' => $decidedAt,
                    ]);
                    $created++;
                    if ($status === ProjectStatus::ApprovedByFamily) continue;

                    // Apport fictif sur le compte, puis versement au projet.
                    $income = $this->funds->createMovement([
                        'fund_account_id' => $account->id, 'type' => FundMovementType::ManualIncome, 'amount' => (string) round($amount * 1.25, 2),
                        'currency' => $currency, 'direction' => 'credit', 'label' => self::PREFIX.'apport fictif pour '.$title,
                        'description' => 'Donnée d’exemple', 'reference' => 'EXEMPLE-'.$project->id, 'movement_date' => $decidedAt->toDateString(),
                        'created_by' => $user->id, 'validated_by' => $user->id,
                    ], logActivity: false);
                    $income->update(['is_example' => true]);
                    $funding = $this->funds->createProjectFundingMovement($project, $user, null, (string) $amount);
                    $fundedOn = $decidedAt->copy()->addDays(5);
                    $funding->update(['is_example' => true, 'movement_date' => $fundedOn->toDateString(), 'label' => self::PREFIX.'versement — '.$title]);
                    $project->update(['status' => $status, 'funded_amount' => $amount, 'funded_at' => $fundedOn, 'funded_by' => $user->id]);
                }
            }
            $created += $this->createIncomeExamples($user, $currency, $rate);
        });
        activity('funds')->causedBy($user)->event('fund.examples.created')->withProperties(['projects' => $created])->log('Données d’exemple créées');

        return $created;
    }

    public function remove(User $user): array
    {
        $result = DB::transaction(function () {
            $projectIds = Project::withTrashed()->where('is_example', true)->pluck('id');
            $paymentIds = ContributionPayment::withTrashed()->where('is_example', true)->pluck('id');
            $movements = FundMovement::withTrashed()->where(fn ($q) => $q->where('is_example', true)->orWhereIn('project_id', $projectIds)->orWhereIn('contribution_payment_id', $paymentIds));
            $accountIds = (clone $movements)->pluck('fund_account_id')->unique();
            $movementCount = (clone $movements)->count();
            (clone $movements)->forceDelete();
            DB::table('approval_requests')->where('subject_type', (new Project)->getMorphClass())->whereIn('subject_id', $projectIds)->delete();
            Project::withTrashed()->whereIn('id', $projectIds)->get()->each->forceDelete();
            ContributionPayment::withTrashed()->whereIn('id', $paymentIds)->get()->each->forceDelete();
            ApprovalRequest::where('title', 'like', self::PREFIX.'%')->each(function (ApprovalRequest $r) {
                DB::table('approval_decisions')->where('approval_request_id', $r->id)->delete();
                $r->delete();
            });
            TransactionType::where('code', 'like', 'example_%')->delete();
            FundAccount::whereIn('id', $accountIds)->get()->each(fn (FundAccount $a) => $this->funds->recalculateAccountBalance($a));

            return ['projects' => $projectIds->count(), 'movements' => $movementCount];
        });
        activity('funds')->causedBy($user)->event('fund.examples.removed')->withProperties($result)->log('Données d’exemple supprimées');

        return $result;
    }

    /**
     * Une entrée fictive pour chaque type de revenu du règlement : contribution volontaire, pénalité de cotisation,
     * prêt remboursé (capital, intérêts, pénalité), intérêts bancaires, type personnalisé (dons), ajustement.
     */
    private function createIncomeExamples(User $user, string $currency, int $rate): int
    {
        $treasury = FundAccount::where('slug', 'treasury')->where('is_active', true)->first();
        $family = FundAccount::where('slug', 'family-projects')->where('is_active', true)->first();
        $solidarity = FundAccount::where('slug', 'solidarity')->where('is_active', true)->first() ?? $family;
        $date = fn (int $daysAgo) => now()->subDays($daysAgo)->toDateString();
        $income = function (FundAccount $account, FundMovementType $type, float $amount, string $label, array $extra = []) use ($user, $currency, $rate) {
            $movement = $this->funds->createMovement([
                'fund_account_id' => $account->id, 'type' => $type, 'amount' => number_format($amount * $rate, 2, '.', ''), 'currency' => $currency,
                'direction' => 'credit', 'label' => self::PREFIX.$label, 'description' => 'Donnée d’exemple', 'created_by' => $user->id, 'validated_by' => $user->id,
                ...$extra,
            ], logActivity: false);
            $movement->update(['is_example' => true]);

            return $movement;
        };
        $asTransaction = function (string $code, FundMovement $movement, string $title) use ($user, $currency) {
            $type = TransactionType::where('code', $code)->first();
            if (! $type) return;
            ApprovalRequest::create([
                'transaction_type_id' => $type->id, 'title' => self::PREFIX.$title, 'amount' => $movement->amount, 'currency' => $currency,
                'status' => 'executed', 'executed_at' => now(), 'required_approvals' => 1, 'approver_roles' => ['treasurer'],
                'requested_by' => $user->id, 'result' => ['movement_id' => $movement->id], 'payload' => ['fund_account_id' => $movement->fund_account_id],
            ]);
        };
        $count = 0;

        if ($treasury) {
            // Pénalité de cotisation (section 4.3), intérêts bancaires (section 3) et ajustement.
            $income($treasury, FundMovementType::PenaltyIncome, 12, 'pénalité de retard de cotisation', ['movement_date' => $date(40)]);
            $bank = $income($treasury, FundMovementType::ManualIncome, 35, 'intérêts bancaires du trimestre', ['movement_date' => $date(20)]);
            $asTransaction('bank_income', $bank, 'intérêts bancaires du trimestre');
            $income($treasury, FundMovementType::Adjustment, 10, 'ajustement (écart de rapprochement bancaire)', ['movement_date' => $date(10)]);
        }

        if ($solidarity) {
            // Type d'entrée créé par l'administration.
            TransactionType::firstOrCreate(['code' => 'example_donations'], [
                'name' => 'Dons reçus (exemple)', 'description' => 'Type d’entrée d’exemple', 'direction' => 'in', 'required_approvals' => 1,
                'approver_roles' => ['treasurer', 'president'], 'is_system' => false, 'is_active' => true, 'created_by' => $user->id,
            ]);
            $donation = $income($solidarity, FundMovementType::ManualIncome, 200, 'don d’un ami de la famille', ['movement_date' => $date(30)]);
            $asTransaction('example_donations', $donation, 'don d’un ami de la famille');
        }

        // Contribution trimestrielle et contribution volontaire (section 4.1), réparties selon la règle d'allocation.
        try {
            $plan = \App\Support\ContributionRules::plan();
            $quarterly = ContributionPayment::create([
                'user_id' => $user->id, 'contribution_plan_id' => $plan?->id, 'amount' => (float) ($plan?->amount ?? 75 * $rate), 'currency' => $currency,
                'payment_month' => 1, 'payment_year' => now()->year, 'payment_period_label' => 'T1 '.now()->year.' (exemple)',
                'payment_method' => PaymentMethod::BankTransfer, 'reference' => 'EXEMPLE-TRIMESTRE', 'status' => ContributionPaymentStatus::Validated, 'is_example' => true,
                'paid_at' => $date(45), 'submitted_at' => now()->subDays(45), 'validated_at' => now()->subDays(44), 'validated_by' => $user->id,
                'notes' => 'Donnée d’exemple', 'created_by' => $user->id,
            ]);
            $this->funds->allocateContributionPayment($quarterly, $user)->each(fn ($m) => $m->update(['is_example' => true]));
            $payment = ContributionPayment::create([
                'user_id' => $user->id, 'amount' => 150 * $rate, 'currency' => $currency, 'payment_year' => now()->year,
                'payment_period_label' => 'Contribution volontaire', 'payment_method' => PaymentMethod::BankTransfer,
                'reference' => 'EXEMPLE-VOLONTAIRE', 'status' => ContributionPaymentStatus::Validated, 'is_example' => true,
                'paid_at' => $date(25), 'submitted_at' => now()->subDays(25), 'validated_at' => now()->subDays(24), 'validated_by' => $user->id,
                'notes' => 'Donnée d’exemple', 'created_by' => $user->id,
            ]);
            $this->funds->allocateContributionPayment($payment, $user)->each(fn ($m) => $m->update(['is_example' => true]));
        } catch (\Throwable) {
            // Pas de règle d'allocation active : l'exemple de contribution volontaire est simplement omis.
        }

        // Prêt remboursé en partie : capital, intérêts (4 %/an) et pénalité de retard (1 %).
        $loanType = ProjectType::where('code', 'loan')->first();
        if ($family && $loanType) {
            $amount = 1200 * $rate;
            $project = Project::create([
                'owner_id' => $user->id, 'project_type_id' => $loanType->id, 'fund_account_id' => $family->id, 'is_example' => true,
                'title' => self::PREFIX.'Prêt pour une moto de livraison', 'short_description' => 'Prêt fictif remboursé en partie, pour illustrer capital, intérêts et pénalités.',
                'description' => 'Donnée d’exemple.', 'sector' => 'commerce', 'total_budget' => $amount, 'personal_contribution' => 0, 'requested_amount' => $amount,
                'currency' => $currency, 'status' => ProjectStatus::ApprovedByFamily, 'created_by' => $user->id, 'loan_duration_months' => 12,
                'loan_interest_rate' => 4, 'repayment_plan' => '12 mensualités.', 'submitted_at' => now()->subMonths(5), 'decided_at' => now()->subMonths(4),
            ]);
            $income($family, FundMovementType::ManualIncome, 1300, 'apport fictif pour le prêt moto', ['movement_date' => $date(125)]);
            $funding = $this->funds->createProjectFundingMovement($project, $user, null, (string) $amount);
            $funding->update(['is_example' => true, 'movement_date' => $date(120), 'label' => self::PREFIX.'versement du prêt moto']);
            $project->update(['status' => ProjectStatus::InProgress, 'funded_amount' => $amount, 'funded_at' => now()->subDays(120), 'funded_by' => $user->id]);
            $income($family, FundMovementType::LoanRepayment, 300, 'remboursement du prêt moto (capital)', ['project_id' => $project->id, 'movement_date' => $date(60)]);
            $income($family, FundMovementType::LoanInterest, 12, 'intérêts du prêt moto', ['project_id' => $project->id, 'movement_date' => $date(60)]);
            $income($family, FundMovementType::PenaltyIncome, 3, 'pénalité de retard du prêt moto', ['project_id' => $project->id, 'movement_date' => $date(60)]);
            $count++;
        }

        return $count;
    }
}
