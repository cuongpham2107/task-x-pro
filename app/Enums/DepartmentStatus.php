<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum DepartmentStatus: string
{
    use HasEnumOptions;

    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang hoạt động',
            self::Inactive => 'Ngừng hoạt động',
        };
    }
}
