<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'country_id',
        'zone_id',
        'family_branch_id',
        'phone',
        'city',
        'address',
        'profession',
        'bio',
        'date_of_birth',
        'gender',
        'languages',
        'member_since',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'member_since' => 'date',
            'languages' => 'array',
            'status' => MemberStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function familyBranch(): BelongsTo
    {
        return $this->belongsTo(FamilyBranch::class);
    }

    public function displayName(): string
    {
        return $this->user?->name ?? 'Membre';
    }
}
