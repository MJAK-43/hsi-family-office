<?php

namespace App\Models;

use App\Enums\ProjectSector;
use App\Enums\ProjectStatus;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id',
        'project_type_id',
        'fund_account_id',
        'is_example',
        'resubmission_of_id',
        'repayment_plan',
        'loan_duration_months',
        'presentation_date',
        'committee_decision',
        'information_request',
        'decided_at',
        'loan_interest_rate',
        'loan_total_due',
        'first_due_date',
        'repaid_at',
        'title',
        'slug',
        'short_description',
        'description',
        'country_id',
        'city',
        'sector',
        'total_budget',
        'personal_contribution',
        'requested_amount',
        'currency',
        'expected_duration_months',
        'expected_roi_percentage',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'committee_decision_comment',
        'rejected_reason',
        'submitted_to_vote_at',
        'funded_at',
        'funded_by',
        'funded_amount',
        'started_at',
        'completed_at',
        'failed_at',
        'cancelled_at',
        'closure_note',
        'progress_percent',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_example' => 'boolean',
            'total_budget' => 'decimal:2',
            'personal_contribution' => 'decimal:2',
            'requested_amount' => 'decimal:2',
            'expected_roi_percentage' => 'decimal:2',
            'funded_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'submitted_to_vote_at' => 'datetime',
            'funded_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'presentation_date' => 'date',
            'decided_at' => 'datetime',
            'first_due_date' => 'date',
            'repaid_at' => 'datetime',
            'loan_total_due' => 'decimal:2',
            'status' => ProjectStatus::class,
            'sector' => ProjectSector::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->slug)) {
                $project->slug = static::generateUniqueSlug($project->title);
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function fundAccount(): BelongsTo
    {
        return $this->belongsTo(FundAccount::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function resubmissionOf(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'resubmission_of_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(ProjectCriterion::class)->orderBy('position')->orderBy('id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('number');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class)->latest('paid_on');
    }

    public function approvalRequests(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'subject')->latest();
    }

    public function isLoan(): bool
    {
        return (bool) $this->type?->is_loan;
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function funder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'funded_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ProjectEvaluation::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function fundMovements(): HasMany
    {
        return $this->hasMany(FundMovement::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('due_date');
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProjectProgressReport::class)->latest('reported_at');
    }

    public function activeVote(): HasOne
    {
        return $this->hasOne(Vote::class)->whereIn('status', ['draft', 'scheduled', 'open'])->latestOfMany();
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Draft);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Submitted);
    }

    public function scopeUnderReview(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::UnderReview);
    }

    public function scopeApprovedByCommittee(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::ApprovedByCommittee);
    }

    public function scopeRejectedByCommittee(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::RejectedByCommittee);
    }

    public function scopeSubmittedToVote(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::SubmittedToVote);
    }

    public function isEditableByOwner(): bool
    {
        return in_array($this->status, [
            ProjectStatus::Draft,
            ProjectStatus::NeedsInformation,
        ], true);
    }

    public function respectsFiftyFiftyRule(): bool
    {
        // Pour un prêt, pas d'apport personnel exigé : le plafond porte sur le solde du fonds.
        if ($this->type && ! $this->type->requires_personal_contribution) {
            return (float) $this->requested_amount > 0;
        }

        $total = Money::fromDecimal($this->total_budget, $this->currency);
        if ($total->compare(Money::zero($this->currency)) <= 0) {
            return false;
        }

        $personal = Money::fromDecimal($this->personal_contribution, $this->currency);
        $requested = Money::fromDecimal($this->requested_amount, $this->currency);

        return $personal->add($personal)->compare($total) >= 0
            && $requested->add($requested)->compare($total) <= 0
            && $personal->add($requested)->compare($total) <= 0;
    }

    public function personalContributionPercent(): float
    {
        $total = (float) $this->total_budget;

        return $total > 0 ? round(((float) $this->personal_contribution / $total) * 100, 1) : 0;
    }

    public function requestedAmountPercent(): float
    {
        $total = (float) $this->total_budget;

        return $total > 0 ? round(((float) $this->requested_amount / $total) * 100, 1) : 0;
    }

    public function averageCommitteeScore(): ?float
    {
        $avg = $this->evaluations()->avg('total_score');

        return $avg !== null ? round((float) $avg, 1) : null;
    }
}
