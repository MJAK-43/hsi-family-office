<?php

namespace App\Http\Middleware;

use App\Support\VisitorLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Choisit la langue de l'interface, dans cet ordre :
 * 1. choix fait avec le sélecteur FR / EN (session) ;
 * 2. langue enregistrée sur le compte connecté ;
 * 3. langue du pays du visiteur : anglais dans les pays anglophones, français ailleurs
 *    (calculée une fois puis gardée en session, voir App\Support\VisitorLocale).
 */
class SetLocale
{
    public const SUPPORTED = ['fr', 'en'];

    public function __construct(private readonly VisitorLocale $visitorLocale)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale')
            ?? $request->user()?->locale
            ?? $this->countryLocale($request);

        $locale = in_array($locale, self::SUPPORTED, true) ? $locale : 'fr';
        app()->setLocale($locale);
        Date::setLocale($locale);

        return $next($request);
    }

    private function countryLocale(Request $request): string
    {
        $session = $request->session();

        if (! in_array($session->get('locale_auto'), self::SUPPORTED, true)) {
            $session->put('locale_auto', $this->visitorLocale->forRequest($request));
        }

        return $session->get('locale_auto');
    }
}
