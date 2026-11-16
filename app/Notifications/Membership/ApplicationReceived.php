<?php

namespace App\Notifications\Membership;

use App\Models\MembershipApplication;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Accusé de réception envoyé au candidat. */
class ApplicationReceived extends Notification
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
        return (new MailMessage)
            ->subject(__("Votre demande d'adhésion a bien été reçue — HSI"))
            ->greeting(__('Merci :name, votre demande est enregistrée', ['name' => $this->application->first_name]))
            ->line(__('Nous avons bien reçu votre demande pour rejoindre le Fonds familial JOKUNG.'))
            ->line(__('Comme le prévoit le règlement, les nouveaux membres sont admis une fois par an, en fin de cycle, après décision du groupe. Vous serez informé(e) par email de la réponse.'))
            ->line(__("Si elle est acceptée, votre compte sera créé automatiquement : vous pourrez vous connecter avec cette adresse email et le mot de passe choisi. Si elle n'est pas retenue, aucun compte ne sera créé et vos informations ne serviront à rien d'autre."))
            ->line(__("Vous n'êtes pas à l'origine de cette demande ? Ignorez simplement ce message."));
    }
}
