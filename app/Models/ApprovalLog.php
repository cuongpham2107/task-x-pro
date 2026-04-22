<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;
    
    /**
     * Tu dong dong bo KPI khi co approval log moi.
     */
    protected static function booted(): void
    {
        static::saved(function (ApprovalLog $log): void {
            if ($log->task && $log->task->pic_id) {
                KpiScore::syncForUser($log->task->pic_id);
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'reviewer_id',
        'approval_level',
        'action',
        'star_rating',
        'comment',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'star_rating' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
