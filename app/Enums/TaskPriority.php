<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum TaskPriority: string
{
    use HasEnumOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Thấp',
            self::Medium => 'Trung bình',
            self::High => 'Cao',
            self::Urgent => 'Khẩn cấp',
        };
    }

    /**
     * Color token for <x-ui.badge> (maps to its internal palette).
     */
    public function color(): string
    {
        return match ($this) {
            self::Urgent => 'red',
            self::High => 'orange',
            self::Medium => 'amber',
            self::Low => 'blue',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            // Update these to match your actual enum cases + preferred icons
            self::Low => 'arrow_downward',
            self::Medium => 'remove',
            self::High => 'arrow_upward',
            self::Urgent => 'priority_high',
        };
    }
}
