<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum DocumentType: string
{
    use HasEnumOptions;

    case Sop = 'sop';
    case Form = 'form';
    case Quote = 'quote';
    case Contract = 'contract';
    case Technical = 'technical';
    case Deliverable = 'deliverable';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sop => 'SOP',
            self::Form => 'Biểu mẫu',
            self::Quote => 'Báo giá',
            self::Contract => 'Hợp đồng',
            self::Technical => 'Kỹ thuật',
            self::Deliverable => 'Giao phẩm',
            self::Other => 'Khác',
        };
    }
}
