<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoteBallot extends Model
{
    protected $fillable = [
        'vote_id',
        'user_id',
        'vote_option_id',
        'comment',
        'cast_at',
    ];

    protected function casts(): array
    {
        return [
            'cast_at' => 'datetime',
        ];
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(Vote::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(VoteOption::class, 'vote_option_id');
    }
}
