<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum KpiPeriodType: string
{
    use HasEnumOptions;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Theo tháng',
            self::Quarterly => 'Theo quý',
            self::Yearly => 'Theo năm',
        };
    }
}
