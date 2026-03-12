<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum TaskStatus: string
{
    use HasEnumOptions;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case WaitingApproval = 'waiting_approval';
    case Completed = 'completed';
    case Late = 'late';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chưa bắt đầu',
            self::InProgress => 'Đang thực hiện',
            self::WaitingApproval => 'Chờ duyệt',
            self::Completed => 'Hoàn thành',
            self::Late => 'Trễ hạn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::InProgress => 'primary',
            self::WaitingApproval => 'orange',
            self::Completed => 'green',
            self::Late => 'red',
        };
    }

    /** Tailwind dot color class */
    public function dotClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-400',
            self::InProgress => 'bg-primary shadow-sm shadow-primary',
            self::WaitingApproval => 'bg-orange-400',
            self::Completed => 'bg-green-500',
            self::Late => 'bg-red-500',
        };
    }

    /** Tailwind badge bg+text classes */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
            self::InProgress => 'bg-primary/10 text-primary',
            self::WaitingApproval => 'bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400',
            self::Completed => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            self::Late => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        };
    }

    /** Left border accent for kanban card */
    public function borderClass(): string
    {
        return match ($this) {
            self::Pending => '',
            self::InProgress => 'border-l-4 border-l-primary',
            self::WaitingApproval => 'border-l-4 border-l-orange-400',
            self::Completed => '',
            self::Late => '',
        };
    }
}
