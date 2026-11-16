<?php

namespace App\Support;

/**
 * Genre d'un membre : trois codes stables (female, male, other) ou vide.
 * Les anciennes saisies libres (« Masculin », « Femme », « Non précisé »…) sont converties.
 */
class Gender
{
    public const CODES = ['female', 'male', 'other'];

    public const LABELS = ['female' => 'Femme', 'male' => 'Homme', 'other' => 'Autre'];

    public static function normalize(mixed $value): ?string
    {
        $v = mb_strtolower(trim((string) $value));
        if ($v === '') return null;
        if (in_array($v, self::CODES, true)) return $v;

        return match (true) {
            in_array($v, ['m', 'h', 'homme', 'masculin', 'masculine', 'man', 'male'], true) => 'male',
            in_array($v, ['f', 'femme', 'féminin', 'feminin', 'féminine', 'feminine', 'woman', 'female'], true) => 'female',
            in_array($v, ['autre', 'other'], true) => 'other',
            default => null, // « Non précisé » et toute autre valeur : non renseigné
        };
    }

    public static function label(?string $code): ?string
    {
        return $code ? (self::LABELS[$code] ?? null) : null;
    }
}
