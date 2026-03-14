<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected ?int $previousPhaseId = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'phase_id',
        'name',
        'description',
        'type',
        'status',
        'priority',
        'progress',
        'pic_id',
        'dependency_task_id',
        'deadline',
        'started_at',
        'completed_at',
        'deliverable_url',
        'issue_note',
        'recommendation',
        'workflow_type',
        'sla_standard_hours',
        'sla_met',
        'delay_days',
        'created_by',
    ];

    /**
     * Dang ky cac hook tu dong:
     * - Chuan hoa progress va tinh SLA/delay khi complete
     * - Dong bo lai progress Phase/Project
     * - Dong bo lai KPI cho PIC lien quan
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            $task->normalizeProgress();
            $task->applyCompletionMetrics();
        });

        static::updating(function (Task $task): void {
            $task->previousPhaseId = $task->getOriginal('phase_id') !== null
                ? (int) $task->getOriginal('phase_id')
                : null;
        });

        static::saved(function (Task $task): void {
            $task->syncPhaseAndProjectProgress();
        });

        static::deleting(function (Task $task): void {
            $task->previousPhaseId = $task->phase_id;
        });

        static::deleted(function (Task $task): void {
            $task->syncPhaseAndProjectProgress();
        });

        static::restored(function (Task $task): void {
            $task->syncPhaseAndProjectProgress();
        });

        static::updated(function (Task $task): void {
            $actor = Auth::user();
            if (! $actor) {
                return;
            }

            if ($task->wasChanged('status')) {
                $originalStatus = $task->getOriginal('status');
                $originalStatusValue = $originalStatus instanceof \BackedEnum ? $originalStatus->value : $originalStatus;
                $newStatusValue = $task->status instanceof \BackedEnum ? $task->status->value : $task->status;

                \App\Models\ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'entity_type' => static::class,
                    'entity_id' => $task->id,
                    'action' => 'status_updated',
                    'old_values' => ['status' => $originalStatusValue],
                    'new_values' => ['status' => $newStatusValue],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            } elseif ($task->wasChanged('progress')) {
                \App\Models\ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'entity_type' => static::class,
                    'entity_id' => $task->id,
                    'action' => 'progress_updated',
                    'old_values' => ['progress' => $task->getOriginal('progress')],
                    'new_values' => ['progress' => $task->progress],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => \App\Enums\TaskType::class,
            'status' => \App\Enums\TaskStatus::class,
            'priority' => \App\Enums\TaskPriority::class,
            'deadline' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'progress' => 'integer',
            'sla_standard_hours' => 'decimal:2',
            'sla_met' => 'boolean',
            'delay_days' => 'decimal:2',
            'workflow_type' => \App\Enums\TaskWorkflowType::class,
        ];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function dependencyTask(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dependency_task_id');
    }

    public function dependentTasks(): HasMany
    {
        return $this->hasMany(self::class, 'dependency_task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function coPicAssignments(): HasMany
    {
        return $this->hasMany(TaskCoPic::class);
    }

    public function coPics(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_co_pics')
            ->withPivot(['assigned_at']);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function activityLogs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'entity');
    }

    /**
     * Dam bao progress task nam trong khoang 0-100.
     */
    private function normalizeProgress(): void
    {
        $progress = (int) $this->progress;
        $this->progress = max(0, min(100, $progress));
    }

    /**
     * Ap dung BR-007 khi task hoan thanh:
     * - Ep progress = 100
     * - Tinh sla_met theo sla_standard_hours
     * - Tinh delay_days theo deadline
     */
    private function applyCompletionMetrics(): void
    {
        if ($this->status !== \App\Enums\TaskStatus::Completed && $this->status !== 'completed') {
            $this->completed_at = null;
            $this->sla_met = null;
            $this->delay_days = 0;

            return;
        }

        $completedAt = $this->completed_at instanceof Carbon
            ? $this->completed_at
            : ($this->completed_at !== null ? Carbon::parse($this->completed_at) : now());

        $startedAt = $this->started_at instanceof Carbon
            ? $this->started_at
            : ($this->started_at !== null ? Carbon::parse($this->started_at) : $completedAt->copy());

        $this->completed_at = $completedAt;
        $this->started_at = $startedAt;
        $this->progress = 100;

        if ($this->sla_standard_hours !== null) {
            $actualSeconds = max(0, $startedAt->diffInSeconds($completedAt));
            $slaLimitSeconds = (float) $this->sla_standard_hours * 3600;
            $this->sla_met = $actualSeconds <= $slaLimitSeconds;
        }

        if ($this->deadline !== null) {
            $deadlineAt = $this->deadline instanceof Carbon
                ? $this->deadline
                : Carbon::parse($this->deadline);

            $delaySeconds = $completedAt->diffInSeconds($deadlineAt, false);
            $this->delay_days = round($delaySeconds / 86400, 2);
        }
    }

    /**
     * Sau khi task thay doi, tinh lai progress phase va project.
     */
    private function syncPhaseAndProjectProgress(): void
    {
        if ($this->phase_id !== null) {
            $phase = Phase::query()->find($this->phase_id);

            if ($phase !== null) {
                $phase->refreshProgressFromTasks();
            }
        }

        if ($this->previousPhaseId !== null && $this->previousPhaseId !== $this->phase_id) {
            $oldPhase = Phase::query()->find($this->previousPhaseId);

            if ($oldPhase !== null) {
                $oldPhase->refreshProgressFromTasks();
            }
        }
    }
}
