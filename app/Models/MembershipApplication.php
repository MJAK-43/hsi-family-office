<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipApplication extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REFUSED = 'refused';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'En attente',
        self::STATUS_ACCEPTED => 'Acceptée',
        self::STATUS_REFUSED => 'Refusée',
    ];

    public const FAMILY_LINKS = [
        'enfant' => 'Enfant d\'un membre',
        'petit_enfant' => 'Petit-enfant d\'un membre',
        'frere_soeur' => 'Frère ou sœur d\'un membre',
        'neveu_niece' => 'Neveu ou nièce d\'un membre',
        'cousin' => 'Cousin ou cousine d\'un membre',
        'conjoint' => 'Conjoint ou conjointe d\'un membre',
        'autre' => 'Autre lien familial',
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'google_id',
        'phone',
        'country_id',
        'country',
        'city',
        'address',
        'profession',
        'date_of_birth',
        'family_link',
        'locale',
        'sponsor_name',
        'message',
        'status',
        'user_id',
        'reviewed_by',
        'reviewed_at',
        'decision_note',
        'ip_address',
    ];

    protected $hidden = ['password', 'google_id'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'date_of_birth' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function familyLinkLabel(): ?string
    {
        if (! $this->family_link) {
            return null;
        }

        return self::FAMILY_LINKS[$this->family_link] ?? $this->family_link;
    }
}
