<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
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
            'type' => ProjectType::class,
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
            ->withPivot(['id', 'assigned_at', 'assigned_by']);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class);
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
        $weightedProgress = (float) $this->phases()
            ->selectRaw('COALESCE(SUM(progress * weight / 100.0), 0) as weighted_progress')
            ->value('weighted_progress');

        $progress = (int) round(max(0, min(100, $weightedProgress)));

        if ($this->progress !== $progress) {
            $this->forceFill([
                'progress' => $progress,
            ])->saveQuietly();
        }
    }
}
