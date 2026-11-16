<?php

namespace App\Http\Controllers;

use App\Enums\MajorityRule;
use App\Enums\VoteStatus;
use App\Enums\VoteType;
use App\Http\Requests\CancelVoteRequest;
use App\Http\Requests\CastBallotRequest;
use App\Http\Requests\CloseVoteRequest;
use App\Http\Requests\StoreVoteRequest;
use App\Models\Project;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteBallot;
use App\Models\VoteConflict;
use App\Services\VoteService;
use App\Support\VoteAccess;
use App\Support\VoteFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class VoteController extends Controller
{
    public function __construct(private VoteService $voteService) {}

    public function index(Request $request): Response
    {
        abort_unless(VoteAccess::canView($request->user()), 403);

        $votes = Vote::query()
            ->with(['project', 'creator'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->latest('opens_at')
            ->latest('created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $votes->getCollection()->transform(fn (Vote $vote) => $this->formatVote($vote));

        return Inertia::render('Votes/Index', [
            'votes' => $votes,
            'filters' => $request->only(['search', 'status', 'type', 'per_page']),
            'stats' => $this->indexStats(),
            'statuses' => VoteStatus::options(),
            'types' => VoteType::options(),
            'can' => [
                'create' => VoteAccess::canCreate($request->user()),
                'manage' => VoteAccess::canManage($request->user()),
            ],
        ]);
    }

    public function create(): Response
    {
        abort_unless(VoteAccess::canCreate(auth()->user()), 403);

        return Inertia::render('Votes/Create', [
            'types' => VoteType::options(),
            'majorityRules' => MajorityRule::options(),
            'projects' => Project::query()
                ->whereIn('status', ['submitted_to_vote', 'approved_by_committee'])
                ->orderBy('title')
                ->get(['id', 'title', 'status']),
        ]);
    }

    public function store(StoreVoteRequest $request): RedirectResponse
    {
        $vote = $this->voteService->create($request->validated(), $request->user());

        return redirect()->route('votes.show', $vote)
            ->with('success', 'Vote créé avec succès.');
    }

    public function show(Vote $vote): Response
    {
        abort_unless(VoteAccess::canView(request()->user()), 403);

        $user = request()->user();
        $vote->load(['project.owner', 'options', 'creator', 'closer', 'winningOption', 'eligibilities.user', 'conflicts.user']);

        $userBallot = VoteBallot::query()
            ->with('option')
            ->where('vote_id', $vote->id)
            ->where('user_id', $user->id)
            ->first();

        $ballots = [];
        if (VoteAccess::canViewBallotsDetail($user, $vote)) {
            $ballots = $vote->ballots()
                ->with(['user', 'option'])
                ->latest('cast_at')
                ->get()
                ->map(fn (VoteBallot $b) => [
                    'id' => $b->id,
                    'user' => $b->user?->name,
                    'option' => $b->option?->label,
                    'option_value' => $b->option?->value,
                    'comment' => $b->comment,
                    'cast_at' => $b->cast_at?->format('d/m/Y H:i'),
                ]);
        }

        $activities = Activity::query()
            ->with('causer')
            ->where('subject_type', Vote::class)
            ->where('subject_id', $vote->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'created_at' => $a->created_at?->format('d/m/Y H:i'),
                'causer' => $a->causer?->name,
            ]);

        return Inertia::render('Votes/Show', [
            'vote' => $this->formatVote($vote, detailed: true),
            'userBallot' => $userBallot ? [
                'option' => $userBallot->option?->label,
                'option_value' => $userBallot->option?->value,
                'comment' => $userBallot->comment,
                'cast_at' => $userBallot->cast_at?->format('d/m/Y H:i'),
            ] : null,
            'ballots' => $ballots,
            'activities' => $activities,
            'can' => [
                'cast' => VoteAccess::canCastOnVote($user, $vote) && ! $userBallot,
                'close' => VoteAccess::canClose($user) && $vote->status === VoteStatus::Open,
                'cancel' => VoteAccess::canManage($user)
                    && in_array($vote->status, [VoteStatus::Draft, VoteStatus::Scheduled, VoteStatus::Open], true),
                'viewBallots' => VoteAccess::canViewBallotsDetail($user, $vote),
                'manageConflicts' => VoteAccess::canManage($user) && in_array($vote->status, [VoteStatus::Draft, VoteStatus::Scheduled], true),
            ],
        ]);
    }

    public function cast(CastBallotRequest $request, Vote $vote): RedirectResponse
    {
        try {
            $this->voteService->castBallot(
                $vote,
                $request->user(),
                $request->input('option'),
                $request->input('comment'),
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['option' => $e->getMessage()]);
        }

        return redirect()->route('votes.show', $vote)
            ->with('success', 'Votre vote a été enregistré.');
    }

    public function close(CloseVoteRequest $request, Vote $vote): RedirectResponse
    {
        try {
            $this->voteService->close($vote, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['close' => $e->getMessage()]);
        }

        return redirect()->route('votes.show', $vote)
            ->with('success', 'Vote clôturé et résultat finalisé.');
    }

    public function cancel(CancelVoteRequest $request, Vote $vote): RedirectResponse
    {
        try {
            $this->voteService->cancel($vote, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return redirect()->route('votes.show', $vote)
            ->with('success', 'Vote annulé.');
    }

    public function declareConflict(Request $request, Vote $vote): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'exists:users,id'], 'reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $subject = ! empty($data['user_id']) && VoteAccess::canManage($request->user()) ? User::findOrFail($data['user_id']) : $request->user();
        try { $this->voteService->declareConflict($vote, $subject, $request->user(), $data['reason']); }
        catch (\RuntimeException $e) { return back()->withErrors(['conflict' => $e->getMessage()]); }
        return back()->with('success', 'Conflit d’intérêts déclaré.');
    }

    public function resolveConflict(Request $request, VoteConflict $voteConflict): RedirectResponse
    {
        abort_unless(VoteAccess::canManage($request->user()), 403);
        $data = $request->validate(['confirmed' => ['required', 'boolean'], 'resolution_note' => ['nullable', 'string', 'max:2000']]);
        try { $this->voteService->resolveConflict($voteConflict, $request->user(), $data['confirmed'], $data['resolution_note'] ?? null); }
        catch (\RuntimeException $e) { return back()->withErrors(['conflict' => $e->getMessage()]); }
        return back()->with('success', 'Conflit d’intérêts traité.');
    }

    private function indexStats(): array
    {
        $open = Vote::open()->count();
        $closed = Vote::closed()->count();
        $closingSoon = Vote::open()
            ->whereNotNull('closes_at')
            ->whereBetween('closes_at', [now(), now()->addDays(3)])
            ->count();
        $participation = Vote::open()->avg('ballots_count');

        return [
            ['key' => 'open', 'label' => 'Votes ouverts', 'value' => (string) $open],
            ['key' => 'closing', 'label' => 'Clôture sous 3 j.', 'value' => (string) $closingSoon],
            ['key' => 'closed', 'label' => 'Votes clôturés', 'value' => (string) $closed],
            ['key' => 'avg', 'label' => 'Bulletins (moy.)', 'value' => (string) round((float) $participation)],
        ];
    }

    private function formatVote(Vote $vote, bool $detailed = false): array
    {
        $data = [
            'id' => $vote->id,
            'title' => $vote->title,
            'slug' => $vote->slug,
            'description' => $vote->description,
            'type' => $vote->type?->value,
            'type_label' => $vote->type?->label(),
            'status' => $vote->status?->value,
            'status_label' => $vote->status?->label(),
            'status_variant' => $vote->status?->color(),
            'result' => $vote->result?->value,
            'result_label' => $vote->result?->label(),
            'result_variant' => $vote->result?->color(),
            'project' => $vote->project?->title,
            'project_id' => $vote->project_id,
            'opens_at' => $vote->opens_at?->format('d/m/Y H:i'),
            'closes_at' => $vote->closes_at?->format('d/m/Y H:i'),
            'closes_at_short' => $vote->closes_at?->translatedFormat('d M Y'),
            'quorum_percent' => $vote->quorum_percent,
            'majority_rule' => $vote->majority_rule?->label(),
            'eligible_count' => $vote->eligible_count,
            'ballots_count' => $vote->ballots_count,
            'for_count' => $vote->for_count,
            'against_count' => $vote->against_count,
            'abstain_count' => $vote->abstain_count,
            'participation' => $vote->participationPercent(),
            'participation_label' => VoteFormatter::percent($vote->participationPercent()),
            'quorum_reached' => $vote->quorumReached(),
            'creator' => $vote->creator?->name,
            'ballot_visibility' => $vote->ballot_visibility,
            'ballot_visibility_label' => $vote->hasSecretBallots() ? 'Secret' : 'Nominatif',
            'winning_option' => $vote->winningOption?->label,
        ];

        if ($detailed) {
            $data['options'] = $vote->options->map(fn ($o) => [
                'id' => $o->id,
                'label' => $o->label,
                'value' => $o->value,
                'is_winning' => $o->is_winning,
                'count' => match ($o->value) {
                    'for' => $vote->for_count,
                    'against' => $vote->against_count,
                    'abstain' => $vote->abstain_count,
                    default => $o->ballots()->count(),
                },
            ]);
            $data['allow_abstention'] = $vote->allow_abstention;
            $data['closed_at'] = $vote->closed_at?->format('d/m/Y H:i');
            $data['closed_by'] = $vote->closer?->name;
            $data['project_owner'] = $vote->project?->owner?->name;
            $data['project_status'] = $vote->project?->status?->label();
            $data['eligibilities'] = $vote->eligibilities->map(fn ($eligibility) => [
                'user' => $eligibility->user?->name, 'is_eligible' => $eligibility->is_eligible,
                'exclusion_reason' => $eligibility->exclusion_reason,
            ]);
            $data['conflicts'] = $vote->conflicts->map(fn ($conflict) => [
                'id' => $conflict->id, 'user' => $conflict->user?->name, 'reason' => $conflict->reason,
                'status' => $conflict->status, 'resolution_note' => $conflict->resolution_note,
            ]);
        }

        return $data;
    }

    /** Modifier les dates d'un vote programmé ou ouvert. */
    public function reschedule(\Illuminate\Http\Request $request, \App\Models\Vote $vote): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()->can('submit projects to vote') || $request->user()->can('manage votes'), 403);
        $data = $request->validate(['opens_at' => ['nullable', 'date'], 'closes_at' => ['required', 'date']]);
        try {
            app(\App\Services\VoteService::class)->reschedule($vote, $request->user(), $data['opens_at'] ?? null, $data['closes_at']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['vote_dates' => $e->getMessage()]);
        }

        return back()->with('success', 'Dates du vote mises à jour.');
    }
}
