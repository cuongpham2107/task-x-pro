<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum PhaseStatus: string
{
    use HasEnumOptions;

    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Paused = 'paused';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chưa bắt đầu',
            self::Active => 'Đang thực hiện',
            self::Completed => 'Hoàn thành',
            self::Paused => 'Tạm dừng',
            self::Overdue => 'Quá hạn',
        };
    }
}
