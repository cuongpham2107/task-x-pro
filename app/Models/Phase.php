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
        $hasIncompleteTask = $tasksQuery
            ->where(function ($query): void {
                $query
                    ->where('status', '!=', \App\Enums\TaskStatus::Completed->value)
                    ->orWhere('progress', '<', 100);
            })
            ->exists();

        $canMarkCompleted = $taskCount > 0 && ! $hasIncompleteTask;
        $status = match (true) {
            $canMarkCompleted => 'completed',
            $progress > 0 => 'active',
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
