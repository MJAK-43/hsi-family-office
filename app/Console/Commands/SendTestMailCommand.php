<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\MailConfigurationTest;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendTestMailCommand extends Command
{
    protected $signature = 'hsi:mail-test {email : Adresse qui reçoit le test} {--reset : Envoie aussi un exemple d\'email de réinitialisation (lien inactif)}';

    protected $description = 'Envoie un email de test pour vérifier la configuration d\'envoi (Brevo).';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $this->info('Transport : '.config('mail.default').' — expéditeur : '.config('mail.from.address'));

        try {
            Notification::route('mail', $email)->notifyNow(new MailConfigurationTest);
            $this->info("Email de test envoyé à {$email}.");

            if ($this->option('reset')) {
                // Utilisateur fictif, jamais enregistré : sert uniquement à générer l'aperçu.
                (new User)->forceFill(['name' => 'Aperçu', 'email' => $email])
                    ->notifyNow(new ResetPassword('apercu-lien-inactif'));
                $this->info("Exemple de réinitialisation envoyé à {$email}.");
            }
        } catch (Throwable $e) {
            $this->error('Échec de l\'envoi : '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
