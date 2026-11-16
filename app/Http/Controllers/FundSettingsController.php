<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Zone;
use App\Support\FundRules;
use App\Support\MemberRoleHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Paramètres du règlement intérieur : règles chiffrées, zones et trésoriers,
 * types de transactions et nombre de validations requises.
 */
class FundSettingsController extends Controller
{
    private const PERMISSIONS = ['manage settings', 'manage zones', 'manage transaction types'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->canAny(self::PERMISSIONS), 403);

        return Inertia::render('FundSettings/Index', [
            'rules' => FundRules::all(),
            'zones' => Zone::query()->with('treasurers:id,name')->withCount('members')->orderBy('name')->get()->map(fn (Zone $zone) => [
                'id' => $zone->id, 'name' => $zone->name, 'code' => $zone->code, 'description' => $zone->description,
                'is_active' => $zone->is_active, 'members_count' => $zone->members_count,
                'treasurer_ids' => $zone->treasurers->pluck('id')->values(),
                'treasurers' => $zone->treasurers->pluck('name')->values(),
            ]),
            'transactionTypes' => TransactionType::query()->orderByDesc('is_system')->orderBy('name')->get()->map(fn (TransactionType $type) => [
                'id' => $type->id, 'code' => $type->code, 'name' => $type->name, 'description' => $type->description,
                'direction' => $type->direction, 'required_approvals' => $type->required_approvals,
                'approver_roles' => $type->approver_roles ?? [], 'is_system' => $type->is_system, 'is_active' => $type->is_active,
            ]),
            'treasurerCandidates' => User::role('treasurer')->orderBy('name')->get(['id', 'name']),
            'roles' => collect(MemberRoleHelper::sort(Role::pluck('name')))->map(fn ($name) => ['name' => $name, 'label' => MemberRoleHelper::label($name)])->values(),
            'directions' => collect(TransactionType::DIRECTIONS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'can' => [
                'rules' => $user->can('manage settings'),
                'zones' => $user->can('manage zones'),
                'transactionTypes' => $user->can('manage transaction types'),
            ],
        ]);
    }

    public function updateRules(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage settings'), 403);

        $rules = ['rules' => ['required', 'array']];
        foreach (array_keys(FundRules::DEFINITIONS) as $key) {
            $rules["rules.$key"] = FundRules::validationRules($key);
        }
        $data = $request->validate($rules, [], collect(FundRules::DEFINITIONS)->mapWithKeys(fn ($d, $k) => ["rules.$k" => $d['label']])->all())['rules'];

        if ((float) $data['loan_third_reminder_days'] <= (float) $data['loan_second_reminder_days']) {
            return back()->withErrors(['rules.loan_third_reminder_days' => 'Le 3e rappel doit venir après le 2e rappel.']);
        }
        if ((int) $data['project_notice_max_days'] < (int) $data['project_notice_min_days']) {
            return back()->withErrors(['rules.project_notice_max_days' => 'Le délai maximal doit être supérieur ou égal au délai minimal.']);
        }

        $changes = [];
        foreach (FundRules::DEFINITIONS as $key => $definition) {
            $before = (string) FundRules::get($key);
            $after = (string) ($data[$key] ?? '');
            if ($before === $after) continue;
            ApplicationSetting::updateOrCreate(['key' => $key], [
                'group' => 'règlement du fonds', 'label' => $definition['label'], 'type' => $definition['type'],
                'description' => $definition['description'] ?? null, 'is_public' => true,
                'value' => $after, 'updated_by' => $request->user()->id,
            ]);
            $changes[$key] = ['before' => $before, 'after' => $after];
        }
        FundRules::flush();
        // La contribution trimestrielle suit le montant et la date limite du règlement.
        \App\Support\ContributionRules::ensurePlan();

        if ($changes) {
            activity('administration')->causedBy($request->user())->event('fund_rules.updated')
                ->withProperties($changes)->log('Règles du fonds mises à jour');
        }

        return back()->with('success', $changes ? 'Règles du fonds enregistrées.' : 'Aucune modification.');
    }

    public function storeZone(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage zones'), 403);
        $data = $this->validateZone($request);
        $zone = Zone::create([...collect($data)->except('treasurer_ids')->all(), 'code' => $this->uniqueCode(Zone::class, $data['name'])]);
        $zone->treasurers()->sync($data['treasurer_ids'] ?? []);
        $this->log($request, 'zone.created', 'Zone créée', $zone, ['name' => $zone->name, 'treasurers' => $data['treasurer_ids'] ?? []]);

        return back()->with('success', 'Zone créée.');
    }

    public function updateZone(Request $request, Zone $zone): RedirectResponse
    {
        abort_unless($request->user()->can('manage zones'), 403);
        $data = $this->validateZone($request);
        $before = ['name' => $zone->name, 'is_active' => $zone->is_active, 'treasurers' => $zone->treasurers()->pluck('users.id')->all()];
        $zone->update(collect($data)->except('treasurer_ids')->all());
        $zone->treasurers()->sync($data['treasurer_ids'] ?? []);
        $this->log($request, 'zone.updated', 'Zone mise à jour', $zone, ['before' => $before, 'after' => ['name' => $zone->name, 'is_active' => $zone->is_active, 'treasurers' => $data['treasurer_ids'] ?? []]]);

        return back()->with('success', 'Zone mise à jour.');
    }

    public function destroyZone(Request $request, Zone $zone): RedirectResponse
    {
        abort_unless($request->user()->can('manage zones'), 403);
        if ($zone->members()->exists()) {
            return back()->with('error', 'Cette zone contient encore des membres : désactivez-la plutôt.');
        }
        $name = $zone->name;
        $zone->delete();
        $this->log($request, 'zone.deleted', 'Zone supprimée', null, ['name' => $name]);

        return back()->with('success', 'Zone supprimée.');
    }

    public function storeTransactionType(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage transaction types'), 403);
        $data = $this->validateTransactionType($request);
        $type = TransactionType::create([...$data, 'code' => $this->uniqueCode(TransactionType::class, $data['name']), 'is_system' => false, 'created_by' => $request->user()->id]);
        $this->log($request, 'transaction_type.created', 'Type de transaction créé', $type, $data);

        return back()->with('success', 'Type de transaction créé.');
    }

    public function updateTransactionType(Request $request, TransactionType $transactionType): RedirectResponse
    {
        abort_unless($request->user()->can('manage transaction types'), 403);
        $data = $this->validateTransactionType($request);
        if ($transactionType->is_system) {
            // Le sens d'un type prévu par le règlement ne change pas.
            $data['direction'] = $transactionType->direction;
        }
        $before = $transactionType->only(array_keys($data));
        $transactionType->update($data);
        $this->log($request, 'transaction_type.updated', 'Type de transaction mis à jour', $transactionType, ['before' => $before, 'after' => $data]);

        return back()->with('success', 'Type de transaction mis à jour.');
    }

    public function destroyTransactionType(Request $request, TransactionType $transactionType): RedirectResponse
    {
        abort_unless($request->user()->can('manage transaction types'), 403);
        if ($transactionType->is_system) {
            return back()->with('error', 'Ce type est prévu par le règlement : vous pouvez le désactiver, pas le supprimer.');
        }
        $name = $transactionType->name;
        $transactionType->delete();
        $this->log($request, 'transaction_type.deleted', 'Type de transaction supprimé', null, ['name' => $name]);

        return back()->with('success', 'Type de transaction supprimé.');
    }

    private function validateZone(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'treasurer_ids' => ['array'],
            'treasurer_ids.*' => ['integer', Rule::exists('model_has_roles', 'model_id')->where('model_type', User::class)->where('role_id', Role::where('name', 'treasurer')->value('id'))],
        ], ['treasurer_ids.*.exists' => 'Seule une personne ayant le rôle Trésorier peut être trésorière d’une zone.']);
    }

    private function validateTransactionType(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'direction' => ['required', Rule::in(array_keys(TransactionType::DIRECTIONS))],
            'required_approvals' => ['required', 'integer', 'min:1', 'max:5'],
            'approver_roles' => ['required', 'array', 'min:1'],
            'approver_roles.*' => ['string', 'exists:roles,name'],
            'is_active' => ['boolean'],
        ], ['approver_roles.required' => 'Choisissez au moins un rôle autorisé à valider.']);
    }

    private function uniqueCode(string $model, string $name): string
    {
        $base = Str::slug($name, '_') ?: 'element';
        $code = $base;
        for ($i = 2; $model::where('code', $code)->exists(); $i++) {
            $code = "{$base}_{$i}";
        }

        return $code;
    }

    private function log(Request $request, string $event, string $message, mixed $subject, array $properties): void
    {
        $activity = activity('administration')->causedBy($request->user())->event($event)->withProperties($properties);
        if ($subject) $activity->performedOn($subject);
        $activity->log($message);
    }
}
