<?php

namespace App\Support;

use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Devise du fonds (paramètre « default_currency »).
 * Tous les montants sont stockés dans cette devise ; en changer convertit toutes les données
 * (voir App\Services\CurrencyConversionService).
 */
class FundCurrency
{
    public const SUPPORTED = [
        'USD' => ['label' => 'Dollar américain', 'symbol' => '$', 'decimals' => 2],
        'EUR' => ['label' => 'Euro', 'symbol' => '€', 'decimals' => 2],
        'XAF' => ['label' => 'Franc CFA (CEMAC)', 'symbol' => 'FCFA', 'decimals' => 0],
    ];

    /** Parité fixe garantie : 1 EUR = 655,957 XAF. */
    public const EUR_XAF = 655.957;

    private static ?string $code = null;

    public static function code(): string
    {
        if (self::$code === null) {
            $value = Schema::hasTable('application_settings')
                ? ApplicationSetting::where('key', 'default_currency')->value('value')
                : null;
            $value = strtoupper(trim((string) $value));
            self::$code = array_key_exists($value, self::SUPPORTED) ? $value : 'USD';
        }

        return self::$code;
    }

    public static function flush(): void
    {
        self::$code = null;
    }

    public static function symbol(?string $code = null): string
    {
        return self::SUPPORTED[$code ?? self::code()]['symbol'] ?? ($code ?? self::code());
    }

    public static function decimals(?string $code = null): int
    {
        return self::SUPPORTED[$code ?? self::code()]['decimals'] ?? 2;
    }

    /** « 1 234,50 $ », « 1 234 € », « 810 000 FCFA ». */
    public static function format(float|int|string|null $amount, ?string $code = null, ?int $decimals = null): string
    {
        $code = $code ? strtoupper($code) : self::code();
        $decimals ??= self::decimals($code);

        return number_format((float) ($amount ?? 0), $decimals, ',', ' ').' '.self::symbol($code);
    }

    /** Comme format(), sans décimales quand le montant est rond : « 75 $ », « 8,33 $ ». */
    public static function short(float|int|string|null $amount, ?string $code = null): string
    {
        $value = round((float) ($amount ?? 0), 2);

        return self::format($value, $code, fmod($value, 1.0) == 0.0 ? 0 : null);
    }

    /** Remplace le symbole « $ » d'un libellé par celui de la devise active : « Montant ($) » → « Montant (€) ». */
    public static function label(string $text): string
    {
        return self::code() === 'USD' ? $text : str_replace('$', self::symbol(), $text);
    }

    public static function options(): array
    {
        return collect(self::SUPPORTED)->map(fn ($c, $code) => ['value' => $code, 'label' => $c['label'], 'symbol' => $c['symbol']])->values()->all();
    }

    public static function shared(): array
    {
        $code = self::code();

        return ['code' => $code, 'symbol' => self::symbol($code), 'decimals' => self::decimals($code)];
    }

    /**
     * Taux proposé pour convertir 1 unité de $from en $to.
     * EUR ↔ XAF : parité fixe. Avec le dollar : cours du jour de la BCE (frankfurter.app), mis en cache 6 h.
     * Renvoie null si le cours n'est pas disponible ; l'administrateur saisit alors le taux.
     */
    public static function suggestedRate(string $from, string $to): ?float
    {
        if ($from === $to) return 1.0;
        if ($from === 'EUR' && $to === 'XAF') return self::EUR_XAF;
        if ($from === 'XAF' && $to === 'EUR') return round(1 / self::EUR_XAF, 8);

        $usdEur = self::usdToEur();
        if (! $usdEur) return null;

        return match ("{$from}>{$to}") {
            'USD>EUR' => $usdEur,
            'EUR>USD' => round(1 / $usdEur, 8),
            'USD>XAF' => round($usdEur * self::EUR_XAF, 6),
            'XAF>USD' => round(1 / ($usdEur * self::EUR_XAF), 10),
            default => null,
        };
    }

    private static function usdToEur(): ?float
    {
        if (app()->runningUnitTests()) return null;

        if ($cached = Cache::get('fund_currency.usd_eur')) return (float) $cached;
        try {
            $rate = Http::timeout(4)->get('https://api.frankfurter.app/latest', ['from' => 'USD', 'to' => 'EUR'])->json('rates.EUR');
        } catch (\Throwable) {
            return null;
        }
        if (! is_numeric($rate) || $rate <= 0) return null;
        Cache::put('fund_currency.usd_eur', (float) $rate, now()->addHours(6));

        return (float) $rate;
    }
}
