<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum TaskWorkflowType: string
{
    use HasEnumOptions;

    case Single = 'single';
    case Double = 'double';

    public function label(): string
    {
        return match ($this) {
            self::Single => '1 cấp duyệt',
            self::Double => '2 cấp duyệt',
        };
    }
}
