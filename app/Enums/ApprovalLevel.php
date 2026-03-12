<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum ApprovalLevel: string
{
    use HasEnumOptions;

    case Leader = 'leader';
    case Ceo = 'ceo';

    public function label(): string
    {
        return match ($this) {
            self::Leader => 'Cấp quản lý',
            self::Ceo => 'Ban Giám đốc',
        };
    }
}
