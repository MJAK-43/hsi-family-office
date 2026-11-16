<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * « Continuer avec Google », depuis la page de connexion ou la page d'adhésion.
 *
 * - L'adresse Google correspond à un compte : la personne est connectée (et le compte est relié à Google).
 * - Aucun compte : elle arrive sur le formulaire d'adhésion, nom et email déjà remplis,
 *   sans mot de passe à choisir. Elle se connectera avec Google une fois admise.
 */
class GoogleAuthController extends Controller
{
    /** Session : identité Google en attente d'une demande d'adhésion. */
    public const SESSION_KEY = 'google_signup';

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        if (! self::enabled()) {
            return redirect()->route('login')->with('error', __('La connexion avec Google n\'est pas disponible pour le moment.'));
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'email', 'profile'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! self::enabled() || $request->filled('error')) {
            // La personne a annulé sur l'écran Google, ou la connexion n'est pas configurée.
            return redirect()->route('login');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Connexion Google impossible : '.$e->getMessage());

            return redirect()->route('login')->with('error', __('La connexion avec Google a échoué. Réessayez, ou utilisez votre email et votre mot de passe.'));
        }

        $email = Str::lower(trim((string) $google->getEmail()));
        $googleId = (string) $google->getId();
        $raw = $google->getRaw();

        // Google doit avoir vérifié l'adresse, sinon n'importe qui pourrait se faire passer pour un membre.
        if ($email === '' || $googleId === '' || ! filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('login')->with('error', __('Votre adresse Google n\'est pas vérifiée : connectez-vous avec votre email et votre mot de passe.'));
        }

        $user = User::where('google_id', $googleId)->first() ?? User::where('email', $email)->first();

        if ($user) {
            // Une adresse déjà reliée à un autre compte Google ne doit pas être reprise.
            if ($user->google_id && $user->google_id !== $googleId) {
                return redirect()->route('login')->with('error', __('Ce compte est relié à un autre compte Google. Connectez-vous avec votre email et votre mot de passe.'));
            }

            if (! $user->google_id) {
                $user->forceFill(['google_id' => $googleId])->save();
            }
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $request->session()->forget(self::SESSION_KEY);
            Auth::login($user, remember: true);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        }

        $pending = MembershipApplication::where('email', $email)
            ->where('status', MembershipApplication::STATUS_PENDING)
            ->exists();

        if ($pending) {
            return redirect()->route('login')->with('status', __('Votre demande d\'adhésion est en cours d\'examen. Vous pourrez vous connecter dès qu\'elle sera acceptée.'));
        }

        $request->session()->put(self::SESSION_KEY, [
            'google_id' => $googleId,
            'email' => $email,
            'first_name' => Str::limit((string) ($raw['given_name'] ?? ''), 100, ''),
            'last_name' => Str::limit((string) ($raw['family_name'] ?? ''), 100, ''),
        ]);

        return redirect()->route('membership.apply');
    }

    /** « Utiliser un autre moyen » sur le formulaire d'adhésion : on oublie l'identité Google. */
    public function forget(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('membership.apply');
    }
}
