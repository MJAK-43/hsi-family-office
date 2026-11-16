<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Départ d'un membre (section 8 du règlement). */
class WithdrawalRequest extends Model
{
    public const STATUSES = ['requested' => 'Demandé', 'scheduled' => 'Calendrier communiqué', 'completed' => 'Remboursé intégralement', 'cancelled' => 'Annulé'];

    protected $fillable = ['user_id', 'reason', 'requested_on', 'effective_date', 'total_contributions', 'refund_percent', 'refund_amount', 'funds_engaged_in_loans', 'status', 'scheduled_at', 'scheduled_by', 'notes'];

    protected function casts(): array
    {
        return [
            'requested_on' => 'date', 'effective_date' => 'date', 'scheduled_at' => 'datetime',
            'total_contributions' => 'decimal:2', 'refund_percent' => 'decimal:2', 'refund_amount' => 'decimal:2',
            'funds_engaged_in_loans' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(WithdrawalInstallment::class)->orderBy('due_date');
    }
}
