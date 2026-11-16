<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Type de projet (Prêt, Investissement…) et sa liste de critères. */
class ProjectType extends Model
{
    protected $fillable = ['name', 'code', 'description', 'is_loan', 'requires_personal_contribution', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_loan' => 'boolean', 'requires_personal_contribution' => 'boolean', 'is_active' => 'boolean'];
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(ProjectTypeCriterion::class)->orderBy('position')->orderBy('id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
