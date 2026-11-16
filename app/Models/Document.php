<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = ['document_category_id', 'folder_id', 'title', 'slug', 'description', 'status', 'visibility', 'is_constitution', 'approval_vote_id', 'current_version_id', 'published_at', 'published_by', 'archived_at', 'archived_by', 'retention_until', 'created_by', 'updated_by'];
    protected function casts(): array
    {
        return ['status' => DocumentStatus::class, 'is_constitution' => 'boolean', 'published_at' => 'datetime', 'archived_at' => 'datetime', 'retention_until' => 'date'];
    }
    protected static function booted(): void
    {
        static::creating(fn (Document $document) => $document->slug ??= static::uniqueSlug($document->title));
    }
    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'document'; $slug = $base; $i = 1;
        while (static::withTrashed()->where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
        return $slug;
    }
    public function folder(): BelongsTo { return $this->belongsTo(DocumentFolder::class, 'folder_id'); }
    public function category(): BelongsTo { return $this->belongsTo(DocumentCategory::class, 'document_category_id'); }
    public function versions(): HasMany { return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number'); }
    public function currentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class, 'current_version_id'); }
    public function accesses(): HasMany { return $this->hasMany(DocumentAccess::class); }
    public function downloads(): HasMany { return $this->hasMany(DocumentDownload::class); }
    public function approvalVote(): BelongsTo { return $this->belongsTo(Vote::class, 'approval_vote_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function publisher(): BelongsTo { return $this->belongsTo(User::class, 'published_by'); }
    public function scopePublished(Builder $query): Builder { return $query->where('status', DocumentStatus::Published); }
}
