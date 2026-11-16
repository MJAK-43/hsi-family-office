<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProgressReport extends Model
{
    protected $fillable = ['project_id', 'progress_percent', 'spent_amount', 'summary', 'risks', 'next_steps', 'reported_at', 'reported_by'];

    protected function casts(): array
    {
        return ['reported_at' => 'date', 'spent_amount' => 'decimal:2'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
}
