<?php

namespace App\Models;

use App\Enums\SlaProjectType;
use App\Enums\SlaTaskType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SlaConfig extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'department_id',
        'task_type',
        'project_type',
        'standard_hours',
        'effective_date',
        'expired_date',
        'note',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task_type' => SlaTaskType::class,
            'project_type' => SlaProjectType::class,
            'standard_hours' => 'decimal:2',
            'effective_date' => 'date',
            'expired_date' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope cau hinh SLA dang hieu luc tai thoi diem truyen vao.
     */
    public function scopeEffectiveAt(Builder $query, Carbon|string $date): Builder
    {
        $point = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return $query
            ->whereDate('effective_date', '<=', $point)
            ->where(function (Builder $builder) use ($point): void {
                $builder
                    ->whereNull('expired_date')
                    ->orWhereDate('expired_date', '>=', $point);
            });
    }

    /**
     * Kiem tra ban ghi co hieu luc tai thoi diem truyen vao hay khong.
     */
    public function isEffectiveAt(Carbon|string|null $date = null): bool
    {
        $point = $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse($date ?? now())->startOfDay();

        $effectiveDate = $this->effective_date instanceof Carbon
            ? $this->effective_date->copy()->startOfDay()
            : Carbon::parse((string) $this->effective_date)->startOfDay();

        if ($effectiveDate->gt($point)) {
            return false;
        }

        if ($this->expired_date === null) {
            return true;
        }

        $expiredDate = $this->expired_date instanceof Carbon
            ? $this->expired_date->copy()->startOfDay()
            : Carbon::parse((string) $this->expired_date)->startOfDay();

        return $expiredDate->gte($point);
    }
}
