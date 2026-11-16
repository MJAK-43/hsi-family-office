<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundAllocationRuleItem extends Model
{
    protected $fillable = [
        'fund_allocation_rule_id',
        'fund_account_id',
        'percentage',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(FundAllocationRule::class, 'fund_allocation_rule_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FundAccount::class, 'fund_account_id');
    }
}
