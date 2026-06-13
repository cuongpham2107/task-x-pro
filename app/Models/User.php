<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles;

    protected string $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_code',
        'name',
        'email',
        'password',
        'avatar',
        'phone',
        'job_title',
        'department_id',
        'status',
        'telegram_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'department_id' => 'integer',
            'status' => UserStatus::class,
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function headedDepartment(): HasOne
    {
        return $this->hasOne(Department::class, 'head_user_id');
    }

    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function projectLeaderAssignments(): HasMany
    {
        return $this->hasMany(ProjectLeader::class, 'user_id');
    }

    public function assignedProjectLeaders(): HasMany
    {
        return $this->hasMany(ProjectLeader::class, 'assigned_by');
    }

    public function leadingProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_leaders')
            ->withPivot(['id', 'assigned_at', 'assigned_by']);
    }

    public function picTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'pic_id');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function taskCoPicAssignments(): HasMany
    {
        return $this->hasMany(TaskCoPic::class);
    }

    public function coPicTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_co_pics')
            ->withPivot(['assigned_at']);
    }

    public function uploadedTaskAttachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class, 'uploader_id');
    }

    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function createdSlaConfigs(): HasMany
    {
        return $this->hasMany(SlaConfig::class, 'created_by');
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'reviewer_id');
    }

    public function kpiScores(): HasMany
    {
        return $this->hasMany(KpiScore::class);
    }

    public function systemNotifications(): HasMany
    {
        return $this->hasMany(SystemNotification::class);
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'uploader_id');
    }

    public function uploadedDocumentVersions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'uploader_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    protected function avatarUrl(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(function () {
            if ($this->avatar && ! str_contains((string) $this->avatar, 'via.placeholder.com')) {
                if (str_starts_with((string) $this->avatar, 'http')) {
                    return $this->avatar;
                }

                return \Illuminate\Support\Facades\Storage::url($this->avatar);
            }

            $backgroundColors = [
                '111827', '0F172A', '1E3A8A', '065F46',
                '7C2D12', '6D28D9', '9D174D',
            ];

            $key = $this->id ?? $this->email ?? $this->name;
            $hash = is_int($key) ? (int) $key : crc32((string) $key);
            $bg = $backgroundColors[$hash % count($backgroundColors)];

            return 'https://ui-avatars.com/api/?name='.urlencode((string) $this->name)
                .'&background='.$bg
                .'&color=FFFFFF'
                .'&bold=true';
        });
    }
}
