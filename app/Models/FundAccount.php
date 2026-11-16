<?php

namespace App\Models;

use App\Enums\FundAccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FundAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'color',
        'sort_order',
        'currency',
        'current_balance',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'type' => FundAccountType::class,
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FundMovement::class);
    }

    public function allocationRuleItems(): HasMany
    {
        return $this->hasMany(FundAllocationRuleItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
