<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Préférences d'affichage personnelles (enregistrées sur le compte, donc valables sur tous les appareils). */
class PreferenceController extends Controller
{
    /** Cartes du tableau de bord que l'on peut masquer. */
    public const DASHBOARD_WIDGETS = [
        'stat_members', 'stat_contributions', 'stat_fund', 'stat_projects', 'stat_votes', 'stat_pending',
        'flows', 'allocation', 'contributions', 'projects', 'quick_actions', 'votes',
    ];

    public function updateDashboard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hidden' => ['present', 'array'],
            'hidden.*' => ['string', 'in:'.implode(',', self::DASHBOARD_WIDGETS)],
        ]);
        $user = $request->user();
        $preferences = $user->preferences ?? [];
        $preferences['dashboard_hidden'] = array_values(array_unique($data['hidden']));
        $user->forceFill(['preferences' => $preferences])->save();

        return back();
    }
}
