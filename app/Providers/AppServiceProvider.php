<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Vite;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Circuit de validation des transactions : chaque type sait comment s'exécuter une fois validé.
        $this->app->singleton(\App\Services\ApprovalService::class, function ($app) {
            $service = new \App\Services\ApprovalService;
            $service->registerExecutor('loan_disbursement', fn ($request, $user) => $app->make(\App\Services\LoanService::class)->executeDisbursement($request, $user));
            $service->registerExecutor('loan_repayment', fn ($request, $user) => $app->make(\App\Services\LoanService::class)->executeRepayment($request, $user));
            $service->registerExecutor('withdrawal_refund', fn ($request, $user) => $app->make(\App\Services\WithdrawalService::class)->executePayment($request, $user));
            $service->registerExecutor('contribution', function ($request, $user) use ($app) {
                $payment = \App\Models\ContributionPayment::findOrFail($request->subject_id);
                $app->make(\App\Services\FundService::class)->validateAndAllocateContributionPayment($payment, $user, 'approval-'.$request->id);

                return ['payment_id' => $payment->id];
            });

            return $service;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Règle commune à toute création ou modification de mot de passe.
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->letters()->numbers()->uncompromised()
            : Password::min(12)->letters()->numbers());

        Mail::extend('brevo', fn (array $config) => new BrevoApiTransport((string) ($config['key'] ?? '')));

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new MailMessage)
                ->subject(__('Réinitialisation de votre mot de passe — HSI'))
                ->greeting(__('Réinitialisez votre mot de passe'))
                ->line(__('Nous avons reçu une demande de réinitialisation du mot de passe de votre espace HSI · Fonds familial JOKUNG.'))
                ->action(__('Choisir un nouveau mot de passe'), $url)
                ->line(__("Ce lien est valable :minutes minutes et ne peut servir qu'une fois.", ['minutes' => $minutes]))
                ->line(__("Vous n'êtes pas à l'origine de cette demande ? Ignorez cet email : votre mot de passe reste inchangé."));
        });

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject(__('Confirmez votre adresse email — HSI'))
            ->greeting(__('Confirmez votre adresse email'))
            ->line(__('Pour sécuriser votre espace HSI · Fonds familial JOKUNG, confirmez que cette adresse vous appartient.'))
            ->action(__('Confirmer mon adresse'), $url)
            ->line(__('Ce lien est valable 60 minutes.'))
            ->line(__("Si vous n'avez pas de compte HSI, ignorez simplement cet email.")));
    }
}
