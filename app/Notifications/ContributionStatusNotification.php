<?php

namespace App\Notifications;

use App\Models\ContributionPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContributionStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private ContributionPayment $payment, private string $status, private ?string $reason = null) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array
    {
        $validated = $this->status === 'validated';
        return [
            'category' => 'contribution', 'title' => $validated ? 'Cotisation validée' : 'Cotisation refusée',
            'message' => $validated ? "Votre cotisation de {$this->payment->amount} {$this->payment->currency} a été validée." : 'Votre cotisation a été refusée'.($this->reason ? ' : '.$this->reason : '.'),
            'url' => route('contributions.show', $this->payment), 'payment_id' => $this->payment->id,
        ];
    }
}
