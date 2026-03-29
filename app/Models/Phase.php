<?php

namespace App\Models;

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
     * Tinh lai progress phase theo trung binh progress task.
     *
     * progress phase = AVG(task.progress)
     */
    public function refreshProgressFromTasks(): void
    {
        $tasksQuery = $this->tasks();
        $averageTaskProgress = (float) ($tasksQuery->avg('progress') ?? 0);
        $progress = (int) round(max(0, min(100, $averageTaskProgress)));

        $taskCount = (int) $tasksQuery->count();
        $hasIncompleteTask = $this->tasks()
            ->where('status', '!=', \App\Enums\TaskStatus::Completed->value)
            ->exists();

        $hasStartedTask = $this->tasks()
            ->where('status', '!=', \App\Enums\TaskStatus::Pending->value)
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
