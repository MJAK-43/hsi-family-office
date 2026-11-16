<?php

namespace App\Http\Middleware;

use App\Enums\MemberStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $profile = $user?->memberProfile;

        if ($profile && $profile->status !== MemberStatus::Active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Votre compte membre est inactif. Contactez un administrateur.');
        }

        return $next($request);
    }
}
