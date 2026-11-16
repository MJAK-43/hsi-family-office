<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTypeCriterion extends Model
{
    protected $table = 'project_type_criteria';

    protected $fillable = ['project_type_id', 'label', 'description', 'is_blocking', 'auto_check', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_blocking' => 'boolean', 'is_active' => 'boolean'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }
}
