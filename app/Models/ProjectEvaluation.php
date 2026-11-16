<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectEvaluation extends Model
{
    use SoftDeletes;

    public const MAX_SCORE_PER_CRITERION = 20;

    public const MAX_TOTAL_SCORE = 120;

    protected $fillable = [
        'project_id',
        'evaluator_id',
        'profitability_score',
        'risk_score',
        'founder_score',
        'family_impact_score',
        'sustainability_score',
        'values_alignment_score',
        'total_score',
        'recommendation',
        'comment',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'evaluated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public static function calculateTotalScore(array $scores): int
    {
        return (int) array_sum([
            $scores['profitability_score'] ?? 0,
            $scores['risk_score'] ?? 0,
            $scores['founder_score'] ?? 0,
            $scores['family_impact_score'] ?? 0,
            $scores['sustainability_score'] ?? 0,
            $scores['values_alignment_score'] ?? 0,
        ]);
    }
}
