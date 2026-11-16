<?php

namespace App\Notifications;

use App\Models\ContributionDue;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContributionDueReminder extends Notification
{
    use Queueable;

    public function __construct(private ContributionDue $due) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'contribution', 'title' => 'Cotisation à régler',
            'message' => "L’échéance {$this->due->period_label} de {$this->due->amount} {$this->due->currency} est à régler.",
            'url' => route('contributions.index'), 'due_id' => $this->due->id,
        ];
    }
}
