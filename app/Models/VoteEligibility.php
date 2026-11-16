<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoteEligibility extends Model
{
    protected $fillable = ['vote_id', 'user_id', 'is_eligible', 'weight', 'exclusion_reason'];
    protected function casts(): array { return ['is_eligible' => 'boolean']; }
    public function vote(): BelongsTo { return $this->belongsTo(Vote::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
