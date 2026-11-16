<?php

namespace App\Models;

use App\Enums\MajorityRule;
use App\Enums\VoteResult;
use App\Enums\VoteStatus;
use App\Enums\VoteType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Vote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'type',
        'project_id',
        'status',
        'opens_at',
        'closes_at',
        'quorum_percent',
        'quorum_absolute',
        'majority_rule',
        'allow_abstention',
        'ballot_visibility',
        'result',
        'winning_option_id',
        'result_summary',
        'eligible_count',
        'eligibility_locked_at',
        'ballots_count',
        'for_count',
        'against_count',
        'abstain_count',
        'created_by',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => VoteType::class,
            'status' => VoteStatus::class,
            'result' => VoteResult::class,
            'majority_rule' => MajorityRule::class,
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'closed_at' => 'datetime',
            'allow_abstention' => 'boolean',
            'eligibility_locked_at' => 'datetime',
            'result_summary' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Vote $vote) {
            if (empty($vote->slug)) {
                $vote->slug = static::generateUniqueSlug($vote->title);
            }
        });
    }

    public static function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 1;

        while (static::withTrashed()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(VoteOption::class)->orderBy('sort_order');
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(VoteBallot::class);
    }

    public function eligibilities(): HasMany
    {
        return $this->hasMany(VoteEligibility::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(VoteConflict::class);
    }

    public function winningOption(): BelongsTo
    {
        return $this->belongsTo(VoteOption::class, 'winning_option_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', VoteStatus::Open);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', VoteStatus::Scheduled);
    }

    public function hasSecretBallots(): bool
    {
        return $this->ballot_visibility === 'secret';
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', VoteStatus::Closed);
    }

    public function isOpen(): bool
    {
        return $this->status === VoteStatus::Open;
    }

    public function isExpired(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }

    public function participationPercent(): float
    {
        $eligible = (int) ($this->eligible_count ?? 0);

        return $eligible > 0
            ? round(((int) $this->ballots_count / $eligible) * 100, 1)
            : 0;
    }

    public function quorumReached(): bool
    {
        $eligible = (int) ($this->eligible_count ?? 0);
        $ballots = (int) $this->ballots_count;

        if ($eligible <= 0) {
            return false;
        }

        $percentOk = ($ballots / $eligible) * 100 >= (int) $this->quorum_percent;

        if ($this->quorum_absolute !== null) {
            return $percentOk && $ballots >= (int) $this->quorum_absolute;
        }

        return $percentOk;
    }
}
