<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (Project $project): void {
            $project->phases()->get()->each->delete();
        });

        static::created(function (Project $project): void {
            $actor = Auth::user();
            if (! $actor) {
                return;
            }

            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'entity_type' => static::class,
                'entity_id' => $project->id,
                'action' => 'created',
                'old_values' => [],
                'new_values' => $project->getAttributes(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        static::updated(function (Project $project): void {
            $actor = Auth::user();
            if (! $actor) {
                return;
            }

            if ($project->wasChanged('status')) {
                $originalStatus = $project->getOriginal('status');
                $originalStatusValue = $originalStatus instanceof \BackedEnum ? $originalStatus->value : $originalStatus;
                $newStatusValue = $project->status instanceof \BackedEnum ? $project->status->value : $project->status;

                ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'entity_type' => static::class,
                    'entity_id' => $project->id,
                    'action' => 'status_updated',
                    'old_values' => ['status' => $originalStatusValue],
                    'new_values' => ['status' => $newStatusValue],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            } elseif ($project->wasChanged('progress')) {
                ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'entity_type' => static::class,
                    'entity_id' => $project->id,
                    'action' => 'progress_updated',
                    'old_values' => ['progress' => $project->getOriginal('progress')],
                    'new_values' => ['progress' => $project->progress],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'project_type_id',
        'type',
        'status',
        'budget',
        'budget_spent',
        'objective',
        'start_date',
        'end_date',
        'progress',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'budget_spent' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'progress' => 'integer',
            'status' => ProjectStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projectLeaders(): HasMany
    {
        return $this->hasMany(ProjectLeader::class);
    }

    public function leaders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_leaders')
            ->withPivot(['id', 'assigned_at', 'assigned_by', 'is_primary'])
            ->orderByPivot('is_primary', 'desc')
            ->orderByPivot('assigned_at', 'asc');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class);
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'entity');
    }

    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Danh sach tat ca task thuoc project (thong qua phases).
     */
    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Task::class, Phase::class);
    }

    /**
     * Danh sach task da hoan thanh.
     */
    public function doneTasks(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->tasks()->where('tasks.status', 'completed');
    }

    /**
     * Tong trong so cua tat ca phase trong project.
     */
    public function phaseWeightTotal(): float
    {
        return (float) $this->phases()->sum('weight');
    }

    /**
     * Kiem tra tong trong so phase co bang 100 hay khong.
     */
    public function hasValidPhaseWeightTotal(): bool
    {
        return round($this->phaseWeightTotal(), 2) === 100.0;
    }

    /**
     * Tinh lai % project theo cong thuc BR-009.
     *
     * % Project = SUM(phase.progress * phase.weight / 100)
     */
    public function refreshProgressFromPhases(): void
    {
        // Always recalculate progress, regardless of project status
        $weightedProgress = (float) $this->phases()
            ->selectRaw('COALESCE(SUM(progress * weight / 100.0), 0) as weighted_progress')
            ->value('weighted_progress');

        $progress = (int) round(max(0, min(100, $weightedProgress)));

        // Sync project status based on phase statuses
        $hasActiveOrCompletedPhase = $this->phases()
            ->whereIn('status', ['active', 'completed'])
            ->exists();

        $statusValue = $this->status instanceof ProjectStatus ? $this->status : ProjectStatus::tryFrom((string) $this->status);

        $updates = ['progress' => $progress];

        // If project is paused or overdue we should not auto-change its status
        $skipAutoStatus = in_array($statusValue, [ProjectStatus::Paused, ProjectStatus::Overdue], true);

        if (! $skipAutoStatus) {
            // Init -> Running nếu có phase active/completed
            if ($hasActiveOrCompletedPhase && $statusValue === ProjectStatus::Init) {
                $updates['status'] = ProjectStatus::Running->value;
            }

            // Auto-set completed nếu tất cả phases đều completed
            $totalPhases = $this->phases()->count();
            if ($totalPhases > 0) {
                $completedPhases = $this->phases()->where('status', 'completed')->count();
                $allCompleted = ($completedPhases === $totalPhases);
                $hasActive = $this->phases()->where('status', 'active')->exists();

                if ($allCompleted) {
                    // Không tự chuyển project sang completed; chỉ nút thủ công mới làm việc này.
                } elseif ($hasActive) {
                    // Có phase active → project running
                    $updates['status'] = ProjectStatus::Running->value;
                } elseif ($completedPhases > 0 && ! $hasActive && ! $allCompleted) {
                    // Có phase completed nhưng chưa all, và không có phase active → running (ongoing)
                    $updates['status'] = ProjectStatus::Running->value;
                }
            }
        }

        if ($this->progress !== $progress || (isset($updates['status']) && $this->status !== $updates['status'])) {
            $originalProgress = $this->progress;
            $originalStatus = $this->status instanceof ProjectStatus ? $this->status->value : $this->status;

            $this->forceFill($updates)->saveQuietly();

            $actor = Auth::user();
            if ($actor && isset($updates['progress']) && $originalProgress !== $updates['progress']) {
                ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'entity_type' => static::class,
                    'entity_id' => $this->id,
                    'action' => 'progress_updated',
                    'old_values' => ['progress' => $originalProgress],
                    'new_values' => ['progress' => $updates['progress']],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }

            if ($actor && isset($updates['status']) && $originalStatus !== $updates['status']) {
                ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'entity_type' => static::class,
                    'entity_id' => $this->id,
                    'action' => 'status_updated',
                    'old_values' => ['status' => $originalStatus],
                    'new_values' => ['status' => $updates['status']],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        }
    }
}
