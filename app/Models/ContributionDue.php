<?php

namespace App\Models;

use App\Enums\ContributionDueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionDue extends Model
{
    protected $fillable = ['contribution_plan_id', 'user_id', 'period_key', 'period_label', 'amount', 'penalty_amount', 'carried_penalty', 'penalty_carried_to_id', 'penalty_waived', 'penalty_waiver_reason', 'penalty_waived_by', 'currency', 'due_date', 'status', 'contribution_payment_id', 'paid_at', 'reminded_at', 'waived_by', 'waiver_reason'];
    protected function casts(): array
    {
        return ['status' => ContributionDueStatus::class, 'due_date' => 'date', 'paid_at' => 'datetime', 'reminded_at' => 'datetime', 'amount' => 'decimal:2', 'penalty_amount' => 'decimal:2', 'carried_penalty' => 'decimal:2', 'penalty_waived' => 'boolean'];
    }
    public function plan(): BelongsTo { return $this->belongsTo(ContributionPlan::class, 'contribution_plan_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function payment(): BelongsTo { return $this->belongsTo(ContributionPayment::class, 'contribution_payment_id'); }
}
