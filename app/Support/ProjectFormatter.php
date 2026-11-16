<?php

namespace App\Support;

class ProjectFormatter
{
    public static function money(float|string|null $amount, ?string $currency = null): string
    {
        $code = $currency ? strtoupper($currency) : FundCurrency::code();

        return FundCurrency::format($amount, $code, FundCurrency::decimals($code));
    }
}
