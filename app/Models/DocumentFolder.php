<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentFolder extends Model
{
    protected $fillable = ['parent_id', 'name', 'color', 'created_by'];

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('name'); }
    public function documents(): HasMany { return $this->hasMany(Document::class, 'folder_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /** Chemin depuis la racine : [racine, …, ce dossier]. */
    public function ancestry(): array
    {
        $path = [];
        $folder = $this;
        $guard = 0;
        while ($folder && $guard++ < 50) {
            array_unshift($path, $folder);
            $folder = $folder->parent;
        }

        return $path;
    }

    /** Vrai si $candidate est ce dossier ou l'un de ses sous-dossiers (pour interdire les déplacements en boucle). */
    public function containsOrIs(?self $candidate): bool
    {
        $guard = 0;
        while ($candidate && $guard++ < 50) {
            if ($candidate->id === $this->id) return true;
            $candidate = $candidate->parent;
        }

        return false;
    }
}
