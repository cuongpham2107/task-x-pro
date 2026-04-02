<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Phase extends Model
{
    use HasFactory;

    /**
     * Tu dong dong bo % project sau moi lan phase thay doi.
     */
    protected static function booted(): void
    {
        static::saved(function (Phase $phase): void {
            $phase->project?->refreshProgressFromPhases();
        });

        static::deleted(function (Phase $phase): void {
            $phase->project?->refreshProgressFromPhases();
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'description',
        'weight',
        'order_index',
        'start_date',
        'end_date',
        'progress',
        'status',
        'is_template',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'order_index' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'progress' => 'integer',
            'is_template' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tinh lai progress phase dua tren so task da completed / tong so task.
     *
     * progress phase = (so task completed / tong task) * 100
     */
    public function refreshProgressFromTasks(): void
    {
        $taskCount = (int) $this->tasks()->count();

        if ($taskCount === 0) {
            $progress = 0;
        } else {
            $completedCount = (int) $this->tasks()
                ->where('status', TaskStatus::Completed)
                ->count();
            $progress = (int) round($completedCount / $taskCount * 100);
        }

        $hasIncompleteTask = $this->tasks()
            ->where('status', '!=', TaskStatus::Completed)
            ->exists();

        $hasStartedTask = $this->tasks()
            ->where('status', '!=', TaskStatus::Pending)
            ->exists();

        $canMarkCompleted = $taskCount > 0 && ! $hasIncompleteTask;
        $status = match (true) {
            $canMarkCompleted => 'completed',
            $progress > 0 || $hasStartedTask => 'active',
            default => 'pending',
        };

        if ($this->progress !== $progress || $this->status !== $status) {
            $this->forceFill([
                'progress' => $progress,
                'status' => $status,
            ])->saveQuietly();
        }

        $this->project?->refreshProgressFromPhases();
    }
}
