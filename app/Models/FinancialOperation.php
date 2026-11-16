<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialOperation extends Model
{
    protected $fillable = [
        'operation_type',
        'idempotency_key',
        'request_hash',
        'status',
        'result',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FundMovement::class);
    }
}
