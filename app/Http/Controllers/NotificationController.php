<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Notifications dans l'application (cloche). */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Notifications/Index', [
            'notifications' => $request->user()->notifications()->latest()->limit(100)->get()->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'message' => $n->data['message'] ?? '',
                'category' => $n->data['category'] ?? 'fund',
                'url' => $n->data['url'] ?? null,
                'read' => $n->read_at !== null,
                'date' => $n->created_at?->timezone('Europe/Paris')->format('d/m/Y H:i'),
            ]),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();
        $url = $item->data['url'] ?? null;

        return $url && str_starts_with($url, url('/')) ? redirect()->to($url) : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Toutes les notifications sont marquées comme lues.');
    }
}
