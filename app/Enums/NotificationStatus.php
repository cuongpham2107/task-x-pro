<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum NotificationStatus: string
{
    use HasEnumOptions;

    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ gửi',
            self::Sent => 'Đã gửi',
            self::Failed => 'Gửi thất bại',
        };
    }
}
