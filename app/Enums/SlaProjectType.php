<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum SlaProjectType: string
{
    use HasEnumOptions;

    case Warehouse = 'warehouse';
    case Customs = 'customs';
    case Trucking = 'trucking';
    case Software = 'software';
    case Gms = 'gms';
    case Tower = 'tower';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Kho bãi',
            self::Customs => 'Hải quan',
            self::Trucking => 'Vận tải',
            self::Software => 'Phần mềm',
            self::Gms => 'GMS',
            self::Tower => 'Tower',
            self::All => 'Tất cả loại dự án',
        };
    }
}
