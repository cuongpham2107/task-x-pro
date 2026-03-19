<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum UserStatus: string
{
    use HasEnumOptions;
    case Pending = 'pending';
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Resigned = 'resigned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ duyệt',
            self::Active => 'Đang làm việc',
            self::OnLeave => 'Nghỉ phép',
            self::Resigned => 'Đã nghỉ việc',
        };
    }
}
