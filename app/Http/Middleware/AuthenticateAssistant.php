<?php

namespace App\Http\Middleware;

use App\Enums\MemberStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accès du service Assistant IA.
 *
 * - Le service présente le code secret partagé (en-tête Authorization: Bearer …).
 * - Il indique pour quel membre il agit (en-tête X-Acting-User). HSI applique alors
 *   les droits de CE membre : l'assistant ne voit jamais plus que lui.
 */
class AuthenticateAssistant
{
    public function handle(Request $request, Closure $next, string $mode = 'member'): Response
    {
        $expected = (string) config('assistant.token');
        $given = (string) $request->bearerToken();
        abort_if($expected === '', 404);
        abort_unless($given !== '' && hash_equals($expected, $given), 401, 'Jeton de l’assistant invalide.');

        if ($mode === 'service') {
            return $next($request);
        }

        $user = User::with('memberProfile')->find((int) $request->header('X-Acting-User'));
        abort_unless($user, 403, 'Membre inconnu.');
        abort_if($user->memberProfile && $user->memberProfile->status !== MemberStatus::Active, 403, 'Compte membre inactif.');
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
