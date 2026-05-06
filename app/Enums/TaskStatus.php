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
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chưa bắt đầu',
            self::InProgress => 'Đang thực hiện',
            self::WaitingApproval => 'Chờ duyệt',
            self::Completed => 'Hoàn thành',
            self::Late => 'Trễ hạn',
            self::Cancelled => 'Đã hủy',
        };
    }

    /**
     * Material Symbols icon name for UI rendering.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'hourglass_empty',
            self::InProgress => 'pending_actions',
            self::WaitingApproval => 'approval',
            self::Completed => 'check_circle',
            self::Late => 'warning',
            self::Cancelled => 'cancel',
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
            self::Cancelled => 'gray',
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
            self::Cancelled => 'bg-gray-400',
        };
    }

    public function textColor(): string
    {
        return match ($this) {
            self::Pending => 'text-slate-600 dark:text-slate-400',
            self::InProgress => 'text-primary',
            self::WaitingApproval => 'text-orange-600 dark:text-orange-400',
            self::Completed => 'text-green-700 dark:text-green-400',
            self::Late => 'text-red-700 dark:text-red-400',
            self::Cancelled => 'text-gray-600 dark:text-gray-400',
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
            self::Cancelled => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
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
            self::Cancelled => 'border-l-4 border-l-gray-300',
        };
    }
}
