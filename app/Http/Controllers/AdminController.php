<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\DocumentCategory;
use App\Models\User;
use App\Support\MemberRoleHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminController extends Controller
{
    private const SYSTEM_ROLES = ['super_admin', 'president', 'vice_president', 'treasurer', 'secretary', 'investment_committee', 'solidarity_committee', 'academy_manager', 'member', 'guest'];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('access administration'), 403);
        $roles = Role::query()->withCount('users')->with('permissions:id,name')->orderBy('name')->get()->map(fn (Role $role) => [
            'id' => $role->id, 'name' => $role->name, 'users_count' => $role->users_count,
            'permissions' => $role->permissions->pluck('name')->values(), 'system' => in_array($role->name, self::SYSTEM_ROLES, true),
        ]);
        $permissions = Permission::query()->withCount('roles')->orderBy('name')->get()->map(fn (Permission $permission) => [
            'id' => $permission->id, 'name' => $permission->name, 'roles_count' => $permission->roles_count,
            'group' => $this->permissionGroup($permission->name),
        ]);
        $users = User::query()->with('roles:id,name')->with('memberProfile:id,user_id,status')->orderBy('name')->get()->map(fn (User $user) => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'avatar_url' => $user->avatarUrl(),
            'role_ids' => $user->roles->pluck('id')->values(), 'role' => MemberRoleHelper::labels($user->roles->pluck('name')),
            'role_names' => array_values(MemberRoleHelper::sort($user->roles->pluck('name'))),
            'status' => $user->memberProfile?->status?->value ?? 'system',
            'member_profile_id' => $user->memberProfile?->id,
            'created_at' => $user->created_at?->format('d/m/Y'),
            'policy_accepted' => $user->policy_accepted_at !== null,
        ]);
        $settings = ApplicationSetting::query()->whereNotIn('key', [...array_keys(\App\Support\FundRules::DEFINITIONS), 'default_currency'])->orderBy('group')->orderBy('label')->get()->groupBy('group')->map(fn ($items) => $items->map(fn (ApplicationSetting $setting) => [
            'id' => $setting->id, 'key' => $setting->key, 'label' => $setting->label, 'value' => $setting->value,
            'type' => $setting->type, 'description' => $setting->description,
        ])->values())->toArray();
        // Journal : filtres et pagination côté serveur (le journal grossit avec le temps).
        $audit = [
            'search' => trim((string) $request->query('audit_search', '')),
            'log' => (string) $request->query('audit_log', ''),
            'user' => $request->integer('audit_user') ?: null,
            'from' => $request->date('audit_from')?->toDateString(),
            'to' => $request->date('audit_to')?->toDateString(),
            'per_page' => in_array($request->integer('audit_per_page'), [10, 25, 50, 100], true) ? $request->integer('audit_per_page') : 25,
        ];
        $activities = Activity::query()->with('causer')
            ->when($audit['log'], fn ($q, $log) => $q->where('log_name', $log))
            ->when($audit['user'], fn ($q, $id) => $q->where('causer_type', User::class)->where('causer_id', $id))
            ->when($audit['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($audit['to'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($audit['search'], fn ($q, $term) => $q->where(fn ($w) => $w->where('description', 'like', "%{$term}%")->orWhere('event', 'like', "%{$term}%")))
            ->latest('id')->paginate($audit['per_page'], ['*'], 'audit_page')->withQueryString()
            ->through(fn (Activity $activity) => [
                'id' => $activity->id, 'description' => $activity->description, 'event' => $activity->event, 'log' => $activity->log_name,
                'causer' => $activity->causer?->name ?? 'Système', 'date' => $activity->created_at?->format('d/m/Y H:i'),
            ]);
        $currency = \App\Support\FundCurrency::code();

        return Inertia::render('Admin/Index', [
            'roles' => $roles, 'permissions' => $permissions, 'users' => $users, 'settings' => $settings,
            'categories' => DocumentCategory::orderBy('name')->get(['id', 'name', 'description', 'retention_days', 'is_active']),
            'activities' => $activities,
            'auditFilters' => $audit,
            'auditLogs' => Activity::query()->select('log_name')->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values(),
            'tab' => $request->query('tab'),
            'currency' => [
                'current' => $currency,
                'options' => \App\Support\FundCurrency::options(),
                'rates' => collect(array_keys(\App\Support\FundCurrency::SUPPORTED))->reject(fn ($c) => $c === $currency)
                    ->mapWithKeys(fn ($c) => [$c => \App\Support\FundCurrency::suggestedRate($currency, $c)])->all(),
                'pending_approvals' => \App\Models\ApprovalRequest::where('status', 'pending')->count(),
            ],
            'stats' => ['users' => $users->count(), 'roles' => $roles->count(), 'permissions' => $permissions->count(), 'settings' => collect($settings)->flatten(1)->count()],
            'can' => ['roles' => $request->user()->can('manage roles'), 'permissions' => $request->user()->can('manage permissions'), 'users' => $request->user()->can('manage users'), 'settings' => $request->user()->can('manage settings'), 'categories' => $request->user()->can('manage document categories')],
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'manage roles');
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:roles,name'], 'permissions' => ['array'], 'permissions.*' => ['string', 'exists:permissions,name']]);
        $role = Role::create(['name' => $data['name']]); $role->syncPermissions($data['permissions'] ?? []);
        $this->log($request, 'administration.role.created', 'Rôle créé', $role, ['permissions' => $data['permissions'] ?? []]);
        return back()->with('success', 'Rôle créé.');
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $this->authorizePermission($request, 'manage roles');
        abort_if($role->name === 'super_admin', 422, 'Le rôle super-administrateur conserve toutes les permissions.');
        $data = $request->validate(['permissions' => ['array'], 'permissions.*' => ['string', 'exists:permissions,name']]);
        $before = $role->permissions()->pluck('name')->all(); $role->syncPermissions($data['permissions'] ?? []);
        $this->log($request, 'administration.role.updated', 'Permissions du rôle mises à jour', $role, ['before' => $before, 'after' => $data['permissions'] ?? []]);
        return back()->with('success', 'Permissions du rôle mises à jour.');
    }

    public function destroyRole(Request $request, Role $role): RedirectResponse
    {
        $this->authorizePermission($request, 'manage roles');
        abort_if(in_array($role->name, self::SYSTEM_ROLES, true), 422, 'Un rôle système ne peut pas être supprimé.');
        abort_if($role->users()->exists(), 422, 'Ce rôle est encore attribué à des utilisateurs.');
        $name = $role->name; $role->delete(); app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->log($request, 'administration.role.deleted', 'Rôle supprimé', null, ['role' => $name]);
        return back()->with('success', 'Rôle supprimé.');
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $this->authorizePermission($request, 'manage users');
        if ($request->user()->is($user)) return back()->with('error', 'Vous ne pouvez pas modifier vos propres rôles.');
        // Une personne peut cumuler plusieurs rôles (ex. trésorière et membre du comité d'investissement).
        $data = $request->validate(['role_ids' => ['required', 'array', 'min:1'], 'role_ids.*' => ['integer', 'distinct', 'exists:roles,id']], ['role_ids.required' => 'Choisissez au moins un rôle.']);
        $roles = Role::whereIn('id', $data['role_ids'])->get(); $current = $user->roles()->pluck('name')->all();
        if (in_array('super_admin', $current, true) && ! $roles->contains('name', 'super_admin') && User::role('super_admin')->count() <= 1) {
            return back()->with('error', 'Le dernier super-administrateur doit conserver son rôle.');
        }
        $user->syncRoles($roles);
        $after = MemberRoleHelper::sort($roles->pluck('name'));
        $this->log($request, 'administration.user.role_updated', 'Rôles utilisateur mis à jour', $user, ['before' => MemberRoleHelper::sort($current), 'after' => $after]);
        return back()->with('success', 'Rôles mis à jour : '.MemberRoleHelper::labels($after).'.');
    }

    public function storePermission(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'manage permissions');
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'regex:/^[a-z][a-z0-9 ]*$/', 'unique:permissions,name']]);
        $permission = Permission::create(['name' => $data['name']]); app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->log($request, 'administration.permission.created', 'Permission créée', $permission);
        return back()->with('success', 'Permission créée.');
    }

    public function destroyPermission(Request $request, Permission $permission): RedirectResponse
    {
        $this->authorizePermission($request, 'manage permissions');
        abort_if($permission->roles()->exists() || DB::table('model_has_permissions')->where('permission_id', $permission->id)->exists(), 422, 'Cette permission est encore utilisée.');
        $name = $permission->name; $permission->delete(); app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->log($request, 'administration.permission.deleted', 'Permission supprimée', null, ['permission' => $name]);
        return back()->with('success', 'Permission supprimée.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'manage settings');
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['nullable', 'string', 'max:5000']]);
        foreach ($data['settings'] as $key => $value) {
            // Les règles du fonds se modifient dans « Paramètres du fonds », avec leurs propres contrôles.
            if (array_key_exists($key, \App\Support\FundRules::DEFINITIONS) || $key === 'default_currency') continue;
            $setting = ApplicationSetting::where('key', $key)->first();
            if (! $setting) continue;
            if ($setting->type === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) return back()->withErrors(["settings.{$key}" => 'Une valeur entière est attendue.']);
            $before = $setting->value; $setting->update(['value' => $value, 'updated_by' => $request->user()->id]);
            if ($before !== $value) $this->log($request, 'administration.setting.updated', 'Paramètre mis à jour', $setting, ['key' => $key, 'before' => $before, 'after' => $value]);
        }
        return back()->with('success', 'Paramètres enregistrés.');
    }

    /** Change la devise du fonds et convertit tous les montants enregistrés. */
    public function convertCurrency(Request $request, \App\Services\CurrencyConversionService $service): RedirectResponse
    {
        $this->authorizePermission($request, 'manage settings');
        $data = $request->validate([
            'currency' => ['required', Rule::in(array_keys(\App\Support\FundCurrency::SUPPORTED))],
            'rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Confirmez la conversion de toutes les données.', 'rate.gt' => 'Le taux doit être positif.']);
        try {
            $result = $service->convert($data['currency'], (float) $data['rate'], $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['currency' => $e->getMessage()]);
        }

        return redirect()->route('admin.index', ['tab' => 'settings'])
            ->with('success', __('Devise changée : :from → :to. Tous les montants ont été convertis.', ['from' => $result['from'], 'to' => $result['to']]));
    }

    private function authorizePermission(Request $request, string $permission): void { abort_unless($request->user()->can($permission), 403); }
    private function permissionGroup(string $name): string
    {
        return match (true) {
            str_contains($name, 'document') => 'Documents', str_contains($name, 'vote') => 'Votes',
            str_contains($name, 'project') => 'Projets', str_contains($name, 'fund') || str_contains($name, 'allocation') => 'Fonds',
            str_contains($name, 'contribution') => 'Cotisations', str_contains($name, 'member') || str_contains($name, 'roles') || str_contains($name, 'permissions') || str_contains($name, 'users') => 'Administration',
            default => 'Général',
        };
    }
    private function log(Request $request, string $event, string $message, mixed $subject = null, array $properties = []): void
    {
        $logger = activity('administration')->causedBy($request->user())->event($event)->withProperties($properties);
        if ($subject instanceof \Illuminate\Database\Eloquent\Model) $logger->performedOn($subject);
        $logger->log($message);
    }
}
