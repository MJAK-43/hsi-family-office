<?php

namespace App\Support;

use Illuminate\Http\Request;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Langue par défaut d'un visiteur selon son pays :
 *  - pays anglophone            → anglais ;
 *  - pays bilingue FR / EN      → langue du navigateur (Canada : anglais par défaut, sinon français) ;
 *  - tout autre pays, ou inconnu → français.
 *
 * Le pays vient de l'en-tête Cloudflare « CF-IPCountry » s'il existe, sinon de la base locale
 * DB-IP Country Lite (database/geoip, licence CC BY 4.0 — https://db-ip.com). Aucune adresse IP
 * n'est envoyée à un service extérieur.
 */
class VisitorLocale
{
    public const DATABASE = 'geoip/dbip-country-lite.mmdb';

    /** Pays où l'anglais est la langue officielle ou d'usage (sans le français). */
    public const ENGLISH_COUNTRIES = [
        // Amérique du Nord, Caraïbes, Atlantique
        'US', 'GB', 'IE', 'JM', 'BS', 'BB', 'BZ', 'GY', 'TT', 'AG', 'DM', 'GD', 'KN', 'LC', 'VC',
        'AI', 'BM', 'KY', 'VG', 'VI', 'TC', 'MS', 'FK', 'GI', 'IM', 'JE', 'GG', 'SH', 'IO',
        // Afrique
        'NG', 'GH', 'LR', 'SL', 'GM', 'KE', 'UG', 'TZ', 'ZM', 'ZW', 'MW', 'BW', 'NA', 'ZA', 'LS', 'SZ', 'SS',
        // Asie, Océanie, Méditerranée
        'IN', 'PK', 'SG', 'PH', 'MT', 'AU', 'NZ', 'FJ', 'PG', 'SB', 'WS', 'TO', 'KI', 'TV', 'NR', 'MH',
        'FM', 'PW', 'AS', 'GU', 'MP', 'UM', 'NF', 'CX', 'CC', 'PN', 'CK', 'NU', 'TK',
    ];

    /** Pays bilingues français / anglais : la langue du navigateur départage. */
    public const BILINGUAL_COUNTRIES = ['CA' => 'en', 'CM' => 'fr', 'RW' => 'fr', 'SC' => 'fr', 'VU' => 'fr', 'MU' => 'fr'];

    public function forRequest(Request $request): string
    {
        $country = $this->country($request);

        if ($country === null) {
            return 'fr';
        }
        if (in_array($country, self::ENGLISH_COUNTRIES, true)) {
            return 'en';
        }
        if (isset(self::BILINGUAL_COUNTRIES[$country])) {
            $browser = $request->getLanguages() ? $request->getPreferredLanguage(['fr', 'en']) : null;

            return in_array($browser, ['fr', 'en'], true) && $this->browserNamesLanguage($request, $browser)
                ? $browser
                : self::BILINGUAL_COUNTRIES[$country];
        }

        return 'fr';
    }

    /** Code pays ISO (« FR », « US »…) du visiteur, ou null s'il est inconnu (réseau local, base absente…). */
    public function country(Request $request): ?string
    {
        $header = strtoupper((string) $request->header('CF-IPCountry'));
        if (preg_match('/^[A-Z]{2}$/', $header) && ! in_array($header, ['XX', 'T1'], true)) {
            return $header;
        }

        $ip = $request->ip();
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $path = database_path(self::DATABASE);
        if (! is_file($path)) {
            return null;
        }

        try {
            $reader = new Reader($path);
            $record = $reader->get($ip);
            $reader->close();
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $code = $record['country']['iso_code'] ?? null;

        return is_string($code) && preg_match('/^[A-Z]{2}$/', $code) ? $code : null;
    }

    /** Vrai si le navigateur demande explicitement cette langue (et non un simple repli). */
    private function browserNamesLanguage(Request $request, string $language): bool
    {
        foreach ($request->getLanguages() as $tag) {
            if (str_starts_with(strtolower($tag), $language)) {
                return true;
            }
        }

        return false;
    }
}
