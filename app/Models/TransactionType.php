<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Type de transaction du fonds et nombre de validations nécessaires
 * (par exemple 2 trésoriers pour un décaissement, 1 seul pour une cotisation).
 */
class TransactionType extends Model
{
    public const DIRECTIONS = ['in' => 'Entrée', 'out' => 'Sortie', 'internal' => 'Interne'];

    protected $fillable = ['code', 'name', 'description', 'direction', 'required_approvals', 'approver_roles', 'is_system', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return [
            'approver_roles' => 'array',
            'required_approvals' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Une personne peut-elle valider ce type de transaction ? */
    public function canBeApprovedBy(User $user): bool
    {
        return $user->hasAnyRole($this->approver_roles ?: ['treasurer', 'president', 'super_admin']);
    }
}
