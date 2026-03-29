<?php

namespace App\Models;

use App\Enums\DocumentPermission;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'task_id',
        'uploader_id',
        'name',
        'document_type',
        'description',
        'google_drive_id',
        'google_drive_url',
        'current_version',
        'permission',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_version' => 'integer',
            'document_type' => DocumentType::class,
            'permission' => DocumentPermission::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * Scope a query to only include documents accessible by the given user.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if (! $user->can('document.view')) {
            return $query->whereRaw('0=1');
        }

        if ($user->hasAnyRole(['ceo', 'leader'])) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->where('uploader_id', $user->id)
                ->orWhere(function (Builder $qt) use ($user): void {
                    $qt->whereNotNull('task_id')
                        ->whereHas('task', function (Builder $taskQuery) use ($user): void {
                            $taskQuery->where('tasks.pic_id', $user->id)
                                ->orWhere('tasks.created_by', $user->id)
                                ->orWhereHas('coPics', function (Builder $coPicQuery) use ($user): void {
                                    $coPicQuery->where('users.id', $user->id);
                                });
                        });
                })
                ->orWhere(function (Builder $qp) use ($user): void {
                    $qp->whereNotNull('project_id')
                        ->whereHas('project', function (Builder $projectQuery) use ($user): void {
                            $projectQuery->where('projects.created_by', $user->id)
                                ->orWhereHas('projectLeaders', function (Builder $leaderQuery) use ($user): void {
                                    $leaderQuery->where('user_id', $user->id);
                                });
                        });
                });
        });
    }
}
