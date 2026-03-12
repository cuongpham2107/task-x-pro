<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum ApprovalAction: string
{
    use HasEnumOptions;

    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Đã gửi duyệt',
            self::Approved => 'Đã duyệt',
            self::Rejected => 'Từ chối',
        };
    }
}
