<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Country extends Model
{
    protected $fillable = [
        'code',
        'name',
        'flag_emoji',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Liste prête pour l'affichage : en anglais, les noms sont traduits (lang/en.json) et la liste
     * est retriée par ordre alphabétique ; en français, l'ordre de la base est conservé.
     */
    public static function forDisplay(Collection $countries): Collection
    {
        if (app()->getLocale() === 'fr') {
            return $countries->values();
        }

        return $countries
            ->each(fn (self $country) => $country->setAttribute('name', __($country->name)))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function memberProfiles(): HasMany
    {
        return $this->hasMany(MemberProfile::class);
    }
}
