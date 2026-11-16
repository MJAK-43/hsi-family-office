<?php

use App\Http\Middleware\EnsureMemberIsActive;
use App\Http\Middleware\EnsurePolicyAccepted;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            // En-tête « Link » limité : au-delà, nginx refuse la réponse (502, « upstream sent too big header »).
            AddLinkHeadersForPreloadedAssets::using(8),
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'active_member' => EnsureMemberIsActive::class,
            'policy' => EnsurePolicyAccepted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Trop d'envois d'un formulaire : on revient sur la page avec un message clair
        // au lieu de l'écran brut « 429 Too Many Requests ».
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->isMethod('GET') || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
                return null;
            }

            $minutes = max(1, (int) ceil(((int) ($e->getHeaders()['Retry-After'] ?? 60)) / 60));

            return back()->with('error', trans_choice(
                'Trop d\'essais en peu de temps. Patientez :minutes minute puis réessayez.|Trop d\'essais en peu de temps. Patientez :minutes minutes puis réessayez.',
                $minutes,
                ['minutes' => $minutes],
            ));
        });
    })->create();
