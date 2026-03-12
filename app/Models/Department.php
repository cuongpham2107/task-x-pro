<?php

namespace App\Models;

use App\Enums\DepartmentStatus;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Department extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'head_user_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'head_user_id' => 'integer',
            'status' => DepartmentStatus::class,
        ];
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function activeUsers(): HasMany
    {
        return $this->users()->where('status', UserStatus::Active->value);
    }

    public function slaConfigs(): HasMany
    {
        return $this->hasMany(SlaConfig::class);
    }

    public function kpiScores(): HasManyThrough
    {
        return $this->hasManyThrough(
            KpiScore::class,
            User::class,
            'department_id',
            'user_id',
        );
    }
}
