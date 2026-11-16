<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Country;
use App\Models\FamilyBranch;
use App\Models\MemberProfile;
use App\Models\User;
use App\Support\MemberRoleHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('view members');

        $query = MemberProfile::query()
            ->with(['user.roles', 'country', 'familyBranch'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->country_id, fn ($q, $id) => $q->where('country_id', $id))
            ->when($request->family_branch_id, fn ($q, $id) => $q->where('family_branch_id', $id))
            // Par défaut, l'annuaire montre les membres actifs ; « all » affiche tous les statuts.
            ->when($request->input('status', 'active') !== 'all', fn ($q) => $q->where('status', $request->input('status', 'active')))
            ->when($request->role, function ($q, $role) {
                $q->whereHas('user.roles', fn ($r) => $r->where('name', $role));
            })
            ->latest('member_since');

        $members = $query->paginate($request->integer('per_page', 12))->withQueryString();

        $members->getCollection()->transform(fn (MemberProfile $profile) => $this->formatMember($profile));

        return Inertia::render('Members/Index', [
            'members' => $members,
            'filters' => [...$request->only(['search', 'country_id', 'family_branch_id', 'role', 'per_page']), 'status' => $request->input('status', 'active')],
            'stats' => $this->indexStats(),
            'countries' => Country::forDisplay(Country::where('is_active', true)->orderBy('name')->get(['id', 'name', 'flag_emoji', 'code'])),
            'branches' => FamilyBranch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'roles' => Role::orderBy('name')->pluck('name')->map(fn ($name) => [
                'name' => $name,
                'label' => MemberRoleHelper::label($name),
            ])->values(),
            'statuses' => MemberStatus::options(),
            'countryStats' => $this->countryDistribution(),
            'branchStats' => $this->branchDistribution(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create members');

        return Inertia::render('Members/Create', $this->formOptions());
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $memberProfile = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
            ]);

            $user->syncRoles($request->input('roles'));

            return MemberProfile::create([
                ...$request->safe()->except(['name', 'email', 'password', 'password_confirmation', 'roles']),
                'user_id' => $user->id,
            ]);
        });

        $this->logMemberActivity($memberProfile, 'member.created', 'Membre créé', $request);

        return redirect()->route('members.show', $memberProfile)
            ->with('success', 'Membre créé avec succès.');
    }

    public function show(MemberProfile $memberProfile): Response
    {
        $this->authorize('view members');

        $memberProfile->load(['user.roles', 'user.permissions', 'country', 'familyBranch']);

        $activities = Activity::query()
            ->with('causer')
            ->where('subject_type', MemberProfile::class)
            ->where('subject_id', $memberProfile->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'created_at' => $activity->created_at?->format('d/m/Y H:i'),
                'causer' => $activity->causer?->name,
            ]);

        return Inertia::render('Members/Show', [
            'member' => $this->formatMember($memberProfile, detailed: true),
            'activities' => $activities,
            'can' => [
                'edit' => request()->user()?->can('edit members') ?? false,
                'suspend' => request()->user()?->can('suspend members') ?? false,
                'delete' => request()->user()?->can('delete members') ?? false,
            ],
        ]);
    }

    public function edit(MemberProfile $memberProfile): Response
    {
        $this->authorize('edit members');

        $memberProfile->load(['user.roles', 'country', 'familyBranch']);

        return Inertia::render('Members/Edit', [
            'member' => $this->formatMember($memberProfile, detailed: true),
            ...$this->formOptions($memberProfile),
        ]);
    }

    public function update(UpdateMemberRequest $request, MemberProfile $memberProfile): RedirectResponse
    {
        DB::transaction(function () use ($request, $memberProfile) {
            $memberProfile->user->update([
                'name' => $request->name,
                'email' => $request->email,
                ...($request->filled('password') ? ['password' => Hash::make($request->password)] : []),
            ]);

            $memberProfile->user->syncRoles($request->input('roles'));

            $memberProfile->update($request->safe()->except([
                'name', 'email', 'password', 'password_confirmation', 'roles',
            ]));
        });

        $this->logMemberActivity($memberProfile, 'member.updated', 'Membre modifié', $request);

        return redirect()->route('members.show', $memberProfile)
            ->with('success', 'Membre mis à jour avec succès.');
    }

    public function suspend(Request $request, MemberProfile $memberProfile): RedirectResponse
    {
        $this->authorize('suspend members');

        abort_if($request->user()->is($memberProfile->user), 403, 'Vous ne pouvez pas suspendre votre propre compte.');

        $memberProfile->update(['status' => MemberStatus::Suspended]);
        $this->revokeUserAccess($memberProfile->user);

        $this->logMemberActivity($memberProfile, 'member.suspended', 'Membre suspendu', $request);

        return back()->with('success', 'Membre suspendu.');
    }

    public function activate(Request $request, MemberProfile $memberProfile): RedirectResponse
    {
        $this->authorize('suspend members');

        $memberProfile->update(['status' => MemberStatus::Active]);

        $this->logMemberActivity($memberProfile, 'member.activated', 'Membre activé', $request);

        return back()->with('success', 'Membre activé.');
    }

    public function destroy(Request $request, MemberProfile $memberProfile): RedirectResponse
    {
        $this->authorize('delete members');

        abort_if($request->user()->is($memberProfile->user), 403, 'Vous ne pouvez pas archiver votre propre compte.');

        if ($memberProfile->user?->hasRole('super_admin') && User::role('super_admin')->count() <= 1) {
            return back()->with('error', 'Le dernier super-administrateur ne peut pas être archivé.');
        }

        $memberProfile->update(['status' => MemberStatus::Inactive]);
        $this->revokeUserAccess($memberProfile->user);
        $this->logMemberActivity($memberProfile, 'member.archived', 'Membre archivé', $request);

        return redirect()->route('members.index')
            ->with('success', 'Membre archivé. Les données historiques sont conservées.');
    }

    private function formOptions(?MemberProfile $target = null): array
    {
        return [
            'countries' => Country::forDisplay(Country::where('is_active', true)->orderBy('name')->get(['id', 'name', 'flag_emoji'])),
            'branches' => FamilyBranch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'zones' => \App\Models\Zone::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'roles' => MemberRoleHelper::optionsFor(auth()->user(), $target?->user),
            'statuses' => MemberStatus::options(),
        ];
    }

    private function formatMember(MemberProfile $profile, bool $detailed = false): array
    {
        $profile->loadMissing(['user.roles', 'country', 'familyBranch', 'zone']);

        $data = [
            'id' => $profile->id,
            'name' => $profile->user?->name,
            'email' => $profile->user?->email,
            'roles' => MemberRoleHelper::sort($profile->user?->roles->pluck('name') ?? []),
            'role' => MemberRoleHelper::primary($profile->user?->roles->pluck('name') ?? []),
            'role_label' => MemberRoleHelper::labels($profile->user?->roles->pluck('name') ?? []),
            'country' => $profile->country ? [
                'id' => $profile->country->id,
                'name' => $profile->country->name,
                'flag_emoji' => $profile->country->flag_emoji,
            ] : null,
            'zone' => $profile->zone ? ['id' => $profile->zone->id, 'name' => $profile->zone->name] : null,
            'share_percent' => $profile->user ? app(\App\Services\EligibilityService::class)->sharePercent($profile->user) : 0,
            'total_contributions' => $profile->user ? \App\Support\ProjectFormatter::money(app(\App\Services\EligibilityService::class)->totalContributions($profile->user)) : null,
            'family_branch' => $profile->familyBranch ? [
                'id' => $profile->familyBranch->id,
                'name' => $profile->familyBranch->name,
            ] : null,
            'profession' => $profile->profession,
            'city' => $profile->city,
            'status' => $profile->status->value,
            'status_label' => $profile->status->label(),
            'status_variant' => $profile->status->badgeVariant(),
            'member_since' => $profile->member_since?->format('d/m/Y'),
            'member_since_raw' => $profile->member_since?->format('Y-m-d'),
            'avatar_url' => $profile->user?->avatarUrl(),
            'age' => $profile->date_of_birth?->age,
            'country_code' => $profile->country?->code,
            'bio_excerpt' => $profile->bio ? \Illuminate\Support\Str::limit($profile->bio, 110) : null,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'phone' => $profile->phone,
                'address' => $profile->address,
                'bio' => $profile->bio,
                'date_of_birth' => $profile->date_of_birth?->format('d/m/Y'),
                'date_of_birth_raw' => $profile->date_of_birth?->format('Y-m-d'),
                'gender' => $profile->gender,
                'languages' => $profile->languages ?? [],
                'notes' => $profile->notes,
                'permissions' => $profile->user?->getAllPermissions()->pluck('name'),
            ]);
        }

        return $data;
    }

    private function indexStats(): array
    {
        $total = MemberProfile::count();
        $active = MemberProfile::where('status', MemberStatus::Active)->count();
        $newThisMonth = MemberProfile::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $countriesCount = MemberProfile::whereNotNull('country_id')
            ->distinct()
            ->count('country_id');
        $pending = MemberProfile::where('status', MemberStatus::Pending)->count();

        return [
            ['title' => 'Membres actifs', 'value' => (string) $active, 'change' => "+{$newThisMonth} ce mois", 'changeType' => 'success'],
            ['title' => 'Nouveaux ce mois', 'value' => (string) $newThisMonth, 'change' => '—', 'changeType' => 'neutral'],
            ['title' => 'Pays représentés', 'value' => (string) $countriesCount, 'change' => '—', 'changeType' => 'neutral'],
            ['title' => 'En attente', 'value' => (string) $pending, 'change' => "Total : {$total}", 'changeType' => 'neutral'],
        ];
    }

    private function countryDistribution(): array
    {
        $total = max(MemberProfile::whereNotNull('country_id')->count(), 1);

        return Country::query()
            ->withCount('memberProfiles')
            ->orderByDesc('member_profiles_count')
            ->get()
            ->filter(fn (Country $c) => $c->member_profiles_count > 0)
            ->take(6)
            ->map(fn (Country $c) => [
                'name' => $c->name,
                'flag_emoji' => $c->flag_emoji,
                'count' => $c->member_profiles_count,
                'percent' => round(($c->member_profiles_count / $total) * 100),
            ])
            ->values()
            ->all();
    }

    private function branchDistribution(): array
    {
        $total = max(MemberProfile::whereNotNull('family_branch_id')->count(), 1);

        return FamilyBranch::query()
            ->withCount('memberProfiles')
            ->orderByDesc('member_profiles_count')
            ->get()
            ->filter(fn (FamilyBranch $b) => $b->member_profiles_count > 0)
            ->take(6)
            ->map(fn (FamilyBranch $b) => [
                'name' => $b->name,
                'count' => $b->member_profiles_count,
                'percent' => round(($b->member_profiles_count / $total) * 100),
            ])
            ->values()
            ->all();
    }

    private function logMemberActivity(MemberProfile $memberProfile, string $event, string $description, Request $request): void
    {
        activity('members')
            ->performedOn($memberProfile)
            ->causedBy($request->user())
            ->event($event)
            ->withProperties([
                'member_name' => $memberProfile->displayName(),
                'status' => $memberProfile->status->value,
            ])
            ->log($description);
    }

    private function revokeUserAccess(?User $user): void
    {
        if (! $user) {
            return;
        }

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->delete();
        }

        $user->forceFill(['remember_token' => null])->saveQuietly();
    }
}
