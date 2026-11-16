<?php

namespace App\Notifications\Membership;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Envoyé quand une demande d'adhésion est faite avec l'email d'un compte existant.
 * La page affiche le même message que pour une nouvelle demande, pour ne pas révéler
 * quelles adresses ont un compte : c'est cet email qui informe la personne.
 */
class ExistingAccountNotice extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Vous avez déjà un compte HSI'))
            ->greeting(__('Vous êtes déjà membre'))
            ->line(__("Une demande d'adhésion vient d'être faite avec cette adresse email, mais un compte existe déjà pour elle."))
            ->action(__('Se connecter'), route('login'))
            ->line(__('Mot de passe oublié ? Utilisez le lien « Mot de passe oublié » sur la page de connexion.'))
            ->line(__("Vous n'êtes pas à l'origine de cette demande ? Ignorez ce message."));
    }
}
