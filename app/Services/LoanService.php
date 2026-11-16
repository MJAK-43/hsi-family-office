<?php

namespace App\Services;

use App\Enums\FundMovementType;
use App\Enums\ProjectStatus;
use App\Enums\VoteStatus;
use App\Enums\VoteType;
use App\Models\ApprovalRequest;
use App\Models\FundAccount;
use App\Models\LoanInstallment;
use App\Models\LoanRepayment;
use App\Models\Project;
use App\Models\User;
use App\Models\Vote;
use App\Notifications\FundNotification;
use App\Support\FundRules;
use App\Support\Notifier;
use App\Support\ProjectFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Prêts (section 5) : décaissement validé par les trésoriers, échéancier mensuel au taux annuel,
 * remboursements, pénalité de 1 % par mois sur l'échéance impayée et trois niveaux de rappel.
 */
class LoanService
{
    public function __construct(
        private ApprovalService $approvals,
        private EligibilityService $eligibility,
        private ProjectService $projects,
        private FundService $funds,
    ) {}

    /** Échéancier à mensualités constantes (taux annuel ÷ 12). */
    public function buildSchedule(float $amount, float $annualRate, int $months, string $firstDueDate): array
    {
        $months = max(1, $months);
        $rate = $annualRate / 100 / 12;
        $payment = $rate > 0 ? $amount * $rate / (1 - (1 + $rate) ** -$months) : $amount / $months;
        $balance = $amount;
        $first = CarbonImmutable::parse($firstDueDate);
        $rows = [];
        for ($i = 1; $i <= $months; $i++) {
            $interest = round($balance * $rate, 2);
            $principal = $i === $months ? round($balance, 2) : round($payment - $interest, 2);
            $balance = round($balance - $principal, 2);
            $rows[] = [
                'number' => $i,
                'due_date' => $first->addMonthsNoOverflow($i - 1)->toDateString(),
                'principal' => $principal,
                'interest' => $interest,
                'amount' => round($principal + $interest, 2),
            ];
        }

        return $rows;
    }

    /** Prépare le versement : il ne sera exécuté qu'après le nombre de validations prévu. */
    public function prepareDisbursement(Project $project, User $user, array $data): ApprovalRequest
    {
        $project->loadMissing('type', 'owner');
        if ($project->status !== ProjectStatus::ApprovedByFamily) {
            throw new RuntimeException('Seul un projet approuvé par le vote des membres peut être décaissé.');
        }
        if ($project->approvalRequests()->where('status', 'pending')->whereHas('type', fn ($q) => $q->where('code', 'loan_disbursement'))->exists()) {
            throw new RuntimeException('Un décaissement est déjà en attente de validation pour ce projet.');
        }
        $amount = round((float) ($data['amount'] ?? $project->requested_amount), 2);
        if ($amount <= 0 || $amount > (float) $project->requested_amount) {
            throw new RuntimeException('Le montant doit être positif et ne pas dépasser le montant demandé ('.ProjectFormatter::money($project->requested_amount).').');
        }
        $cap = $this->eligibility->fundCap();
        if ($amount > $cap) {
            throw new RuntimeException('Le montant dépasse le plafond de '.FundRules::get('loan_max_balance_percent').' % du solde du fonds ('.ProjectFormatter::money($cap).').');
        }

        $payload = ['amount' => $amount, 'disbursed_on' => $data['disbursed_on'] ?? now()->toDateString()];
        if ($project->isLoan()) {
            $months = (int) ($data['duration_months'] ?? $project->loan_duration_months ?? 0);
            $max = FundRules::get('loan_max_duration_months');
            if ($months < 1 || $months > $max) {
                throw new RuntimeException('La durée de remboursement doit être comprise entre 1 et '.$max.' mois.');
            }
            $firstDue = $data['first_due_date'] ?? CarbonImmutable::parse($payload['disbursed_on'])->addMonthNoOverflow()->toDateString();
            $lastDue = CarbonImmutable::parse($firstDue)->addMonthsNoOverflow($months - 1);
            if ($lastDue->gt(CarbonImmutable::parse($payload['disbursed_on'])->addMonthsNoOverflow($max))) {
                throw new RuntimeException('La dernière échéance dépasserait '.$max.' mois après le versement.');
            }
            $payload += [
                'duration_months' => $months,
                'first_due_date' => $firstDue,
                'interest_rate' => FundRules::get('loan_annual_interest_rate'),
            ];
            $payload['schedule'] = $this->buildSchedule($amount, $payload['interest_rate'], $months, $firstDue);
        }

        return $this->approvals->request('loan_disbursement', 'Décaissement — '.$project->title, $user, $amount, $payload, $project, $project->owner);
    }

    public function executeDisbursement(ApprovalRequest $request, User $user): array
    {
        $project = Project::with('type', 'owner')->findOrFail($request->subject_id);
        $payload = $request->payload;
        $project = $this->projects->fund($project, $user, 'approval-'.$request->id, (string) $payload['amount']);

        if ($project->isLoan()) {
            foreach ($payload['schedule'] as $row) {
                $project->installments()->create($row + ['status' => 'pending']);
            }
            $total = collect($payload['schedule'])->sum('amount');
            $project->update([
                'status' => ProjectStatus::InProgress, 'started_at' => now(),
                'loan_interest_rate' => $payload['interest_rate'], 'loan_total_due' => round($total, 2),
                'first_due_date' => $payload['first_due_date'], 'loan_duration_months' => $payload['duration_months'],
            ]);
            activity('projects')->performedOn($project)->causedBy($user)->event('loan.schedule_created')
                ->withProperties(['installments' => count($payload['schedule']), 'total_due' => round($total, 2), 'rate' => $payload['interest_rate']])
                ->log('Échéancier de remboursement créé');
        }

        $lines = [['Montant versé : :amount.', ['amount' => ProjectFormatter::money($payload['amount'])]]];
        if ($project->isLoan()) {
            $lines[] = [':count échéances à partir du :date, total à rembourser :total.', [
                'count' => count($payload['schedule']), 'date' => CarbonImmutable::parse($payload['first_due_date'])->format('d/m/Y'),
                'total' => ProjectFormatter::money($project->loan_total_due),
            ]];
        }
        Notifier::send([$project->owner, ...Notifier::treasurers()], new FundNotification(
            ['Décaissement effectué : :title', ['title' => $project->title]], $lines,
            route('projects.show', $project), 'Voir le dossier', 'loan',
        ));

        return ['project_id' => $project->id];
    }

    public function outstanding(Project $project): float
    {
        return round($project->installments->sum(fn (LoanInstallment $i) => $i->remaining() + $i->remainingPenalty()), 2);
    }

    public function requestRepayment(Project $project, User $user, float $amount, string $paidOn, ?string $note): ApprovalRequest
    {
        $project->load('installments', 'owner');
        if (! $project->isLoan() || $project->status !== ProjectStatus::InProgress) {
            throw new RuntimeException('Ce projet n’a pas de prêt en cours de remboursement.');
        }
        $outstanding = $this->outstanding($project);
        if ($amount <= 0 || $amount > $outstanding + 0.001) {
            throw new RuntimeException('Le montant doit être positif et ne pas dépasser le reste dû ('.ProjectFormatter::money($outstanding).').');
        }

        return $this->approvals->request('loan_repayment', 'Remboursement — '.$project->title, $user, $amount,
            ['paid_on' => $paidOn, 'note' => $note], $project, $project->owner);
    }

    public function executeRepayment(ApprovalRequest $request, User $user): array
    {
        $project = Project::with('installments', 'owner')->findOrFail($request->subject_id);
        $left = round((float) $request->amount, 2);
        $allocation = [];
        $penaltyPart = 0.0;
        $interestPart = 0.0;
        $paidOn = $request->payload['paid_on'] ?? now()->toDateString();

        foreach ($project->installments as $installment) {
            if ($left <= 0) break;
            $penalty = min($left, $installment->remainingPenalty());
            $left = round($left - $penalty, 2);
            $part = min($left, $installment->remaining());
            $left = round($left - $part, 2);
            if ($penalty <= 0 && $part <= 0) continue;
            $paid = round((float) $installment->paid_amount + $part, 2);
            $installment->update([
                'penalty_paid' => round((float) $installment->penalty_paid + $penalty, 2),
                'paid_amount' => $paid,
                'status' => $paid >= (float) $installment->amount ? 'paid' : 'partial',
                'paid_at' => $paid >= (float) $installment->amount ? CarbonImmutable::parse($paidOn) : null,
            ]);
            $penaltyPart += $penalty;
            // Part d'intérêts de ce versement, au prorata de l'échéance (capital + intérêts).
            $interest = (float) $installment->amount > 0 ? round($part * (float) $installment->interest / (float) $installment->amount, 2) : 0.0;
            $interestPart += $interest;
            $allocation[] = ['installment' => $installment->number, 'amount' => $part, 'interest' => $interest, 'penalty' => $penalty];
        }
        if ($left > 0.001) {
            throw new RuntimeException('Le montant dépasse le reste dû du prêt.');
        }

        // L'argent revient sur le compte qui a prêté ; capital, intérêts et pénalités sont distingués dans le registre.
        $account = FundAccount::query()->when($project->fund_account_id, fn ($q) => $q->whereKey($project->fund_account_id), fn ($q) => $q->where('slug', 'family-projects'))->firstOrFail();
        $capitalPart = round((float) $request->amount - $penaltyPart - $interestPart, 2);
        $leg = fn (FundMovementType $type, float $value, string $label) => $this->funds->createMovement([
            'fund_account_id' => $account->id, 'project_id' => $project->id,
            'type' => $type, 'amount' => number_format($value, 2, '.', ''), 'currency' => \App\Support\FundCurrency::code(),
            'direction' => 'credit', 'label' => $label.' — '.$project->title,
            'reference' => 'VAL-'.$request->id, 'movement_date' => $paidOn,
            'created_by' => $request->requested_by, 'validated_by' => $user->id,
        ]);
        $movement = $capitalPart > 0 ? $leg(FundMovementType::LoanRepayment, $capitalPart, 'Remboursement de prêt') : null;
        $interestMovement = $interestPart > 0 ? $leg(FundMovementType::LoanInterest, $interestPart, 'Intérêts de prêt') : null;
        $penaltyMovement = $penaltyPart > 0 ? $leg(FundMovementType::PenaltyIncome, $penaltyPart, 'Pénalité de retard de remboursement') : null;
        $movement ??= $interestMovement ?? $penaltyMovement;
        $repayment = LoanRepayment::create([
            'project_id' => $project->id, 'amount' => $request->amount, 'paid_on' => $paidOn, 'allocation' => $allocation,
            'note' => $request->payload['note'] ?? null, 'recorded_by' => $request->requested_by,
            'approval_request_id' => $request->id, 'fund_movement_id' => $movement->id,
        ]);
        activity('projects')->performedOn($project)->causedBy($user)->event('loan.repayment_recorded')
            ->withProperties(['amount' => (float) $request->amount, 'penalties' => $penaltyPart, 'allocation' => $allocation])
            ->log('Remboursement enregistré : '.ProjectFormatter::money($request->amount));

        $project->load('installments');
        if ($this->outstanding($project) <= 0.001) {
            $project->update(['repaid_at' => now(), 'status' => ProjectStatus::Completed, 'completed_at' => now(), 'progress_percent' => 100, 'closure_note' => 'Prêt entièrement remboursé, intérêts et pénalités compris.']);
            activity('projects')->performedOn($project)->causedBy($user)->event('loan.repaid')->log('Prêt entièrement remboursé');
            Notifier::send([$project->owner, ...Notifier::treasurers()], new FundNotification(
                ['Prêt entièrement remboursé : :title', ['title' => $project->title]],
                [['Merci ! Le prêt est soldé, intérêts et pénalités compris. Vous pouvez de nouveau demander un prêt.']],
                route('projects.show', $project), 'Voir le dossier', 'loan',
            ));
        }

        return ['repayment_id' => $repayment->id, 'movement_id' => $movement->id];
    }

    /** Traitement quotidien : retards, pénalités (1 % par mois entamé) et rappels à 10, 15 et 30 jours. */
    public function processDaily(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $penaltyPercent = FundRules::get('loan_late_penalty_percent');
        $grace = FundRules::get('loan_grace_days');
        $second = FundRules::get('loan_second_reminder_days');
        $third = FundRules::get('loan_third_reminder_days');
        $stats = ['late' => 0, 'reminders' => 0];

        LoanInstallment::query()->with('project.owner')
            ->whereIn('status', ['pending', 'partial', 'late'])
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereHas('project', fn ($q) => $q->where('status', ProjectStatus::InProgress))
            ->each(function (LoanInstallment $installment) use ($today, $penaltyPercent, $grace, $second, $third, &$stats) {
                if ($installment->remaining() <= 0) return;
                $due = CarbonImmutable::parse($installment->due_date)->startOfDay();
                $daysLate = (int) $due->diffInDays($today);
                // Dès le lendemain de l'échéance, 1 % de l'impayé par mois entamé, sans délai de grâce.
                $monthsStarted = (int) floor($due->addDay()->diffInMonths($today)) + 1;
                $penalty = round($installment->remaining() * $penaltyPercent / 100 * $monthsStarted, 2);
                $attributes = ['status' => 'late', 'penalty_amount' => max((float) $installment->penalty_amount, $penalty)];
                if ($installment->status !== 'late') $stats['late']++;

                $level = $daysLate >= $third ? 3 : ($daysLate >= $second ? 2 : ($daysLate > $grace ? 1 : 0));
                if ($level > $installment->reminder_level) {
                    $installment->update($attributes);
                    $this->remind($installment->fresh('project.owner'), $level, $daysLate);
                    $attributes = ['reminder_level' => $level, 'last_reminded_at' => now()];
                    $stats['reminders']++;
                }
                $installment->update($attributes);
            });

        return $stats;
    }

    private function remind(LoanInstallment $installment, int $level, int $daysLate): void
    {
        $project = $installment->project;
        $params = [
            'title' => $project->title, 'name' => $project->owner?->name, 'number' => $installment->number,
            'date' => $installment->due_date->format('d/m/Y'), 'days' => $daysLate,
            'amount' => ProjectFormatter::money($installment->remaining()), 'penalty' => ProjectFormatter::money($installment->remainingPenalty()),
        ];
        $lines = [
            ['Échéance n° :number du :date : reste dû :amount, pénalités :penalty.', $params],
            ['Retard : :days jours.', $params],
        ];

        if ($level === 1) {
            Notifier::send([$project->owner, ...Notifier::treasurers()], new FundNotification(
                ['1er rappel — remboursement en retard : :title', $params],
                [...$lines, ['Merci de régulariser le paiement auprès du trésorier de votre zone.']],
                route('projects.show', $project), 'Voir le prêt', 'loan',
            ));
        } elseif ($level === 2) {
            Notifier::send(Notifier::activeMembers(), new FundNotification(
                ['2e rappel — le groupe est informé d’un retard de remboursement (:title)', $params],
                [['Le prêt de :name est en retard de remboursement.', $params], ...$lines],
                route('projects.show', $project), 'Voir le prêt', 'loan',
            ));
        } else {
            $vote = $this->prepareCollectiveDecision($project, $installment, $daysLate);
            Notifier::send(Notifier::activeMembers(), new FundNotification(
                ['3e rappel — décision collective à prendre (:title)', $params],
                [['Le prêt de :name a plus de :days jours de retard. Les membres doivent décider de la suite à donner.', $params], ...$lines],
                route('votes.show', $vote), 'Voir la décision à prendre', 'loan',
            ));
        }
        activity('projects')->performedOn($project)->event('loan.reminder')
            ->withProperties(['level' => $level, 'installment' => $installment->number, 'days_late' => $daysLate])
            ->log('Rappel de remboursement niveau '.$level.' envoyé');
    }

    /** Prépare un vote en brouillon : le président choisit les options et l'ouvre. */
    private function prepareCollectiveDecision(Project $project, LoanInstallment $installment, int $daysLate): Vote
    {
        $existing = Vote::query()->where('project_id', $project->id)->where('type', VoteType::Governance)
            ->whereIn('status', [VoteStatus::Draft, VoteStatus::Scheduled, VoteStatus::Open])->first();
        if ($existing) return $existing;

        return DB::transaction(function () use ($project, $installment, $daysLate) {
            $creator = User::role('president')->first() ?? User::role('super_admin')->first();
            $vote = Vote::create([
                'title' => 'Retard de remboursement — '.$project->title,
                'description' => 'Section 5.6 : le prêt de '.$project->owner?->name.' a '.$daysLate.' jours de retard (échéance n° '.$installment->number.'). Les membres décident de la suite à donner.',
                'type' => VoteType::Governance, 'project_id' => $project->id, 'status' => VoteStatus::Draft,
                'opens_at' => null, 'closes_at' => now()->addDays(FundRules::get('vote_duration_days')),
                'quorum_percent' => 0, 'majority_rule' => \App\Enums\MajorityRule::Threshold, 'allow_abstention' => true,
                'ballot_visibility' => 'secret', 'eligible_count' => 0, 'created_by' => $creator?->id,
            ]);
            foreach ([['Accorder un nouveau délai', 'option_1'], ['Engager un recouvrement formel', 'option_2'], ['Abstention', 'abstain']] as $index => [$label, $value]) {
                $vote->options()->create(['label' => $label, 'value' => $value, 'sort_order' => $index + 1]);
            }

            return $vote;
        });
    }
}
