<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum ProjectType: string
{
    use HasEnumOptions;

    case Warehouse = 'warehouse';
    case Customs = 'customs';
    case Trucking = 'trucking';
    case Software = 'software';
    case Gms = 'gms';
    case Tower = 'tower';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Kho bãi',
            self::Customs => 'Hải quan',
            self::Trucking => 'Vận tải',
            self::Software => 'Phần mềm',
            self::Gms => 'GMS',
            self::Tower => 'Tower',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Warehouse => 'warehouse',
            self::Customs => 'customs',
            self::Trucking => 'trucking',
            self::Software => 'software',
            self::Gms => 'gms',
            self::Tower => 'tower',
        };
    }
}
