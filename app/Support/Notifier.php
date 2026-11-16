<?php

namespace App\Support;

use App\Enums\MemberStatus;
use App\Models\User;
use App\Notifications\FundNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Destinataires usuels des notifications du fonds. Un échec d'envoi ne bloque jamais l'action. */
class Notifier
{
    public static function treasurers(): Collection
    {
        return User::role('treasurer')->get();
    }

    public static function committee(): Collection
    {
        return User::role(['investment_committee', 'president'])->get()->unique('id');
    }

    public static function activeMembers(): Collection
    {
        return User::query()->whereHas('memberProfile', fn ($q) => $q->where('status', MemberStatus::Active))->get();
    }

    public static function send(iterable $users, FundNotification $notification): void
    {
        $users = collect($users)->filter()->unique('id');
        if ($users->isEmpty()) {
            return;
        }
        foreach ($users as $user) {
            try {
                Notification::send($user, $notification);
            } catch (Throwable $e) {
                Log::warning('Notification non envoyée à l’utilisateur '.$user->id.' : '.$e->getMessage());
            }
        }
    }
}
