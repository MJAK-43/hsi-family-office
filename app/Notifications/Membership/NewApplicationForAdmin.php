<?php

namespace App\Notifications\Membership;

use App\Models\MembershipApplication;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerte envoyée aux administrateurs à chaque nouvelle demande. */
class NewApplicationForAdmin extends Notification
{
    public function __construct(private readonly MembershipApplication $application)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->application;

        $message = (new MailMessage)
            ->subject(__("Nouvelle demande d'adhésion : :name", ['name' => $a->fullName()]))
            ->greeting(__("Nouvelle demande d'adhésion"))
            ->line(__('**:name** souhaite rejoindre le Fonds familial JOKUNG.', ['name' => $a->fullName()]))
            ->line(__('Email : :email', ['email' => $a->email]).($a->phone ? ' · '.__('Téléphone : :phone', ['phone' => $a->phone]) : ''))
            ->line(__('Pays : :country', ['country' => $a->country]).($a->city ? ' ('.$a->city.')' : ''));

        if ($a->profession) {
            $message->line(__('Profession : :profession', ['profession' => $a->profession]));
        }

        return $message->action(__('Examiner la demande'), route('membership-applications.index'));
    }
}
