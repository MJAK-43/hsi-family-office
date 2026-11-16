<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\VoteResult;
use App\Enums\VoteStatus;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentService
{
    public function create(array $data, UploadedFile $file, User $user): Document
    {
        return DB::transaction(function () use ($data, $file, $user) {
            $document = Document::create([
                ...$data, 'status' => DocumentStatus::Draft, 'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            $version = $this->storeVersion($document, $file, $user, $data['change_note'] ?? 'Version initiale');
            $document->update(['current_version_id' => $version->id]);
            activity('documents')->performedOn($document)->causedBy($user)->event('document.created')
                ->withProperties(['version_id' => $version->id, 'sha256' => $version->sha256])->log('Document créé');
            return $document->fresh(['category', 'currentVersion']);
        });
    }

    public function addVersion(Document $document, UploadedFile $file, User $user, ?string $note): DocumentVersion
    {
        return DB::transaction(function () use ($document, $file, $user, $note) {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            if ($locked->status === DocumentStatus::Archived) throw new RuntimeException('Restaurez le document avant d’ajouter une version.');
            $version = $this->storeVersion($locked, $file, $user, $note);
            $locked->update([
                'current_version_id' => $version->id, 'status' => DocumentStatus::Draft,
                'published_at' => null, 'published_by' => null, 'updated_by' => $user->id,
            ]);
            activity('documents')->performedOn($locked)->causedBy($user)->event('document.version.created')
                ->withProperties(['version' => $version->version_number, 'sha256' => $version->sha256])->log('Nouvelle version documentaire ajoutée');
            return $version;
        });
    }

    public function submitForReview(Document $document, User $user): Document
    {
        if ($document->status !== DocumentStatus::Draft) throw new RuntimeException('Seul un brouillon peut être soumis à validation.');
        $document->update(['status' => DocumentStatus::InReview, 'updated_by' => $user->id]);
        activity('documents')->performedOn($document)->causedBy($user)->event('document.review.requested')->log('Document soumis à validation');
        return $document->fresh();
    }

    public function publish(Document $document, User $user): Document
    {
        if (! in_array($document->status, [DocumentStatus::Draft, DocumentStatus::InReview], true)) throw new RuntimeException('Ce document ne peut pas être publié dans son état actuel.');
        if (! $document->currentVersion || $document->currentVersion->scan_status !== 'clean') throw new RuntimeException('La version courante n’est pas téléchargeable.');
        if ($document->is_constitution) {
            $vote = $document->approvalVote;
            if (! $vote || $vote->status !== VoteStatus::Closed || $vote->result !== VoteResult::Approved) {
                throw new RuntimeException('La Constitution doit être liée à un vote clôturé et approuvé.');
            }
        }
        $retentionDays = $document->category?->retention_days;
        $document->update([
            'status' => DocumentStatus::Published, 'published_at' => now(), 'published_by' => $user->id,
            'archived_at' => null, 'archived_by' => null,
            'retention_until' => $retentionDays ? now()->addDays($retentionDays)->toDateString() : null,
            'updated_by' => $user->id,
        ]);
        activity('documents')->performedOn($document)->causedBy($user)->event('document.published')
            ->withProperties(['version' => $document->currentVersion->version_number])->log('Document publié');
        return $document->fresh();
    }

    public function archive(Document $document, User $user): Document
    {
        if ($document->status !== DocumentStatus::Published) throw new RuntimeException('Seul un document publié peut être archivé.');
        $document->update(['status' => DocumentStatus::Archived, 'archived_at' => now(), 'archived_by' => $user->id, 'updated_by' => $user->id]);
        activity('documents')->performedOn($document)->causedBy($user)->event('document.archived')->log('Document archivé');
        return $document->fresh();
    }

    public function restore(Document $document, User $user): Document
    {
        if ($document->status !== DocumentStatus::Archived) throw new RuntimeException('Ce document n’est pas archivé.');
        $document->update(['status' => DocumentStatus::Draft, 'archived_at' => null, 'archived_by' => null, 'updated_by' => $user->id]);
        activity('documents')->performedOn($document)->causedBy($user)->event('document.restored')->log('Document restauré en brouillon');
        return $document->fresh();
    }

    public function syncAccess(Document $document, array $roleIds, array $userIds, User $actor): void
    {
        DB::transaction(function () use ($document, $roleIds, $userIds, $actor) {
            $document->accesses()->delete();
            foreach (array_unique(array_map('intval', $roleIds)) as $roleId) $document->accesses()->create(['role_id' => $roleId, 'can_download' => true]);
            foreach (array_unique(array_map('intval', $userIds)) as $userId) $document->accesses()->create(['user_id' => $userId, 'can_download' => true]);
            activity('documents')->performedOn($document)->causedBy($actor)->event('document.access.updated')
                ->withProperties(['role_ids' => $roleIds, 'user_ids' => $userIds])->log('Accès au document mis à jour');
        });
    }

    private function storeVersion(Document $document, UploadedFile $file, User $user, ?string $note): DocumentVersion
    {
        $number = ((int) $document->versions()->max('version_number')) + 1;
        $extension = strtolower($file->getClientOriginalExtension());
        $name = Str::uuid().'.'.$extension;
        $path = $file->storeAs('global-documents/'.$document->id, $name, 'local');
        if (! $path) throw new RuntimeException('Le fichier n’a pas pu être stocké.');
        $absolute = Storage::disk('local')->path($path);
        return $document->versions()->create([
            'version_number' => $number, 'original_name' => basename($file->getClientOriginalName()),
            'file_name' => $name, 'file_path' => $path, 'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(), 'sha256' => hash_file('sha256', $absolute),
            'scan_status' => 'clean', 'change_note' => $note, 'uploaded_by' => $user->id,
        ]);
    }
}
