<?php

namespace App\Models;

use App\Enums\ContributionFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContributionPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'amount',
        'currency',
        'frequency',
        'country_id',
        'is_default',
        'is_regulatory',
        'is_active',
        'auto_generate',
        'starts_on',
        'due_day',
        'grace_days',
        'last_generated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_default' => 'boolean',
            'is_regulatory' => 'boolean',
            'is_active' => 'boolean',
            'auto_generate' => 'boolean',
            'starts_on' => 'date',
            'last_generated_at' => 'datetime',
            'frequency' => ContributionFrequency::class,
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContributionPayment::class);
    }

    public function dues(): HasMany
    {
        return $this->hasMany(ContributionDue::class);
    }
}
