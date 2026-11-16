<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Échéance d'un prêt : capital + intérêts, avec pénalités de retard. */
class LoanInstallment extends Model
{
    public const STATUSES = ['pending' => 'À venir', 'partial' => 'Partiellement payée', 'paid' => 'Payée', 'late' => 'En retard'];

    protected $fillable = ['project_id', 'number', 'due_date', 'principal', 'interest', 'amount', 'paid_amount', 'penalty_amount', 'penalty_paid', 'status', 'paid_at', 'reminder_level', 'last_reminded_at'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date', 'paid_at' => 'datetime', 'last_reminded_at' => 'datetime',
            'principal' => 'decimal:2', 'interest' => 'decimal:2', 'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2', 'penalty_amount' => 'decimal:2', 'penalty_paid' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Reste dû sur l'échéance (hors pénalités). */
    public function remaining(): float
    {
        return max(0, round((float) $this->amount - (float) $this->paid_amount, 2));
    }

    public function remainingPenalty(): float
    {
        return max(0, round((float) $this->penalty_amount - (float) $this->penalty_paid, 2));
    }
}
