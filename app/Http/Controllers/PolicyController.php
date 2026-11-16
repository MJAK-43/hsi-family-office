<?php

namespace App\Http\Controllers;

use App\Support\FundRules;
use App\Support\InternalPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PolicyController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Policies/Show', [
            'sections' => InternalPolicy::filled(app()->getLocale()),
            'version' => FundRules::get('policy_version'),
            'mustAccept' => ! $user->hasAcceptedCurrentPolicy(),
            'acceptedAt' => $user->hasAcceptedCurrentPolicy() ? $user->policy_accepted_at?->timezone('Europe/Paris')->format('d/m/Y H:i') : null,
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate(['accepted' => ['accepted']], ['accepted.accepted' => __('Cochez la case pour confirmer que vous avez lu et accepté le règlement.')]);

        $user = $request->user();
        $version = FundRules::get('policy_version');
        $user->forceFill([
            'policy_accepted_at' => now(),
            'policy_accepted_version' => $version,
            'policy_accepted_ip' => $request->ip(),
        ])->save();

        activity('members')->performedOn($user)->causedBy($user)->event('policy.accepted')
            ->withProperties(['version' => $version, 'ip' => $request->ip()])
            ->log('Règlement intérieur accepté');

        return redirect()->intended(route('dashboard'))
            ->with('success', __('Merci, votre acceptation du règlement intérieur est enregistrée.'));
    }
}
