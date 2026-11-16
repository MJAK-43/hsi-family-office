<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContributionDueStatus;
use App\Enums\ContributionPaymentStatus;
use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Models\ContributionDue;
use App\Models\ContributionPayment;
use App\Models\MemberProfile;
use App\Services\ContributionDueService;
use App\Support\ContributionRules;
use App\Support\FundCurrency;
use App\Support\FundRules;
use App\Support\MemberRoleHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Données en LECTURE SEULE pour le service Assistant IA.
 * Chaque réponse ne concerne que le membre pour lequel l'assistant agit (X-Acting-User),
 * et ne contient que le strict nécessaire (pas d'email, de téléphone ni d'adresse).
 */
class AssistantController extends Controller
{
    public function __construct(private readonly ContributionDueService $dues) {}

    /** WhatsApp : retrouve le membre à partir de son numéro (accès « service »). */
    public function resolvePhone(Request $request): JsonResponse
    {
        $digits = preg_replace('/\D+/', '', (string) $request->query('phone'));
        abort_if(strlen($digits) < 8, 422, 'Numéro invalide.');
        $suffix = substr($digits, -9); // ignore l'indicatif et le 0 initial (+33 6… = 06…)

        $matches = MemberProfile::query()->with('user')->whereNotNull('phone')->get()
            ->filter(fn (MemberProfile $p) => strlen($d = preg_replace('/\D+/', '', (string) $p->phone)) >= 8 && substr($d, -9) === $suffix)
            ->values();

        if ($matches->count() !== 1 || ! $matches[0]->user) {
            return response()->json(['found' => false, 'reason' => $matches->count() > 1 ? 'ambiguous' : 'unknown']);
        }
        $profile = $matches[0];
        if ($profile->status !== MemberStatus::Active) {
            return response()->json(['found' => false, 'reason' => 'inactive']);
        }

        return response()->json(['found' => true, 'user_id' => $profile->user->id, 'first_name' => $this->firstName($profile->user->name), 'locale' => $profile->user->locale ?? null]);
    }

    /** Qui pose la question : prénom et rôles (pour adapter la réponse). */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = MemberRoleHelper::sort($user->getRoleNames());
        $this->log($request, 'me');

        return response()->json([
            'first_name' => $this->firstName($user->name),
            'roles' => array_map(fn ($r) => ['code' => $r, 'label' => MemberRoleHelper::label($r)], $roles),
            'member_since' => $user->memberProfile?->member_since?->toDateString() ?? $user->created_at?->toDateString(),
            'currency' => FundCurrency::code(),
            'today' => now()->toDateString(),
        ]);
    }

    /** Règles chiffrées du règlement intérieur, avec la section correspondante. */
    public function rules(Request $request): JsonResponse
    {
        $sections = [
            'contribution_quarterly_amount' => '4.1', 'contribution_due_day' => '4.1', 'contribution_confirmation_days' => '4.2',
            'contribution_penalty_amount' => '4.3', 'contribution_penalty_start_day' => '4.3', 'contribution_penalty_period_days' => '4.3',
            'loans_open_on' => '5', 'loan_min_contribution_months' => '5', 'loan_max_balance_percent' => '5', 'loan_annual_interest_rate' => '5',
            'loan_max_duration_months' => '5', 'loan_late_penalty_percent' => '5.5', 'loan_grace_days' => '5.5',
            'loan_second_reminder_days' => '5.5', 'loan_third_reminder_days' => '5.5',
            'withdrawal_notice_months' => '8', 'withdrawal_refund_percent' => '8', 'withdrawal_max_refund_months' => '8',
        ];
        $public = [
            'contribution_quarterly_amount', 'contribution_due_day', 'contribution_penalty_amount', 'contribution_penalty_start_day',
            'contribution_penalty_period_days', 'contribution_confirmation_days', 'loans_open_on', 'loan_min_contribution_months',
            'loan_max_balance_percent', 'loan_annual_interest_rate', 'loan_max_duration_months', 'loan_late_penalty_percent',
            'loan_grace_days', 'loan_second_reminder_days', 'loan_third_reminder_days', 'project_notice_min_days', 'project_notice_max_days',
            'refusal_wait_same_project_months', 'refusal_wait_other_project_days', 'vote_approval_percent', 'vote_duration_days',
            'admission_notice_months', 'withdrawal_notice_months', 'withdrawal_refund_percent', 'withdrawal_max_refund_months',
        ];
        $rules = [];
        foreach ($public as $key) {
            $def = FundRules::DEFINITIONS[$key];
            $rules[] = ['key' => $key, 'label' => $def['label'], 'value' => FundRules::get($key), 'section' => $sections[$key] ?? null, 'note' => $def['description'] ?? null];
        }
        $this->log($request, 'rules');

        return response()->json([
            'currency' => FundCurrency::code(),
            'policy_version' => FundRules::get('policy_version'),
            'quarters' => 'T1 = janvier-mars, T2 = avril-juin, T3 = juillet-septembre, T4 = octobre-décembre ; la cotisation est due avant le jour limite du premier mois du trimestre.',
            'votes' => 'Les abstentions ne sont pas comptées dans le seuil d’approbation.',
            'rules' => $rules,
        ]);
    }

    /** Situation des cotisations du membre : échéances et paiements en attente de confirmation. */
    public function contributions(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = CarbonImmutable::today();
        $dues = ContributionDue::query()->where('user_id', $user->id)->orderByDesc('due_date')->limit(8)->get()
            ->map(fn (ContributionDue $d) => $this->formatDue($d, $today));
        $pending = ContributionPayment::query()->where('user_id', $user->id)->where('status', ContributionPaymentStatus::Pending)
            ->latest('id')->limit(5)->get()
            ->map(fn (ContributionPayment $p) => [
                'amount' => (float) $p->amount, 'period' => $p->payment_period_label,
                'submitted_at' => ($p->submitted_at ?? $p->created_at)?->toDateString(),
                'status' => 'en attente de confirmation par le trésorier',
            ]);
        $validated = ContributionPayment::query()->where('user_id', $user->id)->where('status', ContributionPaymentStatus::Validated)->where('is_example', false);
        $this->log($request, 'contributions');

        return response()->json([
            'currency' => FundCurrency::code(),
            'today' => $today->toDateString(),
            'total_paid' => round((float) (clone $validated)->sum('amount'), 2),
            'payments_count' => (clone $validated)->count(),
            'dues' => $dues->values(),
            'awaiting_confirmation' => $pending->values(),
        ]);
    }

    /**
     * Montant à payer à une date donnée, pénalité comprise (section 4.3).
     * Le calcul est celui de l'application (ContributionDueService::penaltyFor) : l'IA ne calcule rien elle-même.
     */
    public function penalty(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $user = $request->user();
        $date = isset($data['date']) ? CarbonImmutable::parse($data['date']) : CarbonImmutable::today();

        $open = ContributionDue::query()->where('user_id', $user->id)
            ->whereIn('status', [ContributionDueStatus::Pending, ContributionDueStatus::Late])
            ->orderBy('due_date')->get()
            ->map(fn (ContributionDue $d) => $this->formatDue($d, $date));

        // Trimestre de la date demandée : si aucune échéance n'existe encore, on l'estime (sans rien enregistrer).
        $quarter = ContributionRules::quarterOfMonth($date->month);
        $key = ContributionRules::periodKey($date->year, $quarter);
        $exists = ContributionDue::query()->where('user_id', $user->id)->where('period_key', $key)->first();
        if (! $exists && ($plan = ContributionRules::plan())) {
            $estimate = new ContributionDue([
                'period_key' => $key, 'period_label' => ContributionRules::periodLabel($date->year, $quarter),
                'amount' => $plan->amount, 'currency' => $plan->currency,
                'due_date' => ContributionRules::dueDate($date->year, $quarter), 'status' => ContributionDueStatus::Pending,
            ]);
            $open->push([...$this->formatDue($estimate, $date), 'estimated' => true]);
        }
        $this->log($request, 'penalty', ['date' => $date->toDateString()]);

        return response()->json([
            'currency' => FundCurrency::code(),
            'date' => $date->toDateString(),
            'rule' => [
                'amount' => FundRules::get('contribution_penalty_amount'),
                'start_day' => FundRules::get('contribution_penalty_start_day'),
                'period_days' => FundRules::get('contribution_penalty_period_days'),
                'section' => '4.3',
            ],
            'already_paid_this_quarter' => $exists?->status === ContributionDueStatus::Paid,
            'dues' => $open->values(),
            'total_due' => round($open->sum('total_due'), 2),
        ]);
    }

    private function formatDue(ContributionDue $due, CarbonImmutable $at): array
    {
        $open = in_array($due->status, [ContributionDueStatus::Pending, ContributionDueStatus::Late], true);
        $penalty = ! $open || $due->penalty_waived ? (float) $due->penalty_amount : $this->dues->penaltyFor($due, $at);
        $dueDate = CarbonImmutable::parse($due->due_date);
        $weeks = 0;
        if ($open && $penalty > 0 && ($amount = FundRules::get('contribution_penalty_amount')) > 0) {
            $weeks = (int) round($penalty / $amount);
        }

        return [
            'period' => $due->period_label,
            'due_date' => $dueDate->toDateString(),
            'status' => match ($due->status) {
                ContributionDueStatus::Paid => 'payée',
                ContributionDueStatus::Late => 'en retard',
                ContributionDueStatus::Waived => 'dispensée',
                default => $dueDate->lt($at->startOfDay()) ? 'en retard' : 'à payer',
            },
            'paid_at' => $due->paid_at?->toDateString(),
            'amount' => (float) $due->amount,
            'penalty' => round($penalty, 2),
            'penalty_weeks' => $weeks,
            'penalty_waived' => (bool) $due->penalty_waived,
            'carried_penalty' => (float) $due->carried_penalty,
            'total_due' => $open ? round((float) $due->amount + $penalty + (float) $due->carried_penalty, 2) : 0.0,
        ];
    }

    private function firstName(?string $name): string
    {
        return explode(' ', trim((string) $name))[0] ?? '';
    }

    /** Trace dans le journal d'audit de HSI : quel membre, quelle information consultée par l'assistant. */
    private function log(Request $request, string $tool, array $properties = []): void
    {
        activity('assistant')->causedBy($request->user())->event('assistant.tool')
            ->withProperties(['tool' => $tool, 'channel' => $request->header('X-Channel', 'unknown'), ...$properties])
            ->log('Assistant IA : consultation « '.$tool.' »');
    }
}
