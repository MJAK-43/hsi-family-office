<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

class MemberRoleHelper
{
    /** Rôles qu'un gestionnaire de membres sans délégation de rôles peut attribuer. */
    private const DELEGABLE_ROLES = [
        'member',
        'guest',
    ];

    /** Ordre d'importance : le premier rôle d'une personne est celui affiché en priorité. */
    public const PRIORITY = [
        'super_admin', 'president', 'vice_president', 'treasurer', 'secretary',
        'investment_committee', 'solidarity_committee', 'academy_manager', 'member', 'guest',
    ];

    /** Trie des noms de rôles du plus important au moins important. */
    public static function sort(iterable $names): array
    {
        $names = array_values(array_unique(is_array($names) ? $names : collect($names)->all()));
        usort($names, function (string $a, string $b) {
            $ia = array_search($a, self::PRIORITY, true);
            $ib = array_search($b, self::PRIORITY, true);

            return [$ia === false ? 99 : $ia, $a] <=> [$ib === false ? 99 : $ib, $b];
        });

        return $names;
    }

    public static function primary(iterable $names): ?string
    {
        return self::sort($names)[0] ?? null;
    }

    /** « Président · Trésorier » */
    public static function labels(iterable $names): string
    {
        $sorted = self::sort($names);

        return $sorted ? implode(' · ', array_map(fn (string $name) => self::label($name), $sorted)) : self::label(null);
    }

    public static function assignableFor(User $actor, ?User $target = null): array
    {
        $currentRoles = $target ? $target->roles()->pluck('name')->all() : [];

        if ($target && $actor->is($target)) {
            return $currentRoles;
        }

        if ($actor->can('manage roles')) {
            return Role::orderBy('name')->pluck('name')->all();
        }

        return Role::query()
            ->whereIn('name', array_values(array_unique([
                ...self::DELEGABLE_ROLES,
                ...$currentRoles,
            ])))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public static function label(?string $role): string
    {
        return match ($role) {
            'super_admin' => 'Super administrateur',
            'president' => 'Président',
            'vice_president' => 'Vice-président',
            'treasurer' => 'Trésorier',
            'secretary' => 'Secrétaire',
            'investment_committee' => 'Comité investissement',
            'solidarity_committee' => 'Comité solidarité',
            'academy_manager' => 'Responsable Academy',
            'member' => 'Membre',
            'guest' => 'Observateur',
            default => $role ?? '—',
        };
    }

    /**
     * Règle de validation du champ « roles » (plusieurs rôles possibles) :
     * - uniquement des rôles que la personne connectée a le droit d'attribuer ;
     * - on ne modifie pas ses propres rôles ;
     * - le dernier super-administrateur garde son rôle.
     */
    public static function rolesRule(User $actor, ?User $target = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($actor, $target): void {
            $value = array_values(array_filter((array) $value, 'is_string'));
            $current = $target ? $target->roles()->pluck('name')->all() : [];

            if ($target && $actor->is($target)) {
                if (self::sort($value) !== self::sort($current)) {
                    $fail('Vous ne pouvez pas modifier vos propres rôles.');
                }

                return;
            }

            if (array_diff($value, self::assignableFor($actor, $target))) {
                $fail('Vous n\'êtes pas autorisé à attribuer ce rôle.');

                return;
            }

            if (in_array('super_admin', $current, true) && ! in_array('super_admin', $value, true)
                && User::role('super_admin')->count() <= 1) {
                $fail('Le dernier super-administrateur doit conserver son rôle.');
            }
        };
    }

    public static function optionsFor(User $actor, ?User $target = null): array
    {
        return collect(self::assignableFor($actor, $target))
            ->map(fn (string $name) => [
                'name' => $name,
                'label' => self::label($name),
            ])
            ->values()
            ->all();
    }
}
