<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tant que la personne n'a pas accepté la version en vigueur du règlement intérieur,
 * elle est renvoyée vers la page du règlement (section « Acceptation des membres »).
 */
class EnsurePolicyAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasAcceptedCurrentPolicy() && ! $request->routeIs('policies.*', 'logout', 'locale.update')) {
            if ($request->isMethod('GET')) {
                return redirect()->guest(route('policies.show'));
            }

            return redirect()->route('policies.show')
                ->with('error', __('Merci de lire et d’accepter le règlement intérieur avant de continuer.'));
        }

        return $next($request);
    }
}
