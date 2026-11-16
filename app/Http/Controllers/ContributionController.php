<?php

namespace App\Http\Controllers;

use App\Enums\ContributionPaymentStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\RejectContributionPaymentRequest;
use App\Http\Requests\StoreContributionPaymentRequest;
use App\Http\Requests\UpdateContributionPaymentRequest;
use App\Http\Requests\ValidateContributionPaymentRequest;
use App\Models\ContributionPayment;
use App\Models\ContributionDue;
use App\Models\ContributionPlan;
use App\Models\Country;
use App\Models\FamilyBranch;
use App\Models\PaymentProof;
use App\Models\User;
use App\Notifications\ContributionStatusNotification;
use App\Services\FundService;
use App\Services\ContributionDueService;
use App\Support\ContributionAccess;
use App\Support\ContributionFormatter;
use App\Support\ContributionRules;
use App\Support\Notifier;
use App\Notifications\FundNotification;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContributionController extends Controller
{
    public function __construct(private FundService $fundService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            ContributionAccess::canViewAll($user) || ContributionAccess::canViewOwn($user),
            403,
        );

        $query = ContributionPayment::query()
            ->with(['user.memberProfile.country', 'user.memberProfile.familyBranch', 'plan', 'validator'])
            ->when(! ContributionAccess::canViewAll($user), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->search, function ($q, $search) {
                $q->whereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->payment_method, fn ($q, $method) => $q->where('payment_method', $method))
            ->when($request->payment_month, fn ($q, $month) => $q->where('payment_month', $month))
            ->when($request->payment_year, fn ($q, $year) => $q->where('payment_year', $year))
            ->when($request->country_id, function ($q, $countryId) {
                $q->whereHas('user.memberProfile', fn ($p) => $p->where('country_id', $countryId));
            })
            ->when($request->zone_id, function ($q, $zoneId) {
                $q->whereHas('user.memberProfile', fn ($p) => $p->where('zone_id', $zoneId));
            })
            ->when($request->family_branch_id, function ($q, $branchId) {
                $q->whereHas('user.memberProfile', fn ($p) => $p->where('family_branch_id', $branchId));
            })
            ->latest('submitted_at')
            ->latest('created_at');

        $payments = $query->paginate($request->integer('per_page', 10))->withQueryString();
        $payments->getCollection()->transform(fn (ContributionPayment $p) => $this->formatPayment($p));
        $dues = ContributionDue::query()->with(['user', 'plan'])
            ->when(! ContributionAccess::canViewAll($user), fn ($q) => $q->where('user_id', $user->id))
            ->whereIn('status', ['pending', 'late'])->orderBy('due_date')->limit(25)->get()
            ->map(fn (ContributionDue $due) => [
                'id' => $due->id, 'member' => $due->user?->name, 'plan' => $due->plan?->name,
                'period' => $due->period_label, 'amount' => ContributionFormatter::money($due->amount, $due->currency),
                'due_date' => $due->due_date?->format('d/m/Y'), 'status' => $due->status->value,
                'status_label' => $due->status->label(),
                'penalty' => (float) $due->penalty_amount > 0 ? ContributionFormatter::money($due->penalty_amount, $due->currency) : null,
                'penalty_waived' => $due->penalty_waived,
                'carried_penalty' => (float) $due->carried_penalty > 0 ? ContributionFormatter::money($due->carried_penalty, $due->currency) : null,
                'total' => ContributionFormatter::money((float) $due->amount + ($due->penalty_waived ? 0 : (float) $due->penalty_amount) + (float) $due->carried_penalty, $due->currency),
            ]);

        return Inertia::render('Contributions/Index', [
            'payments' => $payments,
            'filters' => $request->only([
                'search', 'status', 'payment_method', 'payment_month',
                'payment_year', 'country_id', 'family_branch_id', 'zone_id', 'per_page',
            ]),
            'zones' => Zone::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'register' => $this->quarterRegister($user, $request->integer('register_year') ?: now()->year),
            'rules' => ContributionRules::forForm(),
            'stats' => $this->indexStats($user),
            'countries' => Country::forDisplay(Country::where('is_active', true)->orderBy('name')->get(['id', 'name', 'flag_emoji'])),
            'branches' => FamilyBranch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => ContributionPaymentStatus::options(),
            'paymentMethods' => PaymentMethod::options(),
            'years' => $this->availableYears(),
            'can' => $this->indexPermissions($user),
            'dues' => $dues,
        ]);
    }

    public function processDues(Request $request, ContributionDueService $service): RedirectResponse
    {
        abort_unless(ContributionAccess::canManagePlans($request->user()), 403);
        $created = $service->generate();
        $result = $service->markLateAndRemind();
        return back()->with('success', __(':created échéance(s) créée(s), :late retard(s), :reminded relance(s).', ['created' => $created, 'late' => $result['late'], 'reminded' => $result['reminded']]));
    }

    /** Section 4.3 : pénalité annulée si le retard vient d'une erreur bancaire justifiée. */
    public function waivePenalty(Request $request, ContributionDue $contributionDue, ContributionDueService $service): RedirectResponse
    {
        abort_unless(ContributionAccess::canManagePlans($request->user()) || $request->user()->can('validate contribution payments'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        abort_if($contributionDue->penalty_waived || (float) $contributionDue->penalty_amount <= 0, 422, 'Aucune pénalité à annuler.');
        $service->waivePenalty($contributionDue, $request->user(), $data['reason']);

        return back()->with('success', 'Pénalité annulée.');
    }

    public function waiveDue(Request $request, ContributionDue $contributionDue): RedirectResponse
    {
        abort_unless(ContributionAccess::canManagePlans($request->user()), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        abort_if(in_array($contributionDue->status->value, ['paid', 'waived'], true), 422);
        $contributionDue->update(['status' => 'waived', 'waived_by' => $request->user()->id, 'waiver_reason' => $data['reason']]);
        activity('contributions')->performedOn($contributionDue)->causedBy($request->user())->event('contribution.due.waived')->log('Échéance de cotisation exonérée');
        return back()->with('success', 'Échéance exonérée.');
    }

    public function create(): Response
    {
        abort_unless(auth()->user()->can('submit contribution payments'), 403);

        return Inertia::render('Contributions/Create', $this->formOptions());
    }

    public function store(StoreContributionPaymentRequest $request): RedirectResponse
    {
        $user = $request->user();
        $targetUserId = ContributionAccess::canSelectMember($user)
            ? (int) $request->user_id
            : $user->id;

        $payment = DB::transaction(function () use ($request, $user, $targetUserId) {
            $payment = ContributionPayment::create([
                'user_id' => $targetUserId,
                ...$this->periodAttributes($request),
                'amount' => $request->amount,
                'currency' => strtoupper($request->currency),
                'payment_method' => $request->payment_method,
                'reference' => $request->reference,
                'status' => ContributionPaymentStatus::Pending,
                'paid_at' => $request->paid_at,
                'submitted_at' => now(),
                'notes' => $request->notes,
                'created_by' => $user->id,
            ]);

            if ($request->hasFile('proof')) {
                $this->storeProof($payment, $request->file('proof'), $user->id);
            }

            return $payment;
        });

        $this->logPaymentActivity($payment, 'contribution.payment.submitted', 'Cotisation soumise', $request);
        $this->notifyZoneTreasurers($payment);

        return redirect()->route('contributions.show', $payment)
            ->with('success', 'Cotisation soumise avec succès.');
    }

    public function show(ContributionPayment $contributionPayment): Response
    {
        $this->authorizePaymentView($contributionPayment);

        $contributionPayment->load(['user.memberProfile.country', 'plan', 'validator', 'rejecter', 'proofs.uploader']);

        $activities = Activity::query()
            ->with('causer')
            ->where('subject_type', ContributionPayment::class)
            ->where('subject_id', $contributionPayment->id)
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'created_at' => $activity->created_at?->format('d/m/Y H:i'),
                'causer' => $activity->causer?->name,
            ]);

        return Inertia::render('Contributions/Show', [
            'payment' => $this->formatPayment($contributionPayment, detailed: true),
            'activities' => $activities,
            'can' => $this->paymentPermissions($contributionPayment, request()->user()),
        ]);
    }

    public function edit(ContributionPayment $contributionPayment): Response
    {
        abort_unless(ContributionAccess::canModifyPayment(auth()->user(), $contributionPayment), 403);

        $contributionPayment->load(['user', 'plan']);

        return Inertia::render('Contributions/Edit', [
            'payment' => $this->formatPayment($contributionPayment, detailed: true),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateContributionPaymentRequest $request, ContributionPayment $contributionPayment): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $contributionPayment, $user) {
            $wasRejected = $contributionPayment->status === ContributionPaymentStatus::Rejected;

            $targetUserId = ContributionAccess::canSelectMember($user)
                ? (int) $request->user_id
                : $contributionPayment->user_id;

            $contributionPayment->update([
                'user_id' => $targetUserId,
                ...$this->periodAttributes($request),
                'amount' => $request->amount,
                'currency' => strtoupper($request->currency),
                'payment_method' => $request->payment_method,
                'reference' => $request->reference,
                'paid_at' => $request->paid_at,
                'notes' => $request->notes,
                'updated_by' => $user->id,
                'status' => $wasRejected ? ContributionPaymentStatus::Pending : $contributionPayment->status,
                'submitted_at' => $wasRejected ? now() : $contributionPayment->submitted_at,
                'rejected_by' => $wasRejected ? null : $contributionPayment->rejected_by,
                'rejected_at' => $wasRejected ? null : $contributionPayment->rejected_at,
                'rejection_reason' => $wasRejected ? null : $contributionPayment->rejection_reason,
            ]);

            if ($request->hasFile('proof')) {
                foreach ($contributionPayment->proofs as $oldProof) {
                    Storage::disk('local')->delete($oldProof->file_path);
                    $oldProof->delete();
                }
                $this->storeProof($contributionPayment, $request->file('proof'), $user->id);
            }
        });

        $this->logPaymentActivity($contributionPayment, 'contribution.payment.updated', 'Cotisation modifiée', $request);

        return redirect()->route('contributions.show', $contributionPayment)
            ->with('success', 'Cotisation mise à jour avec succès.');
    }

    public function validatePayment(ValidateContributionPaymentRequest $request, ContributionPayment $contributionPayment): RedirectResponse
    {
        // Plusieurs validations exigées, ou paiement de la personne qui valide : circuit de validation.
        $approvals = app(\App\Services\ApprovalService::class);
        $pending = \App\Models\ApprovalRequest::query()->where('subject_type', $contributionPayment->getMorphClass())
            ->where('subject_id', $contributionPayment->id)->where('status', 'pending')->first();
        if ($pending || $approvals->requiredApprovals('contribution') > 1 || $contributionPayment->user_id === $request->user()->id) {
            try {
                $result = $pending
                    ? $approvals->approve($pending, $request->user())
                    : $approvals->request('contribution', 'Cotisation — '.$contributionPayment->user?->name, $request->user(), (float) $contributionPayment->amount, [], $contributionPayment, $contributionPayment->user);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['validation' => $e->getMessage()]);
            }

            return back()->with('success', $result->status === 'executed'
                ? 'Cotisation validée et allouée au fonds familial.'
                : 'Validation enregistrée : une autre personne doit encore valider cette cotisation.');
        }

        try {
            $this->fundService->validateAndAllocateContributionPayment(
                $contributionPayment,
                $request->user(),
                $request->input('idempotency_key'),
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['validation' => __('La validation et l’allocation ont été annulées : :reason', ['reason' => __($e->getMessage())])]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['validation' => 'La validation et l’allocation ont été annulées à cause d’une erreur technique.']);
        }

        return back()->with('success', 'Cotisation validée et allouée au fonds familial.');

    }

    public function rejectPayment(RejectContributionPaymentRequest $request, ContributionPayment $contributionPayment): RedirectResponse
    {
        $contributionPayment->update([
            'status' => ContributionPaymentStatus::Rejected,
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $request->rejection_reason,
            'validated_by' => null,
            'validated_at' => null,
            'updated_by' => $request->user()->id,
        ]);

        $this->logPaymentActivity($contributionPayment, 'contribution.payment.rejected', 'Cotisation refusée', $request);
        $this->notifyRejectedPayment($contributionPayment, $request->rejection_reason);

        return back()->with('success', 'Cotisation refusée.');
    }

    private function notifyRejectedPayment(ContributionPayment $payment, string $reason): void
    {
        $payment->user?->notify(new ContributionStatusNotification($payment, 'rejected', $reason));
    }

    public function destroy(ContributionPayment $contributionPayment): RedirectResponse
    {
        abort_unless(auth()->user()->can('delete contributions'), 403);

        $contributionPayment->load('proofs');

        foreach ($contributionPayment->proofs as $proof) {
            Storage::disk('local')->delete($proof->file_path);
            $proof->delete();
        }

        $contributionPayment->delete();

        activity('contributions')
            ->performedOn($contributionPayment)
            ->causedBy(auth()->user())
            ->event('contribution.payment.deleted')
            ->log('Cotisation supprimée');

        return redirect()->route('contributions.index')
            ->with('success', 'Cotisation supprimée.');
    }

    public function downloadProof(PaymentProof $paymentProof): \Symfony\Component\HttpFoundation\Response
    {
        $paymentProof->load('payment');
        $user = auth()->user();

        abort_unless(
            ContributionAccess::canViewProof($user, $paymentProof->payment->user_id),
            403,
        );

        abort_unless(Storage::disk('local')->exists($paymentProof->file_path), 404);
        if (\App\Support\FilePreview::wantsInline()) return \App\Support\FilePreview::respond($paymentProof->file_path, $paymentProof->original_name);

        return Storage::disk('local')->download(
            $paymentProof->file_path,
            $paymentProof->original_name,
        );
    }

    private function formOptions(): array
    {
        $user = auth()->user();

        return [
            'plans' => ContributionPlan::where('is_active', true)->orderBy('name')->get()->map(fn (ContributionPlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'amount' => (float) $plan->amount,
                'currency' => $plan->currency,
                'frequency' => $plan->frequency->value,
                'frequency_label' => $plan->frequency->label(),
            ]),
            'paymentMethods' => PaymentMethod::options(),
            'members' => ContributionAccess::canSelectMember($user)
                ? User::whereHas('memberProfile')->with('memberProfile.zone')->orderBy('name')->get(['id', 'name', 'email'])
                    ->map(fn (User $m) => ['id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'zone' => $m->memberProfile?->zone?->name])
                : [],
            'rules' => ContributionRules::forForm(),
            'regulatoryPlanId' => ContributionRules::plan()?->id,
            'currentQuarter' => now()->quarter,
            'quarterDues' => $this->quarterDuesFor(ContributionAccess::canSelectMember($user) ? null : $user),
            'treasurersByMember' => $this->treasurersByMember(ContributionAccess::canSelectMember($user) ? null : $user),
            'canSelectMember' => ContributionAccess::canSelectMember($user),
            'defaultCurrency' => \App\Support\FundCurrency::code(),
            'defaultMonth' => now()->month,
            'defaultYear' => now()->year,
        ];
    }

    private function formatPayment(ContributionPayment $payment, bool $detailed = false): array
    {
        $payment->loadMissing(['user.memberProfile.country', 'user.memberProfile.familyBranch', 'plan', 'validator', 'rejecter', 'proofs']);

        $data = [
            'id' => $payment->id,
            'member' => [
                'id' => $payment->user?->id,
                'name' => $payment->user?->name,
                'email' => $payment->user?->email,
            ],
            'amount' => (float) $payment->amount,
            'amount_formatted' => ContributionFormatter::money($payment->amount, $payment->currency),
            'currency' => $payment->currency,
            'period' => ContributionFormatter::periodLabel(
                $payment->payment_month,
                $payment->payment_year,
                $payment->payment_period_label,
            ),
            'payment_month' => $payment->payment_month,
            'payment_year' => $payment->payment_year,
            'payment_method' => $payment->payment_method->value,
            'payment_method_label' => $payment->payment_method->label(),
            'reference' => $payment->reference ?? '—',
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'status_variant' => $payment->status->color(),
            'submitted_at' => $payment->submitted_at?->format('d/m/Y H:i'),
            'validated_by' => $payment->validator?->name ?? '—',
            'kind' => $payment->plan?->is_regulatory ? 'quarterly' : ($payment->contribution_plan_id ? 'plan' : 'voluntary'),
            'quarter' => $payment->plan?->is_regulatory ? ContributionRules::quarterOfMonth($payment->payment_month) : null,
            'zone' => $payment->user?->memberProfile?->zone?->name,
            ...$this->confirmationInfo($payment),
            'plan' => $payment->plan ? [
                'id' => $payment->plan->id,
                'name' => $payment->plan->name,
            ] : null,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'contribution_plan_id' => $payment->contribution_plan_id,
                'user_id' => $payment->user_id,
                'paid_at' => $payment->paid_at?->format('d/m/Y'),
                'paid_at_raw' => $payment->paid_at?->format('Y-m-d'),
                'notes' => $payment->notes,
                'validated_at' => $payment->validated_at?->format('d/m/Y H:i'),
                'rejected_at' => $payment->rejected_at?->format('d/m/Y H:i'),
                'rejection_reason' => $payment->rejection_reason,
                'rejecter' => $payment->rejecter?->name,
                'proofs' => $payment->proofs->map(fn (PaymentProof $proof) => [
                    'id' => $proof->id,
                    'original_name' => $proof->original_name,
                    'file_size' => $proof->file_size,
                    'uploaded_at' => $proof->created_at?->format('d/m/Y H:i'),
                    'download_url' => route('contributions.proofs.download', $proof),
                ]),
            ]);
        }

        return $data;
    }

    private function indexStats($user): array
    {
        $baseQuery = ContributionPayment::query();
        if (! ContributionAccess::canViewAll($user)) {
            $baseQuery->where('user_id', $user->id);
        }

        $totalCollected = (clone $baseQuery)->validated()->sum('amount');

        $validatedCount = (clone $baseQuery)->validated()->count();

        $pendingCount = (clone $baseQuery)->pending()->count();

        $rejectedCount = (clone $baseQuery)->rejected()->count();

        $lateMembers = ContributionDue::query()
            ->when(! ContributionAccess::canViewAll($user), fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'late')->distinct('user_id')->count('user_id');

        return [
            [
                'title' => 'Total collecté',
                'value' => ContributionFormatter::money($totalCollected),
                'change' => __(':n validés', ['n' => $validatedCount]),
                'changeType' => 'success',
            ],
            [
                'title' => 'Paiements validés',
                'value' => (string) $validatedCount,
                'change' => '—',
                'changeType' => 'success',
            ],
            [
                'title' => 'Paiements en attente',
                'value' => (string) $pendingCount,
                'change' => '—',
                'changeType' => 'warning',
            ],
            [
                'title' => 'Paiements refusés',
                'value' => (string) $rejectedCount,
                'change' => '—',
                'changeType' => 'danger',
            ],
            [
                'title' => 'Membres en retard',
                'value' => (string) $lateMembers,
                'change' => '—',
                'changeType' => 'danger',
            ],
        ];
    }

    private function indexPermissions($user): array
    {
        return [
            'create' => $user->can('submit contribution payments'),
            'managePlans' => ContributionAccess::canManagePlans($user),
            'validate' => ContributionAccess::canValidate($user),
            'reject' => ContributionAccess::canReject($user),
            'edit' => $user->can('edit contributions'),
            'delete' => $user->can('delete contributions'),
        ];
    }

    private function paymentPermissions(ContributionPayment $payment, $user): array
    {
        $canAct = ContributionAccess::isValidatable($payment->status);

        return [
            'edit' => ContributionAccess::canModifyPayment($user, $payment),
            'validate' => ContributionAccess::canValidate($user) && $canAct,
            'reject' => ContributionAccess::canReject($user) && $canAct,
            'delete' => $user->can('delete contributions'),
        ];
    }

    private function authorizePaymentView(ContributionPayment $payment): void
    {
        $user = auth()->user();

        if (ContributionAccess::canViewAll($user)) {
            return;
        }

        abort_unless(
            ContributionAccess::canViewOwn($user) && $payment->user_id === $user->id,
            403,
        );
    }

    private function buildPeriodLabel(?int $month, ?int $year): ?string
    {
        if (! $month || ! $year) {
            return null;
        }

        return ContributionFormatter::periodLabel($month, $year);
    }

    private function storeProof(ContributionPayment $payment, $file, int $uploadedBy): void
    {
        $path = $file->store('contribution-proofs', 'local');
        $originalName = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $file->getClientOriginalName()));

        PaymentProof::create([
            'contribution_payment_id' => $payment->id,
            'original_name' => $originalName,
            'file_name' => basename($path),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    private function availableYears(): array
    {
        $years = ContributionPayment::query()
            ->whereNotNull('payment_year')
            ->distinct()
            ->orderByDesc('payment_year')
            ->pluck('payment_year')
            ->all();

        if (empty($years)) {
            return [now()->year];
        }

        return $years;
    }

    private function logPaymentActivity(ContributionPayment $payment, string $event, string $description, Request $request): void
    {
        activity('contributions')
            ->performedOn($payment)
            ->causedBy($request->user())
            ->event($event)
            ->withProperties([
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status->value,
                'member' => $payment->user?->name,
            ])
            ->log($description);
    }

    /** Plan, mois et libellé de période selon le type de versement (trimestriel, volontaire, ancien plan). */
    private function periodAttributes(Request $request): array
    {
        if ($request->input('kind') === 'quarterly') {
            $quarter = (int) $request->input('quarter');
            $year = (int) $request->input('payment_year');

            return [
                'contribution_plan_id' => ContributionRules::plan()?->id, 'payment_month' => ContributionRules::firstMonth($quarter),
                'payment_year' => $year, 'payment_period_label' => ContributionRules::periodLabel($year, $quarter),
            ];
        }
        if ($request->input('kind') === 'voluntary') {
            return [
                'contribution_plan_id' => null, 'payment_month' => $request->payment_month, 'payment_year' => $request->payment_year ?: now()->year,
                'payment_period_label' => 'Contribution volontaire',
            ];
        }

        return [
            'contribution_plan_id' => $request->contribution_plan_id, 'payment_month' => $request->payment_month,
            'payment_year' => $request->payment_year, 'payment_period_label' => $this->buildPeriodLabel($request->payment_month, $request->payment_year),
        ];
    }

    /** Section 4.2 : le trésorier de zone confirme la réception sous 2 jours ouvrés. */
    private function confirmationInfo(ContributionPayment $payment): array
    {
        if ($payment->status !== ContributionPaymentStatus::Pending || ! $payment->submitted_at) {
            return ['confirmation_deadline' => null, 'confirmation_overdue' => false];
        }
        $deadline = ContributionRules::confirmationDeadline(CarbonImmutable::parse($payment->submitted_at));

        return ['confirmation_deadline' => $deadline->format('d/m/Y'), 'confirmation_overdue' => now()->gt($deadline)];
    }

    private function notifyZoneTreasurers(ContributionPayment $payment): void
    {
        $payment->loadMissing('user.memberProfile.zone');
        if (! $payment->user) return;
        $deadline = ContributionRules::confirmationDeadline(CarbonImmutable::parse($payment->submitted_at ?? now()));
        Notifier::send(
            ContributionRules::zoneTreasurers($payment->user)->reject(fn ($t) => $t->id === $payment->user_id),
            new FundNotification(
                ['Cotisation à confirmer : :name', ['name' => $payment->user->name]],
                [
                    [':name a déclaré une cotisation de :amount (:period).', ['name' => $payment->user->name, 'amount' => ContributionFormatter::money($payment->amount, $payment->currency), 'period' => $payment->payment_period_label ?? '—']],
                    ['Merci de confirmer la réception avant le :date (section 4.2 du règlement).', ['date' => $deadline->format('d/m/Y')]],
                ],
                route('contributions.show', $payment),
                'Vérifier la cotisation',
                'contributions',
            ),
        );
    }

    /** Échéances trimestrielles ouvertes (pour afficher la pénalité reportée dans le formulaire). */
    private function quarterDuesFor(?User $only): array
    {
        $plan = ContributionRules::plan();
        if (! $plan) return [];

        return ContributionDue::query()->where('contribution_plan_id', $plan->id)
            ->when($only, fn ($q) => $q->where('user_id', $only->id))
            ->whereIn('status', ['pending', 'late'])->get(['user_id', 'period_key', 'carried_penalty', 'penalty_waived', 'status'])
            ->groupBy('user_id')->map(fn ($dues) => $dues->mapWithKeys(fn ($d) => [$d->period_key => [
                'carried_penalty' => (float) $d->carried_penalty, 'penalty_waived' => (bool) $d->penalty_waived, 'status' => $d->status->value,
            ]]))->all();
    }

    private function treasurersByMember(?User $only): array
    {
        $users = $only ? collect([$only->loadMissing('memberProfile.zone')]) : User::whereHas('memberProfile')->with('memberProfile.zone')->get();

        return $users->mapWithKeys(fn (User $u) => [$u->id => [
            'zone' => $u->memberProfile?->zone?->name,
            'treasurers' => ContributionRules::zoneTreasurers($u)->pluck('name')->values()->all(),
        ]])->all();
    }

    /**
     * Registre trimestriel (section 2.2) : pour chaque membre, l'état de chacun des 4 trimestres de l'année.
     * Un membre ne voit que sa propre ligne.
     */
    private function quarterRegister(User $viewer, int $year): array
    {
        $plan = ContributionRules::plan();
        $members = User::query()->whereHas('memberProfile')->with('memberProfile.zone')
            ->when(! ContributionAccess::canViewAll($viewer), fn ($q) => $q->whereKey($viewer->id))
            ->orderBy('name')->get();
        $dues = $plan ? ContributionDue::query()->with('payment')->where('contribution_plan_id', $plan->id)
            ->where('period_key', 'like', $year.'-Q%')->whereIn('user_id', $members->pluck('id'))->get()
            ->groupBy('user_id') : collect();
        $payments = ContributionPayment::query()->where('status', ContributionPaymentStatus::Pending)
            ->where('contribution_plan_id', $plan?->id)->where('payment_year', $year)->whereIn('user_id', $members->pluck('id'))
            ->get(['user_id', 'payment_month'])->groupBy('user_id');
        // Paiements validés hors contribution trimestrielle (anciens plans mensuels) : ils couvrent leur trimestre.
        $legacy = ContributionPayment::query()->where('status', ContributionPaymentStatus::Validated)
            ->where(fn ($q) => $q->whereNull('contribution_plan_id')->orWhere('contribution_plan_id', '!=', $plan?->id))
            ->where('payment_year', $year)->whereNotNull('payment_month')->whereIn('user_id', $members->pluck('id'))
            ->get(['user_id', 'payment_month', 'amount', 'payment_period_label'])
            ->reject(fn ($p) => $p->payment_period_label === 'Contribution volontaire')->groupBy('user_id');
        $today = CarbonImmutable::today();

        $rows = $members->map(function (User $member) use ($dues, $payments, $legacy, $year, $today) {
            $memberDues = ($dues[$member->id] ?? collect())->keyBy('period_key');
            $pendingQuarters = ($payments[$member->id] ?? collect())->map(fn ($p) => ContributionRules::quarterOfMonth($p->payment_month))->all();
            $legacyByQuarter = ($legacy[$member->id] ?? collect())->groupBy(fn ($p) => ContributionRules::quarterOfMonth($p->payment_month));
            $quarters = [];
            $paid = 0.0;
            $penalties = 0.0;
            foreach (range(1, 4) as $q) {
                $due = $memberDues[ContributionRules::periodKey($year, $q)] ?? null;
                $dueDate = ContributionRules::dueDate($year, $q);
                $penalty = $due && ! $due->penalty_waived ? (float) $due->penalty_amount : 0.0;
                $state = match (true) {
                    $due?->status?->value === 'paid' => $penalty > 0 ? 'paid_late' : 'paid',
                    $due?->status?->value === 'waived' => 'waived',
                    in_array($q, $pendingQuarters, true) => 'submitted',
                    $due === null && $legacyByQuarter->has($q) => 'paid',
                    $due?->status?->value === 'late' || ($due && $today->gt($dueDate)) => 'late',
                    $due !== null => 'due',
                    $today->lt($dueDate->startOfQuarter()) => 'upcoming',
                    default => 'none',
                };
                if ($due?->status?->value === 'paid') $paid += (float) ($due->payment?->amount ?? $due->amount);
                elseif ($due === null && $legacyByQuarter->has($q)) $paid += (float) $legacyByQuarter[$q]->sum('amount');
                if (in_array($due?->status?->value, ['pending', 'late'], true)) $penalties += $penalty;
                $quarters[] = [
                    'quarter' => $q, 'state' => $state, 'due_date' => $dueDate->format('d/m/Y'),
                    'amount' => $due ? ContributionFormatter::money($due->amount, $due->currency) : null,
                    'penalty' => $penalty > 0 ? ContributionFormatter::money($penalty) : null,
                    'paid_at' => $due?->paid_at?->format('d/m/Y'),
                ];
            }

            return [
                'id' => $member->id, 'name' => $member->name, 'zone' => $member->memberProfile?->zone?->name,
                'quarters' => $quarters, 'paid_total' => ContributionFormatter::money($paid), 'paid_raw' => $paid,
                'penalties' => $penalties > 0 ? ContributionFormatter::money($penalties) : null,
                'late_count' => collect($quarters)->where('state', 'late')->count(),
            ];
        })->values();

        $years = ContributionDue::query()->where('contribution_plan_id', $plan?->id)->pluck('period_key')
            ->map(fn ($k) => (int) substr($k, 0, 4))->push(now()->year, $year)->unique()->sortDesc()->values();

        return ['year' => $year, 'years' => $years, 'rows' => $rows, 'own_only' => ! ContributionAccess::canViewAll($viewer)];
    }
}
