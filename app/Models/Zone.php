<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Zone géographique du fonds (Europe, Amérique…). Les trésoriers d'une zone sont facultatifs. */
class Zone extends Model
{
    protected $fillable = ['name', 'code', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function treasurers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'zone_user');
    }

    public function members(): HasMany
    {
        return $this->hasMany(MemberProfile::class);
    }
}
