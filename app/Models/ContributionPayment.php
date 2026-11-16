<?php

namespace App\Models;

use App\Enums\ContributionPaymentStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContributionPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'contribution_plan_id',
        'amount',
        'currency',
        'payment_month',
        'payment_year',
        'payment_period_label',
        'payment_method',
        'reference',
        'status',
        'is_example',
        'paid_at',
        'submitted_at',
        'validated_by',
        'validated_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_example' => 'boolean',
            'amount' => 'decimal:2',
            'status' => ContributionPaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContributionPlan::class, 'contribution_plan_id');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function due(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ContributionDue::class);
    }

    public function isEditableByMember(): bool
    {
        return in_array($this->status, [
            ContributionPaymentStatus::Pending,
            ContributionPaymentStatus::Rejected,
            ContributionPaymentStatus::Late,
        ], true);
    }

    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', ContributionPaymentStatus::Validated);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ContributionPaymentStatus::Pending);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', ContributionPaymentStatus::Rejected);
    }

    public function scopeLate(Builder $query): Builder
    {
        return $query->where('status', ContributionPaymentStatus::Late);
    }
}
