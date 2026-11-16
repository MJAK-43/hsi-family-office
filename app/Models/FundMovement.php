<?php

namespace App\Models;

use App\Enums\FundMovementStatus;
use App\Enums\FundMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FundMovement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fund_account_id',
        'related_fund_account_id',
        'contribution_payment_id',
        'project_id',
        'financial_operation_id',
        'operation_leg_key',
        'type',
        'status',
        'is_example',
        'amount',
        'currency',
        'direction',
        'label',
        'description',
        'reference',
        'movement_date',
        'validated_by',
        'validated_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_example' => 'boolean',
            'amount' => 'decimal:2',
            'type' => FundMovementType::class,
            'status' => FundMovementStatus::class,
            'movement_date' => 'date',
            'validated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FundAccount::class, 'fund_account_id');
    }

    public function relatedAccount(): BelongsTo
    {
        return $this->belongsTo(FundAccount::class, 'related_fund_account_id');
    }

    public function contributionPayment(): BelongsTo
    {
        return $this->belongsTo(ContributionPayment::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function financialOperation(): BelongsTo
    {
        return $this->belongsTo(FinancialOperation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', FundMovementStatus::Validated);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', FundMovementStatus::Cancelled);
    }
}
