<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoteConflict extends Model
{
    protected $fillable = ['vote_id', 'user_id', 'reason', 'status', 'declared_by', 'resolved_by', 'resolution_note', 'resolved_at'];
    protected function casts(): array { return ['resolved_at' => 'datetime']; }
    public function vote(): BelongsTo { return $this->belongsTo(Vote::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function declarer(): BelongsTo { return $this->belongsTo(User::class, 'declared_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
}
