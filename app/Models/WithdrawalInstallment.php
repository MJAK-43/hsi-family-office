<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalInstallment extends Model
{
    protected $fillable = ['withdrawal_request_id', 'due_date', 'amount', 'paid_at', 'approval_request_id', 'fund_movement_id'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'paid_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class, 'withdrawal_request_id');
    }
}
