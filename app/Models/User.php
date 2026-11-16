<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'policy_accepted_at' => 'datetime',
            'preferences' => 'array',
            'password' => 'hashed',
        ];
    }

    /** URL publique de la photo de profil, ou null si aucune photo. */
    /** Langue des emails envoyés à ce membre : son choix, sinon la langue de la page en cours. */
    public function preferredLocale(): string
    {
        return in_array($this->locale, ['fr', 'en'], true) ? $this->locale : app()->getLocale();
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    /** Zones dont la personne est trésorière. */
    public function treasuredZones(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'zone_user');
    }

    public function hasAcceptedCurrentPolicy(): bool
    {
        return $this->policy_accepted_at !== null
            && $this->policy_accepted_version === \App\Support\FundRules::get('policy_version');
    }

    public function memberProfile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function contributionPayments(): HasMany
    {
        return $this->hasMany(ContributionPayment::class);
    }

    public function contributionDues(): HasMany
    {
        return $this->hasMany(ContributionDue::class);
    }
}
