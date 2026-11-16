<?php

namespace App\Enums;

enum ProjectMilestoneStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À faire',
            self::InProgress => 'En cours',
            self::Completed => 'Terminé',
            self::Blocked => 'Bloqué',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
