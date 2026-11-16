<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Transaction en attente du nombre de validations prévu pour son type. */
class ApprovalRequest extends Model
{
    public const STATUSES = [
        'pending' => 'En attente de validation',
        'executed' => 'Validée et exécutée',
        'rejected' => 'Refusée',
        'cancelled' => 'Annulée',
        'failed' => 'Échec à l’exécution',
    ];

    protected $fillable = ['transaction_type_id', 'subject_type', 'subject_id', 'title', 'amount', 'currency', 'payload', 'status', 'required_approvals', 'approver_roles', 'requested_by', 'beneficiary_id', 'executed_at', 'rejected_by', 'rejected_reason', 'failure_reason', 'result'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'approver_roles' => 'array', 'result' => 'array', 'amount' => 'decimal:2', 'executed_at' => 'datetime', 'required_approvals' => 'integer'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class)->orderBy('created_at');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    public function approvalsCount(): int
    {
        return $this->decisions()->where('decision', 'approve')->count();
    }
}
