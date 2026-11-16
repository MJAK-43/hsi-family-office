<?php

namespace App\Http\Controllers;

use App\Enums\ContributionPaymentStatus;
use App\Enums\FundMovementType;
use App\Enums\MemberStatus;
use App\Enums\ProjectStatus;
use App\Enums\VoteStatus;
use App\Models\ContributionPayment;
use App\Models\FundAccount;
use App\Models\FundMovement;
use App\Models\MemberProfile;
use App\Models\Project;
use App\Models\Vote;
use App\Support\ContributionAccess;
use App\Support\ContributionFormatter;
use App\Support\FundFormatter;
use App\Support\ProjectAccess;
use App\Support\ProjectFormatter;
use App\Support\VoteAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $visibility = [
            'members' => $user->can('view members'),
            'contributions' => ContributionAccess::canViewAll($user) || ContributionAccess::canViewOwn($user),
            'funds' => $user->can('view funds'),
            'fundMovements' => $user->can('view funds') && $user->can('view fund movements'),
            'projects' => ProjectAccess::canView($user),
            'votes' => VoteAccess::canView($user),
        ];

        $activeMembers = $visibility['members']
            ? MemberProfile::where('status', MemberStatus::Active)->count()
            : 0;
        $newMembersThisMonth = $visibility['members']
            ? MemberProfile::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count()
            : 0;

        $contributionQuery = ContributionPayment::query()
            ->when(! ContributionAccess::canViewAll($user), fn ($query) => $query->where('user_id', $user->id));
        $validatedTotal = $visibility['contributions']
            ? (clone $contributionQuery)->where('status', ContributionPaymentStatus::Validated)->sum('amount')
            : 0;
        $validatedCount = $visibility['contributions']
            ? (clone $contributionQuery)->where('status', ContributionPaymentStatus::Validated)->count()
            : 0;
        $pendingCount = $visibility['contributions']
            ? (clone $contributionQuery)->where('status', ContributionPaymentStatus::Pending)->count()
            : 0;
        $recentPayments = $visibility['contributions']
            ? (clone $contributionQuery)->with('user')
                ->where('status', ContributionPaymentStatus::Validated)
                ->latest('validated_at')
                ->limit(50)
                ->get()
                ->map(fn (ContributionPayment $payment) => [
                    'membre' => $payment->user?->name ?? '—',
                    'montant' => ContributionFormatter::money($payment->amount, $payment->currency),
                    'date' => $payment->validated_at?->translatedFormat('d M Y') ?? $payment->submitted_at?->translatedFormat('d M Y') ?? '—',
                    'statut' => $payment->status->label(),
                    'statut_variant' => $payment->status->color(),
                ])
            : collect();

        $accounts = $visibility['funds'] ? FundAccount::where('is_active', true)->get() : collect();
        $totalFund = (float) $accounts->sum('current_balance');
        $treasury = $accounts->firstWhere('slug', 'treasury');
        $familyProjectsAccount = $accounts->firstWhere('slug', 'family-projects');
        $fundAllocation = $accounts
            ->filter(fn (FundAccount $account) => $account->slug !== 'treasury')
            ->map(fn (FundAccount $account) => [
                'name' => $account->name,
                'value' => (float) $account->current_balance,
                'percent' => $totalFund > 0 ? round(((float) $account->current_balance / $totalFund) * 100, 1) : 0,
            ])->values();
        $allocatedTotal = $visibility['fundMovements']
            ? FundMovement::validated()->where('type', FundMovementType::ContributionAllocation)->sum('amount')
            : 0;
        $recentFundMovements = $visibility['fundMovements']
            ? FundMovement::with('account')->validated()->latest('movement_date')->limit(50)->get()
                ->map(fn (FundMovement $movement) => [
                    'poche' => $movement->account?->name ?? '—',
                    'montant' => FundFormatter::money($movement->amount, $movement->currency),
                    'type' => $movement->type->label(),
                    'date' => $movement->movement_date?->translatedFormat('d M Y') ?? '—',
                ])
            : collect();

        $projectQuery = Project::query()->when(! ProjectAccess::canViewAll($user), function ($query) use ($user): void {
            $publicStatuses = array_map(
                fn (ProjectStatus $status) => $status->value,
                array_filter(ProjectStatus::cases(), fn (ProjectStatus $status) => ProjectAccess::isPublicStatus($status)),
            );
            $query->where(fn ($scope) => $scope->where('owner_id', $user->id)->orWhereIn('status', $publicStatuses));
        });
        $projectCount = fn (ProjectStatus $status): int => $visibility['projects']
            ? (clone $projectQuery)->where('status', $status)->count()
            : 0;
        $submittedProjects = $projectCount(ProjectStatus::Submitted);
        $underReviewProjects = $projectCount(ProjectStatus::UnderReview);
        $approvedCommitteeProjects = $projectCount(ProjectStatus::ApprovedByCommittee);
        $awaitingFundingProjects = $projectCount(ProjectStatus::ApprovedByFamily);
        $fundedProjects = $projectCount(ProjectStatus::Funded);
        $requestedProjectsTotal = $visibility['projects'] ? (clone $projectQuery)->sum('requested_amount') : 0;
        $recentProjects = $visibility['projects']
            ? (clone $projectQuery)->with('owner')->latest('submitted_at')->latest('created_at')->limit(50)->get()
                ->map(fn (Project $project) => [
                    'projet' => $project->title,
                    'porteur' => $project->owner?->name ?? '—',
                    'statut' => $project->status->label(),
                    'statut_variant' => $project->status->color(),
                    'id' => $project->id,
                ])
            : collect();

        $openVotes = $visibility['votes'] ? Vote::where('status', VoteStatus::Open)->count() : 0;
        $newVotesThisWeek = $visibility['votes']
            ? Vote::where('status', VoteStatus::Open)->where('opens_at', '>=', now()->subWeek())->count()
            : 0;
        $recentVotes = $visibility['votes']
            ? Vote::where('status', VoteStatus::Open)->latest('closes_at')->limit(50)->get()
                ->map(fn (Vote $vote) => [
                    'id' => $vote->id,
                    'sujet' => $vote->title,
                    'type' => $vote->type->label(),
                    'echeance' => $vote->closes_at?->translatedFormat('d M Y') ?? '—',
                    'participation' => $vote->participationPercent(),
                    'statut' => $vote->status->label(),
                    'statut_variant' => $vote->status->color(),
                ])
            : collect();

        [$flowFrom, $flowTo, $flowPreset] = \App\Services\FundFlowService::period($request->query('flow_period'), $request->query('flow_from'), $request->query('flow_to'));

        return Inertia::render('Dashboard', [
            'visibility' => $visibility,
            'hiddenWidgets' => $user->preferences['dashboard_hidden'] ?? [],
            'flow' => $visibility['funds'] ? fn () => app(\App\Services\FundFlowService::class)->sankey(['from' => $flowFrom, 'to' => $flowTo]) : null,
            'flowPeriod' => $flowPreset,
            'memberStats' => ['active' => $activeMembers, 'newThisMonth' => $newMembersThisMonth],
            'contributionStats' => [
                'validatedTotal' => ContributionFormatter::money($validatedTotal),
                'validatedCount' => $validatedCount,
                'pendingCount' => $pendingCount,
            ],
            'recentContributions' => $recentPayments,
            'fundStats' => [
                'total' => FundFormatter::money($totalFund),
                'available' => FundFormatter::money($treasury?->current_balance ?? 0),
                'allocated' => FundFormatter::money($allocatedTotal),
            ],
            'fundAllocation' => $fundAllocation,
            'recentFundMovements' => $recentFundMovements,
            'projectStats' => [
                'submitted' => $submittedProjects,
                'underReview' => $underReviewProjects,
                'approvedCommittee' => $approvedCommitteeProjects,
                'awaitingFunding' => $awaitingFundingProjects,
                'funded' => $fundedProjects,
                'requestedTotal' => ProjectFormatter::money($requestedProjectsTotal),
                'inAnalysis' => $submittedProjects + $underReviewProjects,
                'familyProjectsBalance' => FundFormatter::money($familyProjectsAccount?->current_balance ?? 0),
            ],
            'recentProjects' => $recentProjects,
            'voteStats' => ['open' => $openVotes, 'newThisWeek' => $newVotesThisWeek],
            'recentVotes' => $recentVotes,
        ]);
    }
}
