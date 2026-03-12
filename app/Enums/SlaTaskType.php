<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum SlaTaskType: string
{
    use HasEnumOptions;

    case Admin = 'admin';
    case Technical = 'technical';
    case Operation = 'operation';
    case Report = 'report';
    case Other = 'other';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Hành chính',
            self::Technical => 'Kỹ thuật',
            self::Operation => 'Vận hành',
            self::Report => 'Báo cáo',
            self::Other => 'Khác',
            self::All => 'Tất cả loại công việc',
        };
    }
}
