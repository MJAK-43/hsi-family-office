<?php

namespace App\Notifications\Membership;

use App\Models\MembershipApplication;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Réponse envoyée au candidat après la décision du groupe. */
class ApplicationDecision extends Notification
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

        if ($a->status === MembershipApplication::STATUS_ACCEPTED) {
            $message = (new MailMessage)
                ->subject(__('Bienvenue dans le Fonds familial JOKUNG — votre compte est prêt'))
                ->greeting(__('Bienvenue, :name !', ['name' => $a->first_name]))
                ->line(__("Votre demande d'adhésion au Fonds familial JOKUNG a été acceptée et votre compte est créé."))
                ->line($a->google_id
                    ? __('Connectez-vous avec le bouton « Continuer avec Google », en choisissant votre compte **:email**.', ['email' => $a->email])
                    : __('Connectez-vous avec votre adresse **:email** et le mot de passe que vous avez choisi lors de votre demande.', ['email' => $a->email]))
                ->action(__('Accéder à mon espace'), route('login'))
                ->line(__('Mot de passe oublié ? Utilisez le lien « Mot de passe oublié » sur la page de connexion.'));
        } else {
            $message = (new MailMessage)
                ->subject(__("Réponse à votre demande d'adhésion — HSI"))
                ->greeting(__('Bonjour :name,', ['name' => $a->first_name]))
                ->line(__("Après examen, votre demande d'adhésion au Fonds familial JOKUNG n'a pas été retenue pour ce cycle."))
                ->line(__("Vous pourrez déposer une nouvelle demande lors de la prochaine période d'admission."));
        }

        if ($a->decision_note) {
            $message->line(__('Message du fonds : « :note »', ['note' => $a->decision_note]));
        }

        return $message;
    }
}
