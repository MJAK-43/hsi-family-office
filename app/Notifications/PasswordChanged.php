<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Confirmation envoyée au membre dès que son mot de passe a été changé. */
class PasswordChanged extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Votre mot de passe HSI a été modifié'))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->name]))
            ->line(__('Le mot de passe de votre compte HSI vient d\'être modifié, le :date.', [
                'date' => now()->timezone('Europe/Paris')->translatedFormat('d/m/Y à H:i'),
            ]))
            ->line(__('Si c\'est vous, vous n\'avez rien à faire.'))
            ->line(__('Si ce n\'est pas vous, choisissez tout de suite un nouveau mot de passe avec le lien ci-dessous, puis prévenez un administrateur du fonds.'))
            ->action(__('Choisir un nouveau mot de passe'), route('password.request'));
    }
}
