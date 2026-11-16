<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email de test envoyé par la commande `hsi:mail-test` pour vérifier la configuration d'envoi. */
class MailConfigurationTest extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test d\'envoi — HSI · Fonds familial JOKUNG')
            ->greeting('L\'envoi des emails fonctionne')
            ->line('Ce message confirme que l\'application HSI envoie correctement ses emails via Brevo.')
            ->line('Envoyé le '.now()->timezone('Europe/Paris')->format('d/m/Y à H:i').' depuis '.config('app.url').'.')
            ->action('Ouvrir l\'application', rtrim(config('app.url'), '/').'/login')
            ->line('Aucune action n\'est nécessaire de votre part.');
    }
}
