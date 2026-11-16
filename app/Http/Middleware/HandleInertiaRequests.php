<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatarUrl(),
                ] : null,
                'roles' => $user ? \App\Support\MemberRoleHelper::sort($user->getRoleNames()) : [],
                'permissions' => $user?->getAllPermissions()->pluck('name') ?? [],
                'unread_notifications' => $user ? $user->unreadNotifications()->count() : 0,
                'pending_approvals' => $user && $user->can('approve transactions')
                    ? \App\Models\ApprovalRequest::where('status', 'pending')->get(['id', 'approver_roles', 'beneficiary_id'])
                        ->filter(fn ($r) => $user->hasAnyRole($r->approver_roles ?: []) && $r->beneficiary_id !== $user->id)
                        ->reject(fn ($r) => \App\Models\ApprovalDecision::where('approval_request_id', $r->id)->where('user_id', $user->id)->exists())->count()
                    : 0,
            ],
            // Langue de l'interface (fr ou en), choisie via le sélecteur de langue.
            'currency' => \App\Support\FundCurrency::shared(),
            'locale' => app()->getLocale(),
            'locales' => SetLocale::SUPPORTED,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
