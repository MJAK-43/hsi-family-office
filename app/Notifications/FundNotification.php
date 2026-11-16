<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification du fonds envoyée par email et visible dans l'application (cloche).
 * Les textes sont des clés françaises traduites dans la langue du destinataire : [texte, paramètres].
 */
class FundNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{0:string,1?:array}  $subject
     * @param  array<int, array{0:string,1?:array}>  $lines
     */
    public function __construct(
        private array $subject,
        private array $lines,
        private ?string $url = null,
        private string $actionLabel = 'Ouvrir dans l’application',
        private string $category = 'fund',
        private bool $mail = true,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->mail && ! empty($notifiable->email) ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->text($this->subject).' — HSI')
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->name ?? '']));
        foreach ($this->lines as $line) {
            $message->line($this->text($line));
        }
        if ($this->url) {
            $message->action(__($this->actionLabel), $this->url);
        }

        return $message->salutation(__('L’équipe du Fonds familial JOKUNG'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->text($this->subject),
            'message' => implode(' ', array_map(fn ($line) => $this->text($line), $this->lines)),
            'url' => $this->url,
        ];
    }

    private function text(array $line): string
    {
        return __($line[0], $line[1] ?? []);
    }
}
