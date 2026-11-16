<?php

namespace App\Http\Controllers;

use App\Enums\ProjectSector;
use App\Enums\ProjectStatus;
use App\Http\Requests\ApproveProjectRequest;
use App\Http\Requests\EvaluateProjectRequest;
use App\Http\Requests\FundProjectRequest;
use App\Http\Requests\RejectProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\SubmitProjectRequest;
use App\Http\Requests\SubmitProjectToVoteRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Country;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Models\ApprovalRequest;
use App\Models\ProjectCriterion;
use App\Models\ProjectType;
use App\Models\Vote;
use App\Services\EligibilityService;
use App\Services\LoanService;
use App\Services\ProjectReviewService;
use App\Services\ProjectService;
use App\Support\FundRules;
use App\Support\ProjectAccess;
use App\Support\ProjectFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projectService) {}

    public function index(Request $request): Response
    {
        abort_unless(ProjectAccess::canView($request->user()), 403);

        $user = $request->user();

        $query = Project::query()
            ->with(['owner', 'country', 'evaluations', 'type', 'fundAccount'])
            ->when(! ProjectAccess::canViewAll($user), function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('owner_id', $user->id)
                        ->orWhereIn('status', array_map(
                            fn (ProjectStatus $s) => $s->value,
                            array_filter(ProjectStatus::cases(), fn ($s) => ProjectAccess::isPublicStatus($s)),
                        ));
                });
            })
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhereHas('owner', fn ($o) => $o->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->sector, fn ($q, $sector) => $q->where('sector', $sector))
            ->when($request->project_type_id, fn ($q, $type) => $q->where('project_type_id', $type))
            ->when($request->fund_account_id, fn ($q, $account) => $q->where('fund_account_id', $account))
            ->when($request->country_id, fn ($q, $countryId) => $q->where('country_id', $countryId))
            ->when($request->owner_id, fn ($q, $ownerId) => $q->where('owner_id', $ownerId))
            ->latest('submitted_at')
            ->latest('created_at');

        $projects = $query->paginate($request->integer('per_page', 10))->withQueryString();
        $projects->getCollection()->transform(fn (Project $p) => $this->formatProject($p));

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['search', 'status', 'sector', 'country_id', 'owner_id', 'per_page', 'project_type_id', 'fund_account_id']),
            'projectTypes' => ProjectType::orderBy('name')->get(['id', 'name']),
            'fundAccounts' => \App\Models\FundAccount::orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'stats' => $this->indexStats($user),
            'countries' => Country::forDisplay(Country::where('is_active', true)->orderBy('name')->get(['id', 'name', 'flag_emoji'])),
            'sectors' => ProjectSector::options(),
            'statuses' => ProjectStatus::options(),
            'owners' => $this->ownerOptions($user),
            'can' => $this->permissions($user),
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()->can('create projects'), 403);

        return Inertia::render('Projects/Create', $this->formOptions());
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $user = $request->user();

        $project = Project::create([
            ...$this->projectAttributes($request),
            'owner_id' => $user->id,
            'status' => ProjectStatus::Draft,
            'created_by' => $user->id,
        ]);

        activity('projects')
            ->performedOn($project)
            ->causedBy($user)
            ->event('project.created')
            ->log('Projet créé');

        $this->storeAttachments($request, $project);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Projet créé avec succès.');
    }

    public function show(Project $project): Response
    {
        abort_unless(ProjectAccess::canViewProject(request()->user(), $project), 403);

        $user = request()->user();
        $project->load(['owner', 'country', 'reviewer', 'funder', 'documents.uploader', 'evaluations.evaluator', 'activeVote', 'milestones.creator', 'progressReports.reporter',
            'type', 'criteria.checker', 'installments', 'repayments.recorder', 'resubmissionOf', 'approvalRequests.decisions.user', 'approvalRequests.type', 'votes']);
        $internal = ProjectAccess::canSeeInternal($user, $project);

        if ($project->criteria->isNotEmpty() && in_array($project->status, [ProjectStatus::Submitted, ProjectStatus::UnderReview], true)) {
            app(ProjectReviewService::class)->refreshAutoChecks($project);
            $project->load('criteria.checker');
        }

        return Inertia::render('Projects/Show', [
            'project' => $this->formatProject($project, detailed: true, internal: $internal),
            'activities' => $this->history($project, $internal),
            'can' => $this->showPermissions($user, $project),
            'statuses' => ProjectStatus::options(),
            'situation' => ($internal || $project->owner_id === $user->id) && $project->owner
                ? app(EligibilityService::class)->situation($project->owner, $project) : null,
            'checks' => $internal ? app(EligibilityService::class)->checks($project) : null,
            'criterionStatuses' => collect(ProjectCriterion::STATUSES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'decisions' => collect(ProjectReviewService::DECISIONS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'loan' => $this->loanData($project, $user),
            'approvals' => $internal ? $this->approvalsData($project, $user) : [],
            'voteDefaults' => [
                'opens_at' => now()->timezone('Europe/Paris')->format('Y-m-d\\TH:i'),
                'closes_at' => now()->addDays(FundRules::get('vote_duration_days'))->timezone('Europe/Paris')->format('Y-m-d\\TH:i'),
                'threshold' => FundRules::get('vote_approval_percent'),
            ],
            'disbursementDefaults' => [
                'amount' => (float) $project->requested_amount,
                'disbursed_on' => now()->toDateString(),
                'duration_months' => $project->loan_duration_months ?? FundRules::get('loan_max_duration_months'),
                'first_due_date' => now()->addMonthNoOverflow()->toDateString(),
                'rate' => FundRules::get('loan_annual_interest_rate'),
                'max_duration' => FundRules::get('loan_max_duration_months'),
                'fund_cap' => app(EligibilityService::class)->fundCap(),
            ],
        ]);
    }

    /** Historique complet du dossier. Le demandeur ne voit pas les étapes internes du comité et des trésoriers. */
    private function history(Project $project, bool $internal): array
    {
        $approvalIds = $project->approvalRequests->pluck('id');
        $voteIds = $project->votes->pluck('id');
        $internalEvents = ['project.evaluated', 'project.document.uploaded.internal'];

        return Activity::query()->with('causer')
            ->where(function ($q) use ($project, $approvalIds, $voteIds, $internal) {
                $q->where(fn ($sub) => $sub->where('subject_type', $project->getMorphClass())->where('subject_id', $project->id));
                if ($internal) {
                    $q->orWhere(fn ($sub) => $sub->where('subject_type', (new ApprovalRequest)->getMorphClass())->whereIn('subject_id', $approvalIds));
                }
                $q->orWhere(fn ($sub) => $sub->where('subject_type', (new Vote)->getMorphClass())->whereIn('subject_id', $voteIds)
                    ->whereIn('event', ['vote.created', 'vote.opened', 'vote.closed', 'vote.rescheduled', 'vote.cancelled']));
            })
            ->latest()->limit(300)->get()
            ->reject(fn (Activity $a) => ! $internal && (in_array($a->event, $internalEvents, true) || ($a->properties['visibility'] ?? null) === 'committee'))
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'created_at' => $activity->created_at?->timezone('Europe/Paris')->format('d/m/Y H:i'),
                'causer' => $internal || $activity->causer_id === $project->owner_id ? $activity->causer?->name : null,
            ])->values()->all();
    }

    private function loanData(Project $project, User $user): ?array
    {
        if ($project->installments->isEmpty() && $project->repayments->isEmpty()) {
            return null;
        }
        $outstanding = round($project->installments->sum(fn ($i) => $i->remaining() + $i->remainingPenalty()), 2);

        return [
            'rate' => (float) $project->loan_interest_rate,
            'total_due' => ProjectFormatter::money($project->loan_total_due),
            'outstanding' => ProjectFormatter::money($outstanding),
            'outstanding_raw' => $outstanding,
            'paid' => ProjectFormatter::money($project->repayments->sum('amount')),
            'penalties' => ProjectFormatter::money($project->installments->sum(fn ($i) => $i->remainingPenalty())),
            'repaid_at' => $project->repaid_at?->format('d/m/Y'),
            'installments' => $project->installments->map(fn ($i) => [
                'id' => $i->id, 'number' => $i->number, 'due_date' => $i->due_date->format('d/m/Y'),
                'principal' => ProjectFormatter::money($i->principal), 'interest' => ProjectFormatter::money($i->interest),
                'amount' => ProjectFormatter::money($i->amount), 'paid' => ProjectFormatter::money($i->paid_amount),
                'remaining' => ProjectFormatter::money($i->remaining()), 'penalty' => ProjectFormatter::money($i->remainingPenalty()), 'penalty_raw' => $i->remainingPenalty(),
                'status' => $i->status, 'status_label' => \App\Models\LoanInstallment::STATUSES[$i->status] ?? $i->status,
                'reminder_level' => $i->reminder_level,
            ])->values(),
            'repayments' => $project->repayments->map(fn ($r) => [
                'id' => $r->id, 'amount' => ProjectFormatter::money($r->amount), 'paid_on' => $r->paid_on->format('d/m/Y'),
                'recorded_by' => $r->recorder?->name, 'note' => $r->note,
            ])->values(),
        ];
    }

    private function approvalsData(Project $project, User $user): array
    {
        $service = app(\App\Services\ApprovalService::class);

        return $project->approvalRequests->map(fn (ApprovalRequest $r) => [
            'id' => $r->id, 'title' => $r->title, 'type' => $r->type?->name, 'amount' => ProjectFormatter::money($r->amount),
            'status' => $r->status, 'status_label' => ApprovalRequest::STATUSES[$r->status] ?? $r->status,
            'approvals' => $r->decisions->where('decision', 'approve')->count(), 'required' => $r->required_approvals,
            'decisions' => $r->decisions->map(fn ($d) => ['user' => $d->user?->name, 'decision' => $d->decision, 'comment' => $d->comment, 'date' => $d->created_at?->timezone('Europe/Paris')->format('d/m/Y H:i')])->values(),
            'can_approve' => $service->canApprove($user, $r),
            'failure_reason' => $r->failure_reason, 'rejected_reason' => $r->rejected_reason,
            'created_at' => $r->created_at?->timezone('Europe/Paris')->format('d/m/Y H:i'),
        ])->values()->all();
    }

    public function checkCriterion(Request $request, ProjectCriterion $projectCriterion): RedirectResponse
    {
        abort_unless(ProjectAccess::canReview($request->user(), $projectCriterion->project), 403);
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(ProjectCriterion::STATUSES))],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            app(ProjectReviewService::class)->checkCriterion($projectCriterion, $request->user(), $data['status'], $data['comment'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['criteria' => $e->getMessage()]);
        }

        return back()->with('success', 'Critère enregistré.');
    }

    public function decide(Request $request, Project $project): RedirectResponse
    {
        abort_unless(ProjectAccess::canReview($request->user(), $project), 403);
        $data = $request->validate([
            'decision' => ['required', 'in:'.implode(',', array_keys(ProjectReviewService::DECISIONS))],
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
        ], ['reason.required' => 'Le motif de la décision est obligatoire.', 'reason.min' => 'Le motif doit faire au moins 5 caractères.']);
        try {
            app(ProjectReviewService::class)->decide($project, $request->user(), $data['decision'], $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('success', 'Décision enregistrée et envoyée au demandeur.');
    }

    public function prepareDisbursement(Request $request, Project $project): RedirectResponse
    {
        abort_unless(ProjectAccess::canFund($request->user(), $project), 403);

        return $this->disburse($request, $project);
    }

    private function disburse(Request $request, Project $project): RedirectResponse
    {
        abort_if($project->owner_id === $request->user()->id, 403);
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1'],
            'disbursed_on' => ['nullable', 'date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:'.FundRules::get('loan_max_duration_months')],
            'first_due_date' => ['nullable', 'date'],
        ]);
        try {
            $result = app(LoanService::class)->prepareDisbursement($project, $request->user(), $data);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['fund' => $e->getMessage()]);
        }

        return redirect()->route('projects.show', $project)->with('success', $result->status === 'executed'
            ? 'Décaissement validé et effectué.'
            : 'Décaissement préparé : il sera effectué après '.$result->required_approvals.' validation(s).');
    }

    public function recordRepayment(Request $request, Project $project): RedirectResponse
    {
        abort_unless(ProjectAccess::canRecordRepayment($request->user(), $project), 403);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $result = app(LoanService::class)->requestRepayment($project, $request->user(), (float) $data['amount'], $data['paid_on'], $data['note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['repayment' => $e->getMessage()]);
        }

        return back()->with('success', $result->status === 'executed' ? 'Remboursement enregistré.' : 'Remboursement soumis à validation.');
    }

    public function edit(Project $project): Response
    {
        abort_unless(ProjectAccess::canModify(request()->user(), $project), 403);

        return Inertia::render('Projects/Edit', [
            ...$this->formOptions(),
            'project' => $this->formatProject($project, detailed: true),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $user = $request->user();

        $project->update([
            ...$this->projectAttributes($request),
            'updated_by' => $user->id,
        ]);

        if ($project->wasChanged('title')) {
            $project->update(['slug' => Project::generateUniqueSlug($project->title, $project->id)]);
        }

        activity('projects')
            ->performedOn($project)
            ->causedBy($user)
            ->event('project.updated')
            ->log('Projet mis à jour');

        $this->storeAttachments($request, $project);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Projet mis à jour avec succès.');
    }

    public function submit(SubmitProjectRequest $request, Project $project): RedirectResponse
    {
        try {
            $this->projectService->submit($project);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['submit' => $e->getMessage()]);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Projet soumis pour analyse.');
    }

    public function evaluate(EvaluateProjectRequest $request, Project $project): RedirectResponse
    {
        try {
            $this->projectService->evaluate($project, $request->user(), $request->validated());
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['evaluate' => $e->getMessage()]);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Évaluation enregistrée.');
    }

    public function approveByCommittee(ApproveProjectRequest $request, Project $project): RedirectResponse
    {
        try {
            $this->projectService->approveByCommittee(
                $project,
                $request->user(),
                $request->input('committee_decision_comment'),
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['approve' => $e->getMessage()]);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Projet approuvé par le comité.');
    }

    public function rejectByCommittee(RejectProjectRequest $request, Project $project): RedirectResponse
    {
        try {
            $this->projectService->rejectByCommittee(
                $project,
                $request->user(),
                $request->input('rejected_reason'),
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['reject' => $e->getMessage()]);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Projet rejeté par le comité.');
    }

    public function submitToVote(SubmitProjectToVoteRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
        ]);
        try {
            $project = $this->projectService->submitToVote($project, $request->user(), $data['opens_at'] ?? null, $data['closes_at'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['submit_to_vote' => $e->getMessage()]);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', $project->activeVote?->status === \App\Enums\VoteStatus::Scheduled ? 'Vote des membres programmé.' : 'Projet soumis au vote familial.');
    }

    public function fund(FundProjectRequest $request, Project $project): RedirectResponse
    {
        return $this->disburse($request, $project);
    }

    public function start(Request $request, Project $project): RedirectResponse
    {
        abort_unless(ProjectAccess::canManageExecution($request->user(), $project), 403);
        try { $this->projectService->start($project, $request->user()); }
        catch (\RuntimeException $e) { return back()->withErrors(['execution' => $e->getMessage()]); }
        return back()->with('success', 'Exécution du projet démarrée.');
    }

    public function complete(Request $request, Project $project): RedirectResponse
    {
        abort_unless(ProjectAccess::canManageExecution($request->user(), $project), 403);
        $data = $request->validate(['closure_note' => ['required', 'string', 'min:5', 'max:5000']]);
        try { $this->projectService->complete($project, $request->user(), $data['closure_note']); }
        catch (\RuntimeException $e) { return back()->withErrors(['execution' => $e->getMessage()]); }
        return back()->with('success', 'Projet clôturé avec succès.');
    }

    public function fail(Request $request, Project $project): RedirectResponse
    {
        abort_unless(ProjectAccess::canManageExecution($request->user(), $project), 403);
        $data = $request->validate(['closure_note' => ['required', 'string', 'min:5', 'max:5000']]);
        try { $this->projectService->fail($project, $request->user(), $data['closure_note']); }
        catch (\RuntimeException $e) { return back()->withErrors(['execution' => $e->getMessage()]); }
        return back()->with('success', 'Échec du projet enregistré.');
    }

    public function cancelExecution(Request $request, Project $project): RedirectResponse
    {
        abort_unless(ProjectAccess::canManageExecution($request->user(), $project), 403);
        $data = $request->validate(['closure_note' => ['required', 'string', 'min:5', 'max:5000']]);
        try { $this->projectService->cancel($project, $request->user(), $data['closure_note']); }
        catch (\RuntimeException $e) { return back()->withErrors(['execution' => $e->getMessage()]); }
        return back()->with('success', 'Projet annulé. Toute correction financière doit être enregistrée dans le fonds.');
    }

    public function storeMilestone(Request $request, Project $project): RedirectResponse
    {
        abort_unless(ProjectAccess::canReportProgress($request->user(), $project), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:3000'],
            'due_date' => ['nullable', 'date'], 'planned_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        try { $this->projectService->addMilestone($project, $request->user(), $data); }
        catch (\RuntimeException $e) { return back()->withErrors(['milestone' => $e->getMessage()]); }
        return back()->with('success', 'Jalon ajouté.');
    }

    public function updateMilestone(Request $request, ProjectMilestone $projectMilestone): RedirectResponse
    {
        abort_unless(ProjectAccess::canReportProgress($request->user(), $projectMilestone->project), 403);
        $data = $request->validate([
            'status' => ['required', 'in:pending,in_progress,completed,blocked'],
            'spent_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->projectService->updateMilestone($projectMilestone, $request->user(), $data);
        return back()->with('success', 'Jalon mis à jour.');
    }

    public function storeProgressReport(Request $request, Project $project): RedirectResponse
    {
        abort_unless(ProjectAccess::canReportProgress($request->user(), $project), 403);
        $data = $request->validate([
            'progress_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'spent_amount' => ['required', 'numeric', 'min:0'],
            'summary' => ['required', 'string', 'min:10', 'max:5000'],
            'risks' => ['nullable', 'string', 'max:5000'], 'next_steps' => ['nullable', 'string', 'max:5000'],
            'reported_at' => ['required', 'date', 'before_or_equal:today'],
        ]);
        try { $this->projectService->addProgressReport($project, $request->user(), $data); }
        catch (\RuntimeException $e) { return back()->withErrors(['report' => $e->getMessage()]); }
        return back()->with('success', 'Rapport d’avancement ajouté.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        abort_unless(ProjectAccess::canDelete($request->user()), 403);

        activity('projects')
            ->performedOn($project)
            ->causedBy($request->user())
            ->event('project.deleted')
            ->log('Projet supprimé');

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Projet supprimé.');
    }

    private function projectAttributes(Request $request): array
    {
        return [
            'title' => $request->input('title'),
            'short_description' => $request->input('short_description'),
            'description' => $request->input('description'),
            'country_id' => $request->input('country_id'),
            'project_type_id' => $request->input('project_type_id'),
            'fund_account_id' => $request->input('fund_account_id'),
            'resubmission_of_id' => $request->input('resubmission_of_id'),
            'repayment_plan' => $request->input('repayment_plan'),
            'loan_duration_months' => $request->input('loan_duration_months'),
            'presentation_date' => $request->input('presentation_date'),
            'city' => $request->input('city'),
            'sector' => $request->input('sector'),
            'total_budget' => $request->input('total_budget'),
            'personal_contribution' => $request->input('personal_contribution'),
            'requested_amount' => $request->input('requested_amount'),
            'currency' => strtoupper($request->input('currency', \App\Support\FundCurrency::code())),
            'expected_duration_months' => $request->input('expected_duration_months'),
            'expected_roi_percentage' => $request->input('expected_roi_percentage'),
        ];
    }

    private function formOptions(): array
    {
        return [
            'countries' => Country::forDisplay(Country::where('is_active', true)->orderBy('name')->get(['id', 'name', 'flag_emoji'])),
            'sectors' => ProjectSector::options(),
            'documentTypes' => \App\Enums\ProjectDocumentType::options(),
            'fundAccounts' => \App\Models\FundAccount::query()->where('is_active', true)->where('slug', '!=', 'treasury')->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'description'])->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'description' => $a->description]),
            'projectTypes' => ProjectType::query()->where('is_active', true)->with(['criteria' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get()
                ->map(fn (ProjectType $type) => [
                    'id' => $type->id, 'name' => $type->name, 'description' => $type->description, 'is_loan' => $type->is_loan,
                    'requires_personal_contribution' => $type->requires_personal_contribution,
                    'criteria' => $type->criteria->pluck('label')->values(),
                ]),
            'refusedProjects' => Project::query()->where('owner_id', auth()->id())
                ->whereIn('status', [ProjectStatus::RejectedByCommittee, ProjectStatus::RejectedByFamily])->get(['id', 'title']),
            'loanRules' => [
                'max_duration' => FundRules::get('loan_max_duration_months'),
                'rate' => FundRules::get('loan_annual_interest_rate'),
                'notice_min' => FundRules::get('project_notice_min_days'),
                'notice_max' => FundRules::get('project_notice_max_days'),
                'fund_cap' => ProjectFormatter::money(app(EligibilityService::class)->fundCap()),
                'cap_percent' => FundRules::get('loan_max_balance_percent'),
            ],
        ];
    }

    private function indexStats(User $user): array
    {
        $baseQuery = Project::query()
            ->when(! ProjectAccess::canViewAll($user), fn ($q) => $q->where('owner_id', $user->id));

        $submitted = (clone $baseQuery)->where('status', ProjectStatus::Submitted)->count();
        $underReview = (clone $baseQuery)->where('status', ProjectStatus::UnderReview)->count();
        $approved = (clone $baseQuery)->where('status', ProjectStatus::ApprovedByCommittee)->count();
        $requestedTotal = (clone $baseQuery)->sum('requested_amount');
        $budgetTotal = (clone $baseQuery)->sum('total_budget');

        return [
            ['key' => 'submitted', 'label' => 'Projets soumis', 'value' => (string) $submitted],
            ['key' => 'under_review', 'label' => 'En analyse', 'value' => (string) $underReview],
            ['key' => 'approved', 'label' => 'Approuvés comité', 'value' => (string) $approved],
            ['key' => 'requested', 'label' => 'Montant demandé total', 'value' => ProjectFormatter::money($requestedTotal)],
            ['key' => 'budget', 'label' => 'Budget total cumulé', 'value' => ProjectFormatter::money($budgetTotal)],
        ];
    }

    private function ownerOptions(User $user): array
    {
        if (! ProjectAccess::canViewAll($user)) {
            return [];
        }

        return User::whereHas('roles', fn ($q) => $q->whereIn('name', ['member', 'president', 'treasurer', 'secretary']))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    private function permissions(User $user): array
    {
        return [
            'create' => $user->can('create projects'),
            'delete' => $user->can('delete projects'),
            'viewAll' => ProjectAccess::canViewAll($user),
        ];
    }

    private function showPermissions(User $user, Project $project): array
    {
        return [
            'edit' => ProjectAccess::canModify($user, $project),
            'submit' => ProjectAccess::canSubmit($user, $project),
            'evaluate' => ProjectAccess::canEvaluate($user) && $project->owner_id !== $user->id
                && in_array($project->status, [ProjectStatus::Submitted, ProjectStatus::UnderReview], true),
            'approve' => ProjectAccess::canApprove($user)
                && in_array($project->status, [ProjectStatus::Submitted, ProjectStatus::UnderReview], true),
            'reject' => ProjectAccess::canReject($user)
                && in_array($project->status, [ProjectStatus::Submitted, ProjectStatus::UnderReview], true),
            'submitToVote' => ProjectAccess::canSubmitToVote($user)
                && ($project->status === ProjectStatus::ApprovedByCommittee
                    || ($project->status === ProjectStatus::SubmittedToVote && ! $project->activeVote)),
            'fund' => ProjectAccess::canFund($user, $project)
                && ! $project->approvalRequests->contains(fn ($r) => $r->status === 'pending' && $r->type?->code === 'loan_disbursement'),
            'review' => ProjectAccess::canReview($user, $project),
            'recordRepayment' => ProjectAccess::canRecordRepayment($user, $project),
            'seeInternal' => ProjectAccess::canSeeInternal($user, $project),
            'rescheduleVote' => ProjectAccess::canSubmitToVote($user) && in_array($project->activeVote?->status?->value, ['scheduled', 'open'], true),
            'start' => ProjectAccess::canManageExecution($user, $project) && $project->status === ProjectStatus::Funded,
            'manageExecution' => ProjectAccess::canManageExecution($user, $project) && in_array($project->status, [ProjectStatus::Funded, ProjectStatus::InProgress], true),
            'reportProgress' => ProjectAccess::canReportProgress($user, $project) && $project->status === ProjectStatus::InProgress,
            'delete' => ProjectAccess::canDelete($user),
            'uploadDocument' => ProjectAccess::canUploadDocument($user, $project),
            'downloadDocument' => ProjectAccess::canDownloadDocument($user, $project),
        ];
    }

    private function formatProject(Project $project, bool $detailed = false, bool $internal = true): array
    {
        $data = [
            'id' => $project->id,
            'title' => $project->title,
            'slug' => $project->slug,
            'short_description' => $project->short_description,
            'owner' => $project->owner?->name ?? '—',
            'owner_id' => $project->owner_id,
            'sector' => $project->sector?->label() ?? '—',
            'sector_value' => $project->sector?->value,
            'sector_icon' => $project->sector?->icon(),
            'country' => $project->country?->name ?? '—',
            'country_id' => $project->country_id,
            'country_flag' => $project->country?->flag_emoji,
            'city' => $project->city,
            'total_budget' => ProjectFormatter::money($project->total_budget, $project->currency),
            'total_budget_raw' => (float) $project->total_budget,
            'personal_contribution' => ProjectFormatter::money($project->personal_contribution, $project->currency),
            'personal_contribution_raw' => (float) $project->personal_contribution,
            'requested_amount' => ProjectFormatter::money($project->requested_amount, $project->currency),
            'requested_amount_raw' => (float) $project->requested_amount,
            'currency' => $project->currency,
            'personal_percent' => $project->personalContributionPercent(),
            'requested_percent' => $project->requestedAmountPercent(),
            'respects_fifty_fifty' => $project->respectsFiftyFiftyRule(),
            'status' => $project->status?->value,
            'status_label' => $project->status?->label(),
            'status_variant' => $project->status?->color(),
            'committee_score' => $project->averageCommitteeScore(),
            'submitted_at' => $project->submitted_at?->format('d/m/Y'),
            'type' => $project->type ? ['id' => $project->type->id, 'name' => $project->type->name, 'is_loan' => $project->type->is_loan, 'requires_personal_contribution' => $project->type->requires_personal_contribution] : null,
            'project_type_id' => $project->project_type_id,
            'fund_account_id' => $project->fund_account_id,
            'fund_account' => $project->fundAccount?->name,
            'is_example' => (bool) $project->is_example,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'description' => $project->description,
                'repayment_plan' => $project->repayment_plan,
                'loan_duration_months' => $project->loan_duration_months,
                'presentation_date' => $project->presentation_date?->format('d/m/Y'),
                'presentation_date_raw' => $project->presentation_date?->format('Y-m-d'),
                'resubmission_of_id' => $project->resubmission_of_id,
                'resubmission_of' => $project->resubmissionOf?->title,
                'committee_decision' => $project->committee_decision,
                'information_request' => $project->information_request,
                'decided_at' => $project->decided_at?->format('d/m/Y H:i'),
                'criteria' => $project->criteria->map(fn (ProjectCriterion $c) => [
                    'id' => $c->id, 'label' => $c->label, 'description' => $c->description, 'is_blocking' => $c->is_blocking,
                    'auto_check' => $c->auto_check, 'auto_result' => $c->auto_result, 'auto_detail' => $c->auto_detail,
                    'status' => $c->status, 'status_label' => ProjectCriterion::STATUSES[$c->status] ?? $c->status,
                    'comment' => $c->comment, 'checked_by' => $internal ? $c->checker?->name : null,
                    'checked_at' => $c->checked_at?->timezone('Europe/Paris')->format('d/m/Y H:i'),
                ])->values(),
                'active_vote_status' => $project->activeVote?->status?->value,
                'active_vote_opens_at' => $project->activeVote?->opens_at?->timezone('Europe/Paris')->format('Y-m-d\\TH:i'),
                'active_vote_closes_at' => $project->activeVote?->closes_at?->timezone('Europe/Paris')->format('Y-m-d\\TH:i'),
                'expected_duration_months' => $project->expected_duration_months,
                'expected_roi_percentage' => $project->expected_roi_percentage,
                'submitted_at_full' => $project->submitted_at?->format('d/m/Y H:i'),
                'reviewed_at' => $project->reviewed_at?->format('d/m/Y H:i'),
                'reviewed_by' => $project->reviewer?->name,
                'committee_decision_comment' => $project->committee_decision_comment,
                'rejected_reason' => $project->rejected_reason,
                'submitted_to_vote_at' => $project->submitted_to_vote_at?->format('d/m/Y H:i'),
                'funded_at' => $project->funded_at?->format('d/m/Y H:i'),
                'funded_by' => $project->funder?->name,
                'funded_amount' => $project->funded_amount !== null
                    ? ProjectFormatter::money($project->funded_amount, $project->currency)
                    : null,
                'documents' => $project->documents->filter(fn ($doc) => $internal || $doc->visibility !== 'committee')->values()->map(fn ($doc) => [
                    'visibility' => $doc->visibility ?? 'all',
                    'id' => $doc->id,
                    'type' => $doc->type?->label(),
                    'type_value' => $doc->type?->value,
                    'title' => $doc->title ?? $doc->original_name,
                    'original_name' => $doc->original_name,
                    'file_size' => $doc->file_size,
                    'uploaded_by' => $doc->uploader?->name,
                    'created_at' => $doc->created_at?->format('d/m/Y H:i'),
                    'download_url' => route('projects.documents.download', $doc),
                ]),
                'evaluations' => $project->evaluations->map(fn ($eval) => [
                    'id' => $eval->id,
                    'evaluator' => $eval->evaluator?->name,
                    'profitability_score' => $eval->profitability_score,
                    'risk_score' => $eval->risk_score,
                    'founder_score' => $eval->founder_score,
                    'family_impact_score' => $eval->family_impact_score,
                    'sustainability_score' => $eval->sustainability_score,
                    'values_alignment_score' => $eval->values_alignment_score,
                    'total_score' => $eval->total_score,
                    'recommendation' => $eval->recommendation,
                    'comment' => $eval->comment,
                    'evaluated_at' => $eval->evaluated_at?->format('d/m/Y H:i'),
                ]),
                'timeline' => $this->buildTimeline($project),
                'active_vote_id' => $project->activeVote?->id,
                'active_vote_title' => $project->activeVote?->title,
                'progress_percent' => (int) $project->progress_percent,
                'started_at' => $project->started_at?->format('d/m/Y H:i'),
                'completed_at' => $project->completed_at?->format('d/m/Y H:i'),
                'failed_at' => $project->failed_at?->format('d/m/Y H:i'),
                'cancelled_at' => $project->cancelled_at?->format('d/m/Y H:i'),
                'closure_note' => $project->closure_note,
                'milestones' => $project->milestones->map(fn ($milestone) => [
                    'id' => $milestone->id, 'title' => $milestone->title, 'description' => $milestone->description,
                    'due_date' => $milestone->due_date?->format('d/m/Y'), 'status' => $milestone->status->value,
                    'status_label' => $milestone->status->label(), 'planned_amount' => $milestone->planned_amount,
                    'spent_amount' => $milestone->spent_amount, 'completed_at' => $milestone->completed_at?->format('d/m/Y H:i'),
                ]),
                'progress_reports' => $project->progressReports->map(fn ($report) => [
                    'id' => $report->id, 'progress_percent' => $report->progress_percent, 'spent_amount' => $report->spent_amount,
                    'summary' => $report->summary, 'risks' => $report->risks, 'next_steps' => $report->next_steps,
                    'reported_at' => $report->reported_at?->format('d/m/Y'), 'reported_by' => $report->reporter?->name,
                ]),
            ]);
        }

        return $data;
    }

    private function buildTimeline(Project $project): array
    {
        $timeline = [];

        if ($project->created_at) {
            $timeline[] = ['label' => 'Création', 'date' => $project->created_at->format('d/m/Y H:i'), 'status' => 'done'];
        }
        if ($project->submitted_at) {
            $timeline[] = ['label' => 'Soumission', 'date' => $project->submitted_at->format('d/m/Y H:i'), 'status' => 'done'];
        }
        if ($project->status === ProjectStatus::UnderReview || $project->evaluations->isNotEmpty()) {
            $timeline[] = ['label' => 'Analyse comité', 'date' => $project->evaluations->first()?->evaluated_at?->format('d/m/Y H:i') ?? '—', 'status' => 'current'];
        }
        if ($project->reviewed_at) {
            $label = $project->status === ProjectStatus::RejectedByCommittee ? 'Rejet comité' : 'Décision comité';
            $timeline[] = ['label' => $label, 'date' => $project->reviewed_at->format('d/m/Y H:i'), 'status' => 'done'];
        }
        if ($project->submitted_to_vote_at) {
            $timeline[] = ['label' => 'Soumis au vote', 'date' => $project->submitted_to_vote_at->format('d/m/Y H:i'), 'status' => 'done'];
        }
        if ($project->status === ProjectStatus::ApprovedByFamily || $project->funded_at) {
            $timeline[] = [
                'label' => 'Approuvé famille',
                'date' => $project->funded_at?->format('d/m/Y H:i')
                    ?? ($project->status === ProjectStatus::ApprovedByFamily ? now()->format('d/m/Y H:i') : '—'),
                'status' => 'done',
            ];
        }
        if ($project->status === ProjectStatus::RejectedByFamily) {
            $timeline[] = ['label' => 'Rejeté famille', 'date' => now()->format('d/m/Y H:i'), 'status' => 'done'];
        }
        if ($project->funded_at) {
            $timeline[] = ['label' => 'Financé', 'date' => $project->funded_at->format('d/m/Y H:i'), 'status' => 'done'];
        }
        if ($project->started_at) {
            $timeline[] = ['label' => 'Exécution démarrée', 'date' => $project->started_at->format('d/m/Y H:i'), 'status' => 'done'];
        }
        foreach ([['completed_at', 'Projet terminé'], ['failed_at', 'Projet en échec'], ['cancelled_at', 'Projet annulé']] as [$field, $label]) {
            if ($project->{$field}) $timeline[] = ['label' => $label, 'date' => $project->{$field}->format('d/m/Y H:i'), 'status' => 'done'];
        }

        return $timeline;
    }

    /** Enregistre les pièces jointes du formulaire (business plan, devis, pièce d'identité…). */
    private function storeAttachments(\Illuminate\Http\Request $request, Project $project): void
    {
        foreach ((array) $request->input('attachments', []) as $index => $attachment) {
            $file = $request->file("attachments.{$index}.file");
            if (! $file) continue;
            $fileName = \Illuminate\Support\Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension());
            $document = \App\Models\ProjectDocument::create([
                'project_id' => $project->id,
                'type' => $attachment['type'] ?? 'other',
                'visibility' => 'all',
                'title' => $attachment['title'] ?? null,
                'original_name' => basename($file->getClientOriginalName()),
                'file_name' => $fileName,
                'file_path' => $file->storeAs('project-documents', $fileName, 'local'),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
            activity('projects')->performedOn($project)->causedBy($request->user())->event('project.document.uploaded')
                ->withProperties(['document_id' => $document->id, 'type' => $document->type?->value, 'visibility' => 'all'])->log('Document projet ajouté');
        }
    }
}
