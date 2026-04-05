<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumOptions;

enum ProjectStatus: string
{
    use HasEnumOptions;

    case Init = 'init';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Init => 'Khởi tạo',
            self::Running => 'Đang chạy',
            self::Paused => 'Tạm dừng',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
        };
    }

    /**
     * Material Symbols icon name for UI rendering.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Init => 'new_releases',
            self::Running => 'play_circle',
            self::Paused => 'pause_circle',
            self::Completed => 'check_circle',
            self::Cancelled => 'cancel',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Init => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            self::Running => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::Paused => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
            self::Completed => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            self::Cancelled => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::Init => 'bg-slate-400',
            self::Running => 'bg-blue-500',
            self::Paused => 'bg-yellow-400',
            self::Completed => 'bg-green-500',
            self::Cancelled => 'bg-red-500',
        };
    }
}
