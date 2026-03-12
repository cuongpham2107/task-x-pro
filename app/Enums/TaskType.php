<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum TaskType: string
{
    use HasEnumOptions;

    case Admin = 'admin';
    case Technical = 'technical';
    case Operation = 'operation';
    case Report = 'report';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Hành chính',
            self::Technical => 'Kỹ thuật',
            self::Operation => 'Vận hành',
            self::Report => 'Báo cáo',
            self::Other => 'Khác',
        };
    }
}
