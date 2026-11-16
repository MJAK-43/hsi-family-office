<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoteOption extends Model
{
    protected $fillable = [
        'vote_id',
        'label',
        'value',
        'sort_order',
        'is_winning',
    ];

    protected function casts(): array
    {
        return [
            'is_winning' => 'boolean',
        ];
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }

    public function ballots(): HasMany
    {
        return $this->hasMany(VoteBallot::class);
    }
}
