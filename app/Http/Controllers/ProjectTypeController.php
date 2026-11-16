<?php

namespace App\Http\Controllers;

use App\Models\ProjectType;
use App\Models\ProjectTypeCriterion;
use App\Support\ProjectTypeDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Types de projets et listes de critères, gérés par le président et le comité d'investissement. */
class ProjectTypeController extends Controller
{
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('manage project types'), 403);
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('manage project types') || $request->user()->can('view all projects'), 403);

        return Inertia::render('ProjectTypes/Index', [
            'types' => ProjectType::query()->with('criteria')->withCount('projects')->orderBy('name')->get()->map(fn (ProjectType $type) => [
                'id' => $type->id, 'name' => $type->name, 'code' => $type->code, 'description' => $type->description,
                'is_loan' => $type->is_loan, 'requires_personal_contribution' => $type->requires_personal_contribution,
                'is_active' => $type->is_active, 'projects_count' => $type->projects_count,
                'criteria' => $type->criteria->map(fn (ProjectTypeCriterion $c) => [
                    'id' => $c->id, 'label' => $c->label, 'description' => $c->description, 'is_blocking' => $c->is_blocking,
                    'auto_check' => $c->auto_check, 'position' => $c->position, 'is_active' => $c->is_active,
                ])->values(),
            ]),
            // Bibliothèque de critères déjà définis pour d'autres types, réutilisables d'un clic.
            'criteriaLibrary' => ProjectTypeCriterion::query()->with('type:id,name')->orderBy('label')->get()
                ->unique(fn (ProjectTypeCriterion $c) => mb_strtolower(trim($c->label)).'|'.($c->auto_check ?? ''))
                ->map(fn (ProjectTypeCriterion $c) => [
                    'id' => $c->id, 'label' => $c->label, 'description' => $c->description, 'is_blocking' => $c->is_blocking,
                    'auto_check' => $c->auto_check, 'type_id' => $c->project_type_id, 'type_name' => $c->type?->name,
                ])->values(),
            'autoChecks' => collect(ProjectTypeDefaults::AUTO_CHECKS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'canManage' => $request->user()->can('manage project types'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validateType($request);
        $base = Str::slug($data['name'], '_') ?: 'type';
        $code = $base;
        for ($i = 2; ProjectType::where('code', $code)->exists(); $i++) $code = "{$base}_{$i}";
        $copyIds = $request->validate(['copy_criteria_ids' => ['nullable', 'array'], 'copy_criteria_ids.*' => ['integer', 'exists:project_type_criteria,id']])['copy_criteria_ids'] ?? [];
        $type = ProjectType::create([...$data, 'code' => $code, 'created_by' => $request->user()->id]);
        $copied = $this->copyCriteria($type, $copyIds);
        activity('projects')->performedOn($type)->causedBy($request->user())->event('project_type.created')->withProperties($data)->log('Type de projet créé : '.$type->name);

        return back()->with('success', $copied
            ? __('Type de projet créé avec :count critère(s) repris.', ['count' => $copied])
            : 'Type de projet créé. Ajoutez maintenant ses critères.');
    }

    public function update(Request $request, ProjectType $projectType): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validateType($request);
        $before = $projectType->only(array_keys($data));
        $projectType->update($data);
        activity('projects')->performedOn($projectType)->causedBy($request->user())->event('project_type.updated')
            ->withProperties(['before' => $before, 'after' => $data])->log('Type de projet modifié : '.$projectType->name);

        return back()->with('success', 'Type de projet mis à jour.');
    }

    public function destroy(Request $request, ProjectType $projectType): RedirectResponse
    {
        $this->authorizeManage($request);
        if ($projectType->projects()->withTrashed()->exists()) {
            return back()->with('error', 'Des projets utilisent ce type : désactivez-le plutôt.');
        }
        $name = $projectType->name;
        $projectType->delete();
        activity('projects')->causedBy($request->user())->event('project_type.deleted')->withProperties(['name' => $name])->log('Type de projet supprimé : '.$name);

        return back()->with('success', 'Type de projet supprimé.');
    }

    public function storeCriterion(Request $request, ProjectType $projectType): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validateCriterion($request);
        $criterion = $projectType->criteria()->create([...$data, 'position' => (int) $projectType->criteria()->max('position') + 1]);
        activity('projects')->performedOn($projectType)->causedBy($request->user())->event('project_type.criterion_created')
            ->withProperties($data)->log('Critère ajouté : '.$criterion->label);

        return back()->with('success', 'Critère ajouté. Il s’appliquera aux prochains dossiers déposés.');
    }

    /** Ajoute à un type des critères déjà définis pour d'autres types. */
    public function importCriteria(Request $request, ProjectType $projectType): RedirectResponse
    {
        $this->authorizeManage($request);
        $ids = $request->validate(['criteria_ids' => ['required', 'array', 'min:1'], 'criteria_ids.*' => ['integer', 'exists:project_type_criteria,id']])['criteria_ids'];
        $copied = $this->copyCriteria($projectType, $ids);
        activity('projects')->performedOn($projectType)->causedBy($request->user())->event('project_type.criteria_imported')
            ->withProperties(['source_ids' => $ids, 'copied' => $copied])->log('Critères repris d’autres types : '.$copied);

        return back()->with('success', $copied ? __(':count critère(s) ajouté(s).', ['count' => $copied]) : 'Ces critères figurent déjà dans ce type.');
    }

    /** Copie des critères (sans doublon de libellé) à la fin de la liste du type. */
    private function copyCriteria(ProjectType $type, array $ids): int
    {
        if (! $ids) return 0;
        $existing = $type->criteria()->pluck('label')->map(fn ($l) => mb_strtolower(trim($l)))->all();
        $position = (int) $type->criteria()->max('position');
        $copied = 0;
        $sources = ProjectTypeCriterion::whereIn('id', $ids)->orderBy('project_type_id')->orderBy('position')->get();
        foreach ($sources as $source) {
            $key = mb_strtolower(trim($source->label));
            if (in_array($key, $existing, true)) continue;
            $type->criteria()->create([
                'label' => $source->label, 'description' => $source->description, 'is_blocking' => $source->is_blocking,
                'auto_check' => $source->auto_check, 'is_active' => true, 'position' => ++$position,
            ]);
            $existing[] = $key;
            $copied++;
        }

        return $copied;
    }

    public function updateCriterion(Request $request, ProjectTypeCriterion $projectTypeCriterion): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validateCriterion($request);
        $projectTypeCriterion->update($data);
        activity('projects')->performedOn($projectTypeCriterion->type)->causedBy($request->user())->event('project_type.criterion_updated')
            ->withProperties($data)->log('Critère modifié : '.$projectTypeCriterion->label);

        return back()->with('success', 'Critère mis à jour. Les dossiers déjà déposés gardent leur liste d’origine.');
    }

    public function moveCriterion(Request $request, ProjectTypeCriterion $projectTypeCriterion): RedirectResponse
    {
        $this->authorizeManage($request);
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $siblings = $projectTypeCriterion->type->criteria()->get()->values();
        $index = $siblings->search(fn ($c) => $c->id === $projectTypeCriterion->id);
        $swap = $siblings->get($direction === 'up' ? $index - 1 : $index + 1);
        if ($swap) {
            $order = $siblings->pluck('id')->all();
            [$order[$index], $order[$siblings->search(fn ($c) => $c->id === $swap->id)]] = [$swap->id, $projectTypeCriterion->id];
            foreach ($order as $position => $id) ProjectTypeCriterion::whereKey($id)->update(['position' => $position + 1]);
        }

        return back();
    }

    public function destroyCriterion(Request $request, ProjectTypeCriterion $projectTypeCriterion): RedirectResponse
    {
        $this->authorizeManage($request);
        $label = $projectTypeCriterion->label;
        $type = $projectTypeCriterion->type;
        $projectTypeCriterion->delete();
        activity('projects')->performedOn($type)->causedBy($request->user())->event('project_type.criterion_deleted')
            ->withProperties(['label' => $label])->log('Critère supprimé : '.$label);

        return back()->with('success', 'Critère supprimé.');
    }

    private function validateType(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_loan' => ['boolean'],
            'requires_personal_contribution' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    private function validateCriterion(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_blocking' => ['boolean'],
            'auto_check' => ['nullable', Rule::in(array_keys(ProjectTypeDefaults::AUTO_CHECKS))],
            'is_active' => ['boolean'],
        ]);
    }
}
