<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rapport figé et partagé avec les membres (rapport trimestriel, section 6). */
class SharedReport extends Model
{
    protected $fillable = ['title', 'kind', 'period_start', 'period_end', 'data', 'comment', 'created_by', 'shared_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'data' => 'array', 'shared_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
