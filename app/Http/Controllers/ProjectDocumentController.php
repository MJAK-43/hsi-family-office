<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectDocumentRequest;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Support\ProjectAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentController extends Controller
{
    public function store(StoreProjectDocumentRequest $request, Project $project): RedirectResponse
    {
        $file = $request->file('document');
        $originalName = basename($file->getClientOriginalName());
        $fileName = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('project-documents', $fileName, 'local');

        $visibility = ProjectAccess::canAttachCommitteeDocument($request->user(), $project) && $request->input('visibility') === 'committee' ? 'committee' : 'all';
        $document = ProjectDocument::create([
            'project_id' => $project->id,
            'type' => $request->input('type'),
            'visibility' => $visibility,
            'title' => $request->input('title'),
            'original_name' => $originalName,
            'file_name' => $fileName,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        activity('projects')
            ->performedOn($project)
            ->causedBy($request->user())
            ->event('project.document.uploaded')
            ->withProperties(['document_id' => $document->id, 'type' => $document->type?->value, 'visibility' => $visibility])
            ->log($visibility === 'committee' ? 'Pièce interne du comité ajoutée' : 'Document projet ajouté');

        return redirect()->route('projects.show', $project)
            ->with('success', 'Document ajouté avec succès.');
    }

    public function destroy(Request $request, ProjectDocument $projectDocument): RedirectResponse
    {
        $project = $projectDocument->project;
        abort_unless(ProjectAccess::canUploadDocument($request->user(), $project), 403);

        activity('projects')
            ->performedOn($project)
            ->causedBy($request->user())
            ->event('project.document.deleted')
            ->withProperties(['document_id' => $projectDocument->id])
            ->log('Document projet supprimé');

        $projectDocument->delete();

        return redirect()->route('projects.show', $project)
            ->with('success', 'Document supprimé.');
    }

    public function download(ProjectDocument $projectDocument): \Symfony\Component\HttpFoundation\Response
    {
        $projectDocument->load('project');
        $user = request()->user();

        abort_unless(ProjectAccess::canDownloadDocument($user, $projectDocument->project, $projectDocument), 403);
        abort_unless(Storage::disk('local')->exists($projectDocument->file_path), 404);
        if (\App\Support\FilePreview::wantsInline()) return \App\Support\FilePreview::respond($projectDocument->file_path, $projectDocument->original_name);

        return Storage::disk('local')->download(
            $projectDocument->file_path,
            $projectDocument->original_name,
        );
    }
}
