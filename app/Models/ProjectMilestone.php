<?php

namespace App\Models;

use App\Enums\ProjectMilestoneStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMilestone extends Model
{
    protected $fillable = ['project_id', 'title', 'description', 'due_date', 'status', 'planned_amount', 'spent_amount', 'completed_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'status' => ProjectMilestoneStatus::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'planned_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
