<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum DocumentPermission: string
{
    use HasEnumOptions;

    case View = 'view';
    case Edit = 'edit';
    case Share = 'share';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Chỉ xem',
            self::Edit => 'Chỉnh sửa',
            self::Share => 'Chia sẻ',
        };
    }
}
