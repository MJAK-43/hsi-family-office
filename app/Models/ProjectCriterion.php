<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Critère copié dans un dossier au moment du dépôt, puis vérifié par le comité. */
class ProjectCriterion extends Model
{
    public const STATUSES = [
        'pending' => 'À vérifier',
        'validated' => 'Validé',
        'not_validated' => 'Non validé',
        'not_applicable' => 'Non applicable',
    ];

    protected $table = 'project_criteria';

    protected $fillable = ['project_id', 'project_type_criterion_id', 'label', 'description', 'is_blocking', 'auto_check', 'position', 'status', 'auto_result', 'auto_detail', 'comment', 'checked_by', 'checked_at'];

    protected function casts(): array
    {
        return ['is_blocking' => 'boolean', 'checked_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
