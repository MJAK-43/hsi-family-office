<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFolder;
use App\Models\DocumentDownload;
use App\Models\DocumentVersion;
use App\Models\PaymentProof;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Models\Vote;
use App\Services\DocumentService;
use App\Support\DocumentAuthorization;
use App\Support\FilePreview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $service) {}

    /**
     * Explorateur de la bibliothèque : dossiers créés par les gestionnaires, plus deux dossiers
     * virtuels en lecture seule (« Dossiers de projets » et « Preuves de paiement »).
     * ?folder= vide (racine) | id | projects | project-{id} | proofs ; ?search= recherche partout.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view document library'), 403);
        $user = $request->user();
        $search = trim((string) $request->search);
        $folderParam = (string) $request->query('folder', '');
        $canManage = DocumentAuthorization::canManage($user);
        $folders = collect();
        $files = collect();
        $breadcrumbs = [['key' => '', 'label' => 'Bibliothèque']];
        $location = ['key' => '', 'type' => 'root', 'id' => null, 'name' => 'Bibliothèque', 'readonly' => ! $canManage];

        $libraryQuery = fn () => DocumentAuthorization::visibleQuery($user)->with(['category', 'currentVersion', 'creator'])
            ->when($request->category_id, fn ($q, $id) => $q->where('document_category_id', $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status));

        if ($search !== '') {
            $location = ['key' => '', 'type' => 'search', 'id' => null, 'name' => 'Résultats', 'readonly' => true];
            $files->push(...$libraryQuery()->where(fn ($s) => $s->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"))
                ->latest('updated_at')->limit(200)->get()->map(fn (Document $d) => $this->formatDocument($d)));
            if (! $request->category_id && ! $request->status) {
                $files->push(...$this->projectDocumentsQuery($user)->where(fn ($s) => $s->where('title', 'like', "%{$search}%")->orWhere('original_name', 'like', "%{$search}%"))
                    ->latest()->limit(200)->get()->map(fn (ProjectDocument $d) => $this->formatProjectDocument($d)));
                $files->push(...$this->proofsQuery($user)->where('original_name', 'like', "%{$search}%")
                    ->latest()->limit(200)->get()->map(fn (PaymentProof $p) => $this->formatProof($p)));
            }
        } elseif ($folderParam === 'projects') {
            $breadcrumbs[] = ['key' => 'projects', 'label' => 'Dossiers de projets'];
            $location = ['key' => 'projects', 'type' => 'projects', 'id' => null, 'name' => 'Dossiers de projets', 'readonly' => true];
            $counts = $this->projectDocumentsQuery($user)->selectRaw('project_id, count(*) as total, max(project_documents.updated_at) as last_update')->groupBy('project_id')->get()->keyBy('project_id');
            $projects = Project::query()->whereIn('id', $counts->keys())->with('owner:id,name')->orderBy('title')->get(['id', 'title', 'owner_id']);
            $folders = $projects->map(fn (Project $p) => [
                'key' => 'project-'.$p->id, 'id' => null, 'type' => 'project', 'name' => $p->title, 'subtitle' => $p->owner?->name,
                'count' => (int) ($counts[$p->id]->total ?? 0), 'editable' => false, 'droppable' => false, 'color' => 'blue',
            ]);
        } elseif (str_starts_with($folderParam, 'project-')) {
            $project = Project::findOrFail((int) substr($folderParam, 8));
            abort_unless(\App\Support\ProjectAccess::canViewProject($user, $project), 403);
            $breadcrumbs[] = ['key' => 'projects', 'label' => 'Dossiers de projets'];
            $breadcrumbs[] = ['key' => $folderParam, 'label' => $project->title];
            $location = ['key' => $folderParam, 'type' => 'project', 'id' => $project->id, 'name' => $project->title, 'readonly' => true, 'show_url' => route('projects.show', $project)];
            $files->push(...$this->projectDocumentsQuery($user)->where('project_id', $project->id)->latest()->get()->map(fn (ProjectDocument $d) => $this->formatProjectDocument($d)));
        } elseif ($folderParam === 'proofs') {
            $breadcrumbs[] = ['key' => 'proofs', 'label' => 'Preuves de paiement'];
            $location = ['key' => 'proofs', 'type' => 'proofs', 'id' => null, 'name' => 'Preuves de paiement', 'readonly' => true];
            $files->push(...$this->proofsQuery($user)->latest()->limit(500)->get()->map(fn (PaymentProof $p) => $this->formatProof($p)));
        } else {
            $folder = $folderParam !== '' ? DocumentFolder::with('parent')->findOrFail((int) $folderParam) : null;
            if ($folder) {
                foreach ($folder->ancestry() as $ancestor) $breadcrumbs[] = ['key' => (string) $ancestor->id, 'label' => $ancestor->name];
                $location = ['key' => (string) $folder->id, 'type' => 'folder', 'id' => $folder->id, 'name' => $folder->name, 'readonly' => ! $canManage, 'parent_key' => (string) ($folder->parent_id ?? '')];
            }
            $children = DocumentFolder::query()->where('parent_id', $folder?->id)->withCount('children')->orderBy('name')->get();
            $docCounts = DocumentAuthorization::visibleQuery($user)->whereIn('folder_id', $children->pluck('id'))->selectRaw('folder_id, count(*) as total')->groupBy('folder_id')->pluck('total', 'folder_id');
            $folders = $children->map(fn (DocumentFolder $f) => [
                'key' => (string) $f->id, 'id' => $f->id, 'type' => 'folder', 'name' => $f->name, 'subtitle' => null,
                'count' => (int) ($docCounts[$f->id] ?? 0) + $f->children_count, 'editable' => $canManage, 'droppable' => $canManage, 'color' => $f->color ?: 'gold',
            ]);
            if (! $folder && ! $request->category_id && ! $request->status) {
                $projectCount = $this->projectDocumentsQuery($user)->count();
                $proofCount = $this->proofsQuery($user)->count();
                if ($projectCount) $folders->push(['key' => 'projects', 'id' => null, 'type' => 'projects', 'name' => 'Dossiers de projets', 'subtitle' => 'Pièces jointes des projets', 'count' => $projectCount, 'editable' => false, 'droppable' => false, 'color' => 'blue', 'system' => true]);
                if ($proofCount) $folders->push(['key' => 'proofs', 'id' => null, 'type' => 'proofs', 'name' => 'Preuves de paiement', 'subtitle' => 'Justificatifs de cotisation', 'count' => $proofCount, 'editable' => false, 'droppable' => false, 'color' => 'green', 'system' => true]);
            }
            $files->push(...$libraryQuery()->where('folder_id', $folder?->id)->latest('updated_at')->get()->map(fn (Document $d) => $this->formatDocument($d)));
        }

        $visible = DocumentAuthorization::visibleQuery($user);

        return Inertia::render('Documents/Index', [
            'folders' => $folders->values()->all(),
            'files' => $files->sortByDesc('updated_sort')->values()->all(),
            'location' => $location,
            'breadcrumbs' => $breadcrumbs,
            'tree' => $canManage ? DocumentFolder::orderBy('name')->get(['id', 'parent_id', 'name']) : [],
            'filters' => $request->only(['search', 'category_id', 'status', 'folder']),
            'categories' => DocumentCategory::activeOptions(),
            'statuses' => DocumentStatus::options(),
            'stats' => [
                'total' => (clone $visible)->count() + $this->projectDocumentsQuery($user)->count() + $this->proofsQuery($user)->count(),
                'published' => (clone $visible)->where('status', DocumentStatus::Published)->count(),
                'review' => (clone $visible)->where('status', DocumentStatus::InReview)->count(),
                'folders' => DocumentFolder::count(),
            ],
            'can' => ['manage' => $canManage, 'manageCategories' => $user->can('manage document categories')],
        ]);
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        abort_unless(DocumentAuthorization::canManage($request->user()), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'parent_id' => ['nullable', 'integer', 'exists:document_folders,id']]);
        if (DocumentFolder::where('parent_id', $data['parent_id'] ?? null)->where('name', $data['name'])->exists()) {
            return back()->withErrors(['name' => 'Un dossier porte déjà ce nom ici.']);
        }
        $folder = DocumentFolder::create([...$data, 'created_by' => $request->user()->id]);
        activity('documents')->performedOn($folder)->causedBy($request->user())->event('document.folder.created')->log('Dossier créé : '.$folder->name);

        return back()->with('success', 'Dossier créé.');
    }

    public function updateFolder(Request $request, DocumentFolder $documentFolder): RedirectResponse
    {
        abort_unless(DocumentAuthorization::canManage($request->user()), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:document_folders,id'],
            'color' => ['sometimes', 'nullable', 'in:gold,blue,green,red,purple,slate'],
        ]);
        if (array_key_exists('parent_id', $data) && $data['parent_id'] && $documentFolder->containsOrIs(DocumentFolder::find($data['parent_id']))) {
            return back()->withErrors(['folder' => 'Impossible de déplacer un dossier dans lui-même.']);
        }
        $parent = array_key_exists('parent_id', $data) ? $data['parent_id'] : $documentFolder->parent_id;
        $name = $data['name'] ?? $documentFolder->name;
        if (DocumentFolder::where('parent_id', $parent)->where('name', $name)->whereKeyNot($documentFolder->id)->exists()) {
            return back()->withErrors(['folder' => 'Un dossier porte déjà ce nom à cet endroit.']);
        }
        $documentFolder->update($data);
        activity('documents')->performedOn($documentFolder)->causedBy($request->user())->event('document.folder.updated')->withProperties($data)->log('Dossier modifié : '.$documentFolder->name);

        return back()->with('success', array_key_exists('parent_id', $data) ? 'Dossier déplacé.' : 'Dossier mis à jour.');
    }

    public function destroyFolder(Request $request, DocumentFolder $documentFolder): RedirectResponse
    {
        abort_unless(DocumentAuthorization::canManage($request->user()), 403);
        if ($documentFolder->children()->exists() || Document::withTrashed()->where('folder_id', $documentFolder->id)->whereNull('deleted_at')->exists()) {
            return back()->withErrors(['folder' => 'Videz le dossier (documents et sous-dossiers) avant de le supprimer.']);
        }
        $name = $documentFolder->name;
        $documentFolder->delete();
        activity('documents')->causedBy($request->user())->event('document.folder.deleted')->withProperties(['name' => $name])->log('Dossier supprimé : '.$name);

        return back()->with('success', 'Dossier supprimé.');
    }

    public function move(Request $request, Document $document): RedirectResponse
    {
        abort_unless(DocumentAuthorization::canManage($request->user()), 403);
        $data = $request->validate(['folder_id' => ['nullable', 'integer', 'exists:document_folders,id']]);
        $document->update(['folder_id' => $data['folder_id'] ?? null, 'updated_by' => $request->user()->id]);
        activity('documents')->performedOn($document)->causedBy($request->user())->event('document.moved')->withProperties($data)->log('Document déplacé');

        return back()->with('success', 'Document déplacé.');
    }

    /** Dépôt rapide de plusieurs fichiers (glisser-déposer depuis l'ordinateur). */
    public function upload(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_unless(DocumentAuthorization::canManage($request->user()), 403);
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            // Chemins relatifs (« Dossier/Sous-dossier/fichier.pdf ») quand un dossier entier est importé.
            'paths' => ['nullable', 'array'],
            'paths.*' => ['nullable', 'string', 'max:1000'],
            'files.*' => $this->fileRules(),
            'folder_id' => ['nullable', 'integer', 'exists:document_folders,id'],
            'document_category_id' => ['nullable', 'exists:document_categories,id'],
            'visibility' => ['required', 'in:public,members,restricted'],
        ], ['files.*.mimes' => 'Format non autorisé (PDF, images, Office, texte).', 'files.*.max' => 'Chaque fichier doit faire moins de 20 Mo.']);
        $categoryId = $data['document_category_id'] ?? DocumentCategory::fallbackId();
        $folderCache = [];
        $createdFolders = 0;
        foreach (array_values($request->file('files')) as $i => $file) {
            $title = Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Document', 170, '');
            $folderId = $this->folderForPath($data['folder_id'] ?? null, $data['paths'][$i] ?? null, $request->user(), $folderCache, $createdFolders);
            $this->service->create([
                'document_category_id' => $categoryId, 'folder_id' => $folderId,
                'title' => $title, 'visibility' => $data['visibility'], 'is_constitution' => false, 'change_note' => 'Version initiale',
            ], $file, $request->user());
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['documents' => count($request->file('files')), 'folders' => $createdFolders]);
        }

        return back()->with('success', trans_choice('{1} 1 document ajouté.|[2,*] :count documents ajoutés.', count($request->file('files')), ['count' => count($request->file('files'))]));
    }

    /** Recrée l'arborescence d'un dossier importé (« A/B/fichier.pdf » → dossiers A puis B) et renvoie le dossier du fichier. */
    private function folderForPath(?int $baseId, ?string $path, User $user, array &$cache, int &$created): ?int
    {
        $segments = array_values(array_filter(
            array_map(fn ($s) => Str::limit(trim($s), 120, ''), explode('/', str_replace('\\', '/', (string) $path))),
            fn ($s) => $s !== '' && $s !== '.' && $s !== '..',
        ));
        array_pop($segments); // le dernier élément est le nom du fichier
        $parentId = $baseId;
        foreach (array_slice($segments, 0, 10) as $name) {
            $key = ($parentId ?? 'root').'/'.$name;
            if (! isset($cache[$key])) {
                $folder = DocumentFolder::where('parent_id', $parentId)->where('name', $name)->first();
                if (! $folder) {
                    $folder = DocumentFolder::create(['parent_id' => $parentId, 'name' => $name, 'created_by' => $user->id]);
                    activity('documents')->performedOn($folder)->causedBy($user)->event('document.folder.created')->log('Dossier importé : '.$name);
                    $created++;
                }
                $cache[$key] = $folder->id;
            }
            $parentId = $cache[$key];
        }

        return $parentId;
    }

    private function projectDocumentsQuery(User $user)
    {
        $internal = $user->hasAnyRole(['investment_committee', 'president', 'secretary', 'treasurer', 'super_admin']);

        return ProjectDocument::query()->with(['project.owner', 'uploader'])
            ->when(! $internal, fn ($q) => $q->where('visibility', '!=', 'committee')->whereHas('project', fn ($p) => $p->where('owner_id', $user->id)));
    }

    private function proofsQuery(User $user)
    {
        return PaymentProof::query()->with(['payment.user', 'uploader'])
            ->when(! $user->can('view all contribution payments') && ! $user->can('validate contribution payments'), fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('user_id', $user->id)));
    }

    private function formatProjectDocument(ProjectDocument $document): array
    {
        $name = $document->original_name;

        return [
            'key' => 'project-'.$document->id, 'id' => $document->id, 'source' => 'projects', 'source_label' => 'Projet',
            'title' => $document->title ?: $name, 'file_name' => $name, 'ext' => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
            'preview' => FilePreview::kind($name), 'category' => $document->type?->label(),
            'status' => 'published', 'status_label' => $document->visibility === 'committee' ? 'Interne au comité' : 'Disponible', 'status_variant' => $document->visibility === 'committee' ? 'warning' : 'success',
            'version' => '—', 'size' => $this->fileSize($document->file_size), 'updated_at' => $document->updated_at?->format('d/m/Y H:i'), 'updated_sort' => $document->updated_at?->timestamp,
            'owner' => $document->project?->title, 'show_url' => route('projects.show', $document->project_id),
            'download_url' => route('projects.documents.download', $document), 'preview_url' => route('projects.documents.download', [$document, 'inline' => 1]),
            'movable' => false,
        ];
    }

    private function formatProof(PaymentProof $proof): array
    {
        $name = $proof->original_name;

        return [
            'key' => 'proof-'.$proof->id, 'id' => $proof->id, 'source' => 'proofs', 'source_label' => 'Preuve de paiement',
            'title' => $name, 'file_name' => $name, 'ext' => strtolower(pathinfo($name, PATHINFO_EXTENSION)), 'preview' => FilePreview::kind($name),
            'category' => 'Cotisations', 'status' => 'published', 'status_label' => 'Disponible', 'status_variant' => 'success', 'version' => '—',
            'size' => $this->fileSize($proof->file_size), 'updated_at' => $proof->created_at?->format('d/m/Y H:i'), 'updated_sort' => $proof->created_at?->timestamp,
            'owner' => $proof->payment?->user?->name, 'show_url' => route('contributions.show', $proof->contribution_payment_id),
            'download_url' => route('contributions.proofs.download', $proof), 'preview_url' => route('contributions.proofs.download', [$proof, 'inline' => 1]),
            'movable' => false,
        ];
    }

    public function create(Request $request): Response
    {
        abort_unless(DocumentAuthorization::canManage($request->user()), 403);
        return Inertia::render('Documents/Create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(DocumentAuthorization::canManage($request->user()), 403);
        $data = $request->validate($this->documentRules());
        $data['document_category_id'] ??= DocumentCategory::fallbackId();
        $document = $this->service->create(collect($data)->except('file')->all(), $request->file('file'), $request->user());
        return redirect()->route('documents.show', $document)->with('success', 'Document et première version créés.');
    }

    public function show(Request $request, Document $document): Response
    {
        $document->load(['category', 'versions.uploader', 'currentVersion', 'creator', 'publisher', 'approvalVote', 'accesses.user', 'accesses.role']);
        abort_unless(DocumentAuthorization::canView($request->user(), $document), 403);
        $activities = $request->user()->can('view document audit') ? Activity::query()->with('causer')->where('subject_type', Document::class)->where('subject_id', $document->id)->latest()->limit(30)->get()->map(fn ($a) => ['description' => $a->description, 'causer' => $a->causer?->name ?? 'Système', 'date' => $a->created_at?->format('d/m/Y H:i')]) : [];
        return Inertia::render('Documents/Show', [
            'document' => [...$this->formatDocument($document, true),
                'description' => $document->description, 'visibility' => $document->visibility, 'is_constitution' => $document->is_constitution,
                'approval_vote' => $document->approvalVote?->title, 'approval_vote_id' => $document->approval_vote_id,
                'constitution_blocked' => $document->is_constitution && ! ($document->approvalVote && $document->approvalVote->status === \App\Enums\VoteStatus::Closed && $document->approvalVote->result === \App\Enums\VoteResult::Approved),
                'retention_until' => $document->retention_until?->format('d/m/Y'),
                'versions' => $document->versions->map(fn (DocumentVersion $version) => [
                    'id' => $version->id, 'number' => $version->version_number, 'name' => $version->original_name,
                    'size' => $this->fileSize($version->file_size), 'sha256' => $version->sha256, 'scan_status' => $version->scan_status,
                    'note' => $version->change_note, 'uploader' => $version->uploader?->name, 'date' => $version->created_at?->format('d/m/Y H:i'),
                    'download_url' => route('documents.versions.download', [$document, $version]),
                ]),
                'role_ids' => $document->accesses->pluck('role_id')->filter()->values(),
                'user_ids' => $document->accesses->pluck('user_id')->filter()->values(),
            ],
            'activities' => $activities,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'approvalVotes' => $document->is_constitution ? Vote::closed()->where('result', 'approved')->orderByDesc('closed_at')->get(['id', 'title']) : [],
            'can' => ['manage' => DocumentAuthorization::canManage($request->user()), 'publish' => DocumentAuthorization::canPublish($request->user()), 'manageAccess' => DocumentAuthorization::canManageAccess($request->user()), 'download' => DocumentAuthorization::canDownload($request->user(), $document)],
        ]);
    }

    /** Constitution : lier le vote d'approbation, ou retirer la mention « Constitution » si elle a été cochée par erreur. */
    public function updateConstitution(Request $request, Document $document): RedirectResponse
    {
        abort_unless(DocumentAuthorization::canManage($request->user()), 403);
        $data = $request->validate([
            'is_constitution' => ['required', 'boolean'],
            'approval_vote_id' => ['nullable', 'required_if:is_constitution,1', 'exists:votes,id'],
        ], ['approval_vote_id.required_if' => 'Choisissez le vote d’approbation.']);
        $document->update([
            'is_constitution' => (bool) $data['is_constitution'],
            'approval_vote_id' => $data['is_constitution'] ? $data['approval_vote_id'] : null,
            'updated_by' => $request->user()->id,
        ]);
        activity('documents')->performedOn($document)->causedBy($request->user())->event('document.constitution.updated')
            ->withProperties($data)->log($data['is_constitution'] ? 'Vote d’approbation lié au document' : 'Mention « Constitution » retirée');

        return back()->with('success', $data['is_constitution'] ? 'Vote d’approbation lié : le document peut être publié.' : 'Le document n’est plus marqué comme Constitution : il peut être publié.');
    }

    public function addVersion(Request $request, Document $document): RedirectResponse
    {
        abort_unless(DocumentAuthorization::canManage($request->user()), 403);
        $data = $request->validate(['file' => $this->fileRules(), 'change_note' => ['nullable', 'string', 'max:2000']]);
        try { $this->service->addVersion($document, $request->file('file'), $request->user(), $data['change_note'] ?? null); }
        catch (\RuntimeException $e) { return back()->withErrors(['version' => $e->getMessage()]); }
        return back()->with('success', 'Nouvelle version ajoutée. Le document repasse en brouillon.');
    }

    public function submitReview(Request $request, Document $document): RedirectResponse { return $this->workflow($request, $document, 'submitForReview', 'Document soumis à validation.'); }
    public function publish(Request $request, Document $document): RedirectResponse
    {
        abort_unless(DocumentAuthorization::canPublish($request->user()), 403);
        try { $this->service->publish($document, $request->user()); } catch (\RuntimeException $e) { return back()->with('error', __($e->getMessage())); }
        return back()->with('success', 'Document publié.');
    }
    public function archive(Request $request, Document $document): RedirectResponse { return $this->workflow($request, $document, 'archive', 'Document archivé.'); }
    public function restore(Request $request, Document $document): RedirectResponse { return $this->workflow($request, $document, 'restore', 'Document restauré en brouillon.'); }

    public function updateAccess(Request $request, Document $document): RedirectResponse
    {
        abort_unless(DocumentAuthorization::canManageAccess($request->user()), 403);
        $data = $request->validate(['role_ids' => ['array'], 'role_ids.*' => ['integer', 'exists:roles,id'], 'user_ids' => ['array'], 'user_ids.*' => ['integer', 'exists:users,id']]);
        $this->service->syncAccess($document, $data['role_ids'] ?? [], $data['user_ids'] ?? [], $request->user());
        return back()->with('success', 'Droits d’accès mis à jour.');
    }

    public function download(Request $request, Document $document, ?DocumentVersion $documentVersion = null): \Symfony\Component\HttpFoundation\Response
    {
        $document->load('currentVersion'); abort_unless(DocumentAuthorization::canDownload($request->user(), $document), 403);
        $version = $documentVersion ?? $document->currentVersion;
        abort_unless($version && $version->document_id === $document->id && $version->scan_status === 'clean', 404);
        abort_unless(Storage::disk('local')->exists($version->file_path), 404);
        if (FilePreview::wantsInline() && FilePreview::kind($version->original_name)) {
            activity('documents')->performedOn($document)->causedBy($request->user())->event('document.previewed')->withProperties(['version' => $version->version_number])->log('Document consulté (aperçu)');

            return FilePreview::respond($version->file_path, $version->original_name);
        }
        DocumentDownload::create(['document_id' => $document->id, 'document_version_id' => $version->id, 'user_id' => $request->user()->id, 'ip_address' => $request->ip(), 'user_agent' => Str::limit((string) $request->userAgent(), 500, ''), 'downloaded_at' => now()]);
        activity('documents')->performedOn($document)->causedBy($request->user())->event('document.downloaded')->withProperties(['version' => $version->version_number])->log('Document téléchargé');
        return Storage::disk('local')->download($version->file_path, $version->original_name);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage document categories'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:document_categories,name'], 'description' => ['nullable', 'string', 'max:2000'], 'retention_days' => ['nullable', 'integer', 'min:1', 'max:36500']]);
        DocumentCategory::create([...$data, 'slug' => Str::slug($data['name']), 'is_active' => true, 'created_by' => $request->user()->id]);
        return back()->with('success', 'Catégorie créée.');
    }

    public function toggleCategory(Request $request, DocumentCategory $documentCategory): RedirectResponse
    {
        abort_unless($request->user()->can('manage document categories'), 403);
        $documentCategory->update(['is_active' => ! $documentCategory->is_active]);
        return back()->with('success', 'Catégorie mise à jour.');
    }

    private function workflow(Request $request, Document $document, string $method, string $message): RedirectResponse
    {
        abort_unless(DocumentAuthorization::canManage($request->user()), 403);
        try { $this->service->{$method}($document, $request->user()); } catch (\RuntimeException $e) { return back()->with('error', __($e->getMessage())); }
        return back()->with('success', $message);
    }

    private function documentRules(): array
    {
        return ['document_category_id' => ['nullable', 'exists:document_categories,id'], 'folder_id' => ['nullable', 'integer', 'exists:document_folders,id'], 'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:5000'], 'visibility' => ['required', 'in:public,members,restricted'], 'is_constitution' => ['boolean'], 'approval_vote_id' => ['nullable', 'exists:votes,id'], 'change_note' => ['nullable', 'string', 'max:2000'], 'file' => $this->fileRules()];
    }
    private function fileRules(): array { return ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,ppt,pptx,txt,csv']; }
    private function formOptions(): array
    {
        return ['folders' => DocumentFolder::orderBy('name')->get(['id', 'parent_id', 'name']), 'folderId' => request()->integer('folder') ?: null, 'categories' => DocumentCategory::activeOptions(), 'approvalVotes' => Vote::closed()->where('result', 'approved')->orderByDesc('closed_at')->get(['id', 'title'])];
    }
    private function formatDocument(Document $document, bool $detailed = false): array
    {
        $document->loadMissing(['category', 'currentVersion', 'creator']);
        return ['key' => 'library-'.$document->id, 'id' => $document->id, 'source' => 'library', 'source_label' => 'Bibliothèque', 'title' => $document->title, 'category' => $document->category?->name, 'status' => $document->status->value, 'status_label' => $document->status->label(), 'status_variant' => $document->status->color(), 'version' => $document->currentVersion ? 'v'.$document->currentVersion->version_number : '—', 'size' => $this->fileSize($document->currentVersion?->file_size), 'updated_at' => $document->updated_at?->format('d/m/Y H:i'), 'owner' => $document->creator?->name, 'show_url' => route('documents.show', $document), 'download_url' => $document->currentVersion ? route('documents.download', $document) : null,
            'file_name' => $document->currentVersion?->original_name, 'ext' => strtolower(pathinfo((string) $document->currentVersion?->original_name, PATHINFO_EXTENSION)),
            'preview' => $document->currentVersion?->scan_status === 'clean' ? FilePreview::kind($document->currentVersion?->original_name) : null,
            'preview_url' => $document->currentVersion ? route('documents.download', [$document, 'inline' => 1]) : null,
            'folder_id' => $document->folder_id, 'movable' => true, 'updated_sort' => $document->updated_at?->timestamp, 'description' => $detailed ? null : Str::limit((string) $document->description, 140)];
    }
    private function fileSize(?int $bytes): string
    {
        if (! $bytes) return '—';
        return $bytes < 1048576 ? max(1, (int) round($bytes / 1024)).' Ko' : round($bytes / 1048576, 1).' Mo';
    }
}
