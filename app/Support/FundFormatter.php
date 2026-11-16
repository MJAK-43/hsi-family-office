<?php

namespace App\Support;

class FundFormatter
{
    public static function money(float|string|null $amount, ?string $currency = null): string
    {
        return FundCurrency::format($amount, $currency, 0);
    }
}
