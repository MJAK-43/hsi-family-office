<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Vérification annuelle des comptes par 1 ou 2 membres (section 6). */
class AuditReview extends Model
{
    public const STATUSES = ['open' => 'En cours', 'confirmed' => 'Comptes confirmés', 'issues' => 'Anomalies signalées'];

    protected $fillable = ['year', 'status', 'conclusion', 'confirmed_at', 'confirmed_by', 'created_by'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime'];
    }

    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'audit_review_user');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
