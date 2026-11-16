<?php

namespace App\Support;

class ContributionFormatter
{
    public static function money(float|string|null $amount, ?string $currency = null): string
    {
        return FundCurrency::format($amount, $currency, 0);
    }

    public static function periodLabel(?int $month, ?int $year, ?string $label = null): string
    {
        if ($label) {
            return $label;
        }

        if ($month && $year) {
            $months = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

            return __($months[$month] ?? '') . " {$year}";
        }

        return '—';
    }
}
