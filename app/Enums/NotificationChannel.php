<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum NotificationChannel: string
{
    use HasEnumOptions;

    case Telegram = 'telegram';
    case Email = 'email';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'Telegram',
            self::Email => 'Email',
            self::Both => 'Telegram + Email',
        };
    }
}
