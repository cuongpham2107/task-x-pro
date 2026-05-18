<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\ApprovalLog;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\KpiScore;
use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectLeader;
use App\Models\ProjectType as ProjectTypeModel;
use App\Models\SlaConfig;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskCoPic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ExcelImportSeeder extends Seeder
{
    public function run(): void
    {
        $jsonDir = database_path('seeders/excel_data');

        DB::transaction(function () use ($jsonDir) {
            $this->seedPhaseTemplates();
            $departments = $this->seedDepartments();
            $users = $this->importUsers($jsonDir, $departments);
            $this->assignDepartmentHeads($departments, $users['ceo'], $users['leaders']);
            $this->seedAuthorization($users['ceo'], $users['leaders'], $users['pics'], $users['admin'] ?? null);

            $this->seedSlaConfigs($departments, $users['ceo']);

            $projects = $this->importProjects($jsonDir, $users['leaders'], $users['ceo']);

            $this->seedProjectLeaders($projects, $users['leaders'], $users['ceo']);

            // Đếm số tasks mỗi project để tạo phases trước khi import tasks
            $taskCounts = $this->countTasksByProject($jsonDir);
            $this->createPhasesFromCounts($projects, $taskCounts);

            $this->importTasksData($jsonDir, $projects, $users['leaders'], $users['pics'], $users['ceo'], $taskCounts);

            // Cập nhật phases dựa trên tasks thực tế (dates, weights, progress)
            $this->refinePhasesFromTasks($projects);

            $this->importActivityLogsData($jsonDir, $projects, $users);
            $this->seedKpiScores($users['pics']);
        });
    }

    private function seedDepartments(): Collection
    {
        $departmentData = [
            ['code' => 'BGD', 'name' => 'Ban Giam Doc'],
            ['code' => 'IT', 'name' => 'Cong nghe thong tin'],
            ['code' => 'OPS', 'name' => 'Van hanh du an'],
            ['code' => 'LOG', 'name' => 'Logistics'],
            ['code' => 'QA', 'name' => 'Kiem soat chat luong'],
            ['code' => 'FIN', 'name' => 'Tai chinh ke toan'],
            ['code' => 'HR', 'name' => 'Hanh chinh nhan su'],
            ['code' => 'SALE', 'name' => 'Kinh doanh'],
        ];

        return collect($departmentData)
            ->map(function (array $item): Department {
                return Department::query()->updateOrCreate(
                    ['code' => $item['code']],
                    [
                        'name' => $item['name'],
                        'status' => 'active',
                    ]
                );
            })
            ->values();
    }

    private function importUsers(string $jsonDir, Collection $departments): array
    {
        $usersData = json_decode(file_get_contents("$jsonDir/users.json"), true);
        if (! $usersData) {
            $this->command->warn('No users data found.');

            return ['ceo' => null, 'leaders' => collect(), 'pics' => collect(), 'admin' => null];
        }

        $headOfficeDepartmentId = $departments->firstWhere('code', 'BGD')?->id ?? $departments->first()?->id;
        $supportDepartments = $departments->where('code', '!=', 'BGD')->values();
        $defaultLeaderDepartmentId = $departments->firstWhere('code', 'IT')?->id
            ?? $supportDepartments->first()?->id
            ?? $headOfficeDepartmentId;
        $defaultPicDepartmentId = $departments->firstWhere('code', 'OPS')?->id
            ?? $supportDepartments->first()?->id
            ?? $headOfficeDepartmentId;

        $roleMap = [
            'Admin' => 'ceo',
            'Quản lý' => 'leader',
        ];

        $leaders = collect();
        $pics = collect();
        $ceo = null;
        $admin = null;

        foreach ($usersData as $u) {
            $empCode = $u['Mã NV'];
            $roleName = $u['Phân quyền'] ?? 'User';

            // Determine department from Excel data if available, otherwise fallback by role
            $deptCode = $this->mapDepartmentCode($u['Bộ phận'] ?? '');
            $deptId = match ($deptCode) {
                'BGD' => $headOfficeDepartmentId,
                'IT' => $departments->firstWhere('code', 'IT')?->id ?? $defaultLeaderDepartmentId,
                'OPS' => $departments->firstWhere('code', 'OPS')?->id ?? $defaultPicDepartmentId,
                'LOG' => $departments->firstWhere('code', 'LOG')?->id ?? $defaultPicDepartmentId,
                'QA' => $departments->firstWhere('code', 'QA')?->id ?? $defaultPicDepartmentId,
                'FIN' => $departments->firstWhere('code', 'FIN')?->id ?? $defaultPicDepartmentId,
                'HR' => $departments->firstWhere('code', 'HR')?->id ?? $defaultLeaderDepartmentId,
                'SALE' => $departments->firstWhere('code', 'SALE')?->id ?? $defaultLeaderDepartmentId,
                default => $roleName === 'Admin' ? $headOfficeDepartmentId : ($roleName === 'Quản lý' ? $defaultLeaderDepartmentId : $defaultPicDepartmentId),
            };

            $user = User::firstOrCreate(
                ['employee_code' => $empCode],
                [
                    'name' => $u['Họ tên'],
                    'email' => $u['Email'],
                    'password' => Hash::make($u['Mật khẩu']),
                    'phone' => $u['SĐT'] ?? null,
                    'job_title' => $u['Chức vụ'] ?? null,
                    'department_id' => $deptId,
                    'status' => UserStatus::Active,
                ]
            );

            if ($roleName === 'Admin' && ! $ceo) {
                $ceo = $user;
            } elseif ($roleName === 'Quản lý') {
                $leaders->push($user);
            } else {
                $pics->push($user);
            }

            if ($roleName === 'Admin' && ! $admin) {
                $admin = $user;
            }
        }

        // Ensure we have at least one CEO and admin
        if (! $ceo && $admin) {
            $ceo = $admin;
        }

        // Ensure super_admin user exists
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Super Admin',
                'employee_code' => 'SUPER001',
                'password' => Hash::make('password'),
                'phone' => '0900000000',
                'job_title' => 'System Administrator',
                'department_id' => $headOfficeDepartmentId,
                'status' => UserStatus::Active,
            ]
        );

        if (! $admin) {
            $admin = $superAdmin;
        }

        return [
            'ceo' => $ceo,
            'leaders' => $leaders->unique('id')->values(),
            'pics' => $pics->unique('id')->values(),
            'admin' => $superAdmin,
        ];
    }

    private function seedAuthorization(User $ceo, Collection $leaders, Collection $pics, ?User $admin = null): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionsByRole = [
            'ceo' => [
                'project.view', 'project.create', 'project.update', 'project.delete',
                'phase.view', 'phase.create', 'phase.update',
                'task.view', 'task.create', 'task.update', 'task.assign', 'task.approve', 'task.delete',
                'document.view', 'document.upload', 'document.manage',
                'kpi.view', 'kpi.manage',
                'notification.view', 'notification.manage',
                'sla.view', 'sla.manage',
                'user.view', 'user.create', 'user.update', 'user.delete',
                'department.view', 'department.create', 'department.update', 'department.delete',
                'phase_template.view', 'phase_template.create', 'phase_template.update', 'phase_template.delete',
                'activity_log.view',
            ],
            'leader' => [
                'project.view', 'project.create', 'project.update', 'project.delete',
                'phase.view', 'phase.create', 'phase.update',
                'task.view', 'task.create', 'task.update', 'task.assign', 'task.approve', 'task.delete',
                'document.view', 'document.upload', 'document.manage',
                'kpi.view',
                'notification.view', 'notification.manage',
                'sla.view',
            ],
            'pic' => [
                'project.view',
                'phase.view',
                'task.view',
                'task.update',
                'document.view',
                'document.upload',
                'notification.view',
                'kpi.view',
            ],
            'super_admin' => [
                'super_admin',
            ],
        ];

        $allPermissions = collect($permissionsByRole)
            ->flatten()
            ->unique()
            ->values();

        foreach ($allPermissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissionsByRole as $roleName => $permissionNames) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissionNames);
        }

        if ($ceo) {
            $ceo->syncRoles(['ceo', 'super_admin']);
        }
        $leaders->each(fn (User $leader) => $leader->syncRoles(['leader']));
        $pics->each(fn (User $pic) => $pic->syncRoles(['pic']));

        // Ensure admin gets super_admin role
        if ($admin) {
            $admin->syncRoles(['super_admin']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function assignDepartmentHeads(Collection $departments, User $ceo, Collection $leaders): void
    {
        $departments->each(function (Department $department) use ($ceo, $leaders): void {
            $headUserId = $department->code === 'BGD'
                ? $ceo->id
                : ($leaders->isNotEmpty() ? $leaders->random()->id : $ceo->id);

            $department->update([
                'head_user_id' => $headUserId,
            ]);
        });
    }

    private function seedSlaConfigs(Collection $departments, User $ceo): void
    {
        $effectiveDate = now()->startOfYear()->toDateString();

        foreach ($departments as $department) {
            SlaConfig::query()->updateOrCreate(
                [
                    'department_id' => $department->id,
                    'task_type' => 'technical',
                    'project_type' => 'software',
                    'effective_date' => $effectiveDate,
                ],
                [
                    'standard_hours' => 24,
                    'expired_date' => null,
                    'note' => 'SLA mac dinh cho cong viec ky thuat phan mem.',
                    'created_by' => $ceo->id,
                ]
            );

            SlaConfig::query()->updateOrCreate(
                [
                    'department_id' => $department->id,
                    'task_type' => 'operation',
                    'project_type' => 'warehouse',
                    'effective_date' => $effectiveDate,
                ],
                [
                    'standard_hours' => 36,
                    'expired_date' => null,
                    'note' => 'SLA cho cong viec van hanh kho.',
                    'created_by' => $ceo->id,
                ]
            );

            SlaConfig::query()->updateOrCreate(
                [
                    'department_id' => $department->id,
                    'task_type' => 'all',
                    'project_type' => 'all',
                    'effective_date' => $effectiveDate,
                ],
                [
                    'standard_hours' => 48,
                    'expired_date' => null,
                    'note' => 'SLA chung cho phong ban.',
                    'created_by' => $ceo->id,
                ]
            );
        }

        SlaConfig::query()->updateOrCreate(
            [
                'department_id' => null,
                'task_type' => 'all',
                'project_type' => 'all',
                'effective_date' => $effectiveDate,
            ],
            [
                'standard_hours' => 48,
                'expired_date' => null,
                'note' => 'SLA chung toan cong ty.',
                'created_by' => $ceo->id,
            ]
        );
    }

    private function seedPhaseTemplates(): void
    {
        // Không dùng phase templates nữa — phases được tạo động từ tasks thực tế
        $this->command->info('Skipping phase templates — dynamic phases will be created from tasks.');
    }

    private function importProjects(string $jsonDir, Collection $leaders, User $ceo): Collection
    {
        $projectsData = json_decode(file_get_contents("$jsonDir/projects.json"), true);
        if (! $projectsData) {
            $this->command->warn('No projects data found.');

            return collect();
        }

        $userLookup = [];
        foreach ($ceo ? [$ceo] : [] as $u) {
            $userLookup[$u->name] = $u;
            $userLookup[$u->email] = $u;
        }
        foreach ($leaders as $user) {
            $userLookup[$user->name] = $user;
            $userLookup[$user->email] = $user;
        }

        $projects = collect();

        foreach ($projectsData as $p) {
            $managerName = $p['Quản lý dự án'] ?? '';
            $manager = $userLookup[$managerName] ?? ($leaders->first() ?? $ceo);
            if (! $manager) {
                $this->command->warn("Manager not found: $managerName");

                continue;
            }

            $status = $this->mapProjectStatus($p['Trạng thái dự án'] ?? '');
            $startDate = $this->parseDate($p['Ngày bắt đầu'] ?? '') ?: now()->toDateString();
            $endDate = $this->parseDate($p['Ngày kết thúc'] ?? '');

            // Resolve default project type to existing DB entry (software) or fallback to raw string
            $defaultType = ProjectTypeModel::query()->where('key', 'software')->first();
            $project = Project::firstOrCreate(
                ['name' => $p['Tên dự án']],
                [
                    'type' => $defaultType?->key ?? 'software',
                    'project_type_id' => $defaultType?->id,
                    'status' => $status,
                    'objective' => $p['Mô tả dự án'] ?? '',
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'progress' => 0,
                    'created_by' => $manager->id,
                ]
            );

            // Phases sẽ được tạo động từ tasks sau khi import tasks
            $projects->push($project);
        }

        $this->command->info('Imported '.$projects->count().' projects (phases will be created from tasks).');

        return $projects;
    }

    private function createPhasesFromTemplate(Project $project, int $createdBy, $allTemplates): void
    {
        $typeValue = $project->projectType?->key ?? ($project->type instanceof \BackedEnum ? $project->type->value : (string) $project->type);
        $templates = $allTemplates->where('project_type', $typeValue)->values();

        if ($templates->isEmpty()) {
            $this->command->warn("No phase templates found for type: $typeValue");

            return;
        }

        // Delete existing phases for this project to avoid duplicates/old data
        $project->phases()->delete();

        $projectStart = Carbon::parse($project->start_date);
        $projectEnd = $project->end_date ? Carbon::parse($project->end_date) : null;

        $totalDefault = $templates->sum('default_duration_days');
        $projectDurationDays = $projectEnd ? $projectStart->diffInDays($projectEnd) + 1 : $totalDefault;
        $scale = ($projectDurationDays < $totalDefault && $totalDefault > 0) ? ($projectDurationDays / $totalDefault) : 1;

        $currentStart = $projectStart->copy();
        $lastIndex = $templates->count() - 1;

        $projectStatus = $project->status instanceof \BackedEnum ? $project->status->value : $project->status;

        foreach ($templates as $index => $template) {
            $durationDays = $template->default_duration_days;
            if ($scale < 1) {
                $durationDays = max(1, (int) round($durationDays * $scale));
            }

            $phaseStart = $currentStart->copy();
            if ($projectEnd && $index === $lastIndex) {
                $phaseEnd = $projectEnd->copy();
            } else {
                $phaseEnd = $phaseStart->copy()->addDays($durationDays - 1);
                if ($projectEnd && $phaseEnd->gt($projectEnd)) {
                    $phaseEnd = $projectEnd->copy();
                }
            }

            $phaseStatus = 'pending';
            if ($projectStatus === 'completed' || $projectStatus === 'cancelled') {
                $phaseStatus = 'completed';
            } elseif ($projectStatus === 'running') {
                if (now()->gt($phaseEnd)) {
                    $phaseStatus = 'completed';
                } elseif (now()->gte($phaseStart)) {
                    $phaseStatus = 'active';
                }
            }

            $progress = (int) round(($template->default_weight / 100) * $project->progress);

            Phase::create([
                'project_id' => $project->id,
                'name' => $template->phase_name,
                'description' => $template->phase_description,
                'weight' => $template->default_weight,
                'order_index' => $template->order_index,
                'start_date' => $phaseStart->toDateString(),
                'end_date' => $phaseEnd->toDateString(),
                'progress' => $progress,
                'status' => $phaseStatus,
                'is_template' => false,
                'created_by' => $createdBy,
            ]);

            $currentStart = $phaseEnd->copy()->addDay();
        }
    }

    private function seedProjectLeaders(Collection $projects, Collection $leaders, User $ceo): void
    {
        if ($leaders->isEmpty()) {
            return;
        }

        foreach ($projects->values() as $projectIndex => $project) {
            $leaderCount = $leaders->count();
            $assignedLeaderIds = collect([
                $leaders->get($projectIndex % $leaderCount)?->id,
                $leaders->get(($projectIndex + 1) % $leaderCount)?->id,
            ])
                ->filter()
                ->unique()
                ->values();

            foreach ($assignedLeaderIds as $leaderId) {
                ProjectLeader::query()->firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'user_id' => $leaderId,
                    ],
                    [
                        'assigned_by' => $ceo->id,
                        'assigned_at' => now()->subDays(rand(1, 30)),
                    ]
                );
            }
        }
    }

    private function importTasksData(string $jsonDir, Collection $projects, Collection $leaders, Collection $pics, User $ceo, Collection $taskCounts): void
    {
        $tasksData = json_decode(file_get_contents("$jsonDir/tasks.json"), true);
        if (! $tasksData) {
            $this->command->warn('No tasks data found.');

            return;
        }

        $userLookup = [];
        foreach ($leaders as $u) {
            $userLookup[$u->name] = $u;
            $userLookup[$u->email] = $u;
        }
        foreach ($pics as $u) {
            $userLookup[$u->name] = $u;
            $userLookup[$u->email] = $u;
        }

        $projectsData = json_decode(file_get_contents("$jsonDir/projects.json"), true);
        $codeToName = [];
        foreach ($projectsData as $p) {
            $codeToName[$p['Mã dự án']] = $p['Tên dự án'];
        }

        // Track task index for each project to assign phases correctly
        $taskIndexPerProject = [];

        $taskCount = 0;

        foreach ($tasksData as $t) {
            $projectCode = $t['project_code'] ?? '';
            $projectName = $codeToName[$projectCode] ?? null;
            if (! $projectName) {
                continue;
            }

            $project = $projects->firstWhere('name', $projectName);
            if (! $project) {
                continue;
            }

            $assigneeName = $t['Người thực hiện'] ?? '';
            $assignee = $userLookup[$assigneeName] ?? $pics->first();
            if (! $assignee) {
                continue;
            }

            $status = $this->mapTaskStatus($t['Trạng thái'] ?? '');
            $priority = $this->mapTaskPriority($t['Ưu tiên'] ?? '');
            $startedAt = $this->parseDate($t['Ngày bắt đầu'] ?? '');
            $completedAt = $this->parseDate($t['Ngày hoàn thành'] ?? '');

            $taskTypeRaw = $t['Loại công việc'] ?? 'Khác';
            $taskTypeSLA = $this->mapTaskTypeToSLA($taskTypeRaw);
            $projectTypeKey = $project->projectType?->key ?? ($project->type instanceof \BackedEnum ? $project->type->value : (string) $project->type);
            $slaHours = $this->getSlaHours($assignee, $taskTypeSLA, $projectTypeKey);

            $deadline = $this->parseDate($t['Hạn chót'] ?? '');
            if (! $deadline && $startedAt && $slaHours) {
                $deadline = Carbon::parse($startedAt)->addHours($slaHours)->toDateString();
            } elseif (! $deadline) {
                $deadline = $startedAt ?: now()->toDateString();
            }

            $urls = [];
            if (! empty($t['Link kết quả'])) {
                $urls[] = $t['Link kết quả'];
            }
            if (! empty($t['Kết quả đầu ra'])) {
                $urls[] = $t['Kết quả đầu ra'];
            }
            $deliverableUrls = ! empty($urls) ? json_encode($urls, JSON_UNESCAPED_UNICODE) : null;

            $workflowType = (rand(0, 2) === 0) ? 'double' : 'single'; // 33% double approval

            // Get total tasks and phases for this project
            $totalTasks = $taskCounts->get($projectName, 0);
            $phases = $project->phases()->orderBy('order_index')->get();
            $numPhases = $phases->count();

            // Track task index per project
            $currentIdx = ($taskIndexPerProject[$projectName] ?? 0) + 1;
            $taskIndexPerProject[$projectName] = $currentIdx;

            // Determine which phase this task belongs to
            $phaseId = null;
            if ($numPhases > 0 && $totalTasks > 0) {
                // Tasks per phase (roughly)
                $tasksPerPhase = ceil($totalTasks / $numPhases);
                // Which phase (1-indexed)
                $phaseOrder = ceil($currentIdx / $tasksPerPhase);
                // Get the phase with this order
                $phase = $phases->where('order_index', $phaseOrder)->first();
                if ($phase) {
                    $phaseId = $phase->id;
                }
            }

            $task = Task::create([
                'phase_id' => $phaseId ?? 1, // Default to phase 1 if no phase found
                'name' => $t['Tên nhiệm vụ'] ?? '',
                'description' => $t['Mô tả nhiệm vụ'] ?? '',
                'type' => TaskType::Other->value,
                'status' => $status,
                'priority' => $priority,
                'pic_id' => $assignee->id,
                'deadline' => $deadline,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'progress' => (int) ($t['Tiến độ (%)'] ?? 0),
                'deliverable_urls' => $deliverableUrls,
                'workflow_type' => $workflowType,
                'sla_standard_hours' => $slaHours,
                'created_by' => $assignee->id,
            ]);

            // Add co-pic randomly (30% chance)
            if (rand(0, 9) < 3) {
                $coPic = $pics->where('id', '!=', $assignee->id)->random() ?? null;
                if ($coPic) {
                    TaskCoPic::create([
                        'task_id' => $task->id,
                        'user_id' => $coPic->id,
                        'assigned_at' => now(),
                    ]);
                }
            }

            // Add comment if not pending
            if ($status !== 'pending') {
                TaskComment::create([
                    'task_id' => $task->id,
                    'user_id' => $task->pic_id,
                    'content' => 'Cập nhật tiến độ công việc.',
                ]);
            }

            // Add attachment & document if waiting approval or completed
            if (in_array($status, ['waiting_approval', 'completed'], true)) {
                $attachment = TaskAttachment::create([
                    'task_id' => $task->id,
                    'uploader_id' => $task->pic_id,
                    'original_name' => 'report_v1.pdf',
                    'stored_path' => '',
                    'disk' => config('media-library.disk_name', 'public'),
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 2048,
                    'version' => 1,
                ]);

                $document = Document::create([
                    'task_id' => $task->id,
                    'project_id' => $project->id,
                    'name' => 'Giao phẩm Task #'.$task->id,
                    'uploader_id' => $task->pic_id,
                    'document_type' => 'deliverable',
                    'current_version' => 1,
                    'permission' => 'edit',
                ]);

                DocumentVersion::create([
                    'document_id' => $document->id,
                    'version_number' => 1,
                    'uploader_id' => $task->pic_id,
                    'stored_path' => 'docs/v1.pdf',
                    'change_summary' => 'Initial version',
                    'file_size_bytes' => 5000,
                ]);
            }

            // Add approval log if status is waiting approval or completed
            if (in_array($status, ['waiting_approval', 'completed'], true)) {
                $reviewer = $leaders->first() ?? $ceo;
                ApprovalLog::create([
                    'task_id' => $task->id,
                    'reviewer_id' => $reviewer?->id ?? $ceo->id,
                    'approval_level' => 'leader',
                    'action' => $status === 'completed' ? 'approved' : 'submitted',
                    'star_rating' => $status === 'completed' ? rand(4, 5) : null,
                    'comment' => 'Reviewer comment.',
                    'created_at' => now()->subHours(5),
                ]);

                if ($status === 'completed' && $workflowType === 'double') {
                    ApprovalLog::create([
                        'task_id' => $task->id,
                        'reviewer_id' => $ceo->id,
                        'approval_level' => 'ceo',
                        'action' => 'approved',
                        'star_rating' => 5,
                        'comment' => 'CEO Approved.',
                        'created_at' => now()->subHours(1),
                    ]);
                }
            }

            $taskCount++;
        }

        $this->command->info('Imported '.$taskCount.' tasks.');
    }

    private function getSlaHours(?User $user, string $taskType, $projectType): ?int
    {
        if (! $user || ! $user->department_id) {
            return 48; // fallback global
        }

        // Convert project type enum to string
        $projectTypeStr = $projectType instanceof \BackedEnum ? $projectType->value : (string) $projectType;

        // Try specific SLA: department + task_type + project_type
        $specific = SlaConfig::where('department_id', $user->department_id)
            ->where('task_type', $taskType)
            ->where('project_type', $projectTypeStr)
            ->first();

        if ($specific) {
            return (int) $specific->standard_hours;
        }

        // Try generic department SLA (all tasks, all projects)
        $deptGeneric = SlaConfig::where('department_id', $user->department_id)
            ->where('task_type', 'all')
            ->where('project_type', 'all')
            ->first();

        if ($deptGeneric) {
            return (int) $deptGeneric->standard_hours;
        }

        return 48; // global fallback
    }

    private function importActivityLogsData(string $jsonDir, Collection $projects, array $users): void
    {
        $logsData = json_decode(file_get_contents("$jsonDir/activity_logs.json"), true);
        if (! $logsData) {
            $this->command->warn('No activity logs data found.');

            return;
        }

        $userLookup = [];
        foreach ($users['leaders'] as $u) {
            $userLookup[$u->email] = $u;
        }
        foreach ($users['pics'] as $u) {
            $userLookup[$u->email] = $u;
        }
        if ($users['ceo']) {
            $userLookup[$users['ceo']->email] = $users['ceo'];
        }

        $projectsData = json_decode(file_get_contents("$jsonDir/projects.json"), true);
        $codeToName = [];
        foreach ($projectsData as $p) {
            $codeToName[$p['Mã dự án']] = $p['Tên dự án'];
        }

        $taskLookup = [];
        foreach (Task::with('phase.project')->get() as $task) {
            $proj = $task->phase->project;
            $key = $proj->id.'|'.$task->name;
            $taskLookup[$key] = $task;
        }

        $count = 0;
        foreach ($logsData as $log) {
            $actor = $userLookup[$log['Người thực hiện'] ?? ''] ?? null;
            if (! $actor) {
                continue;
            }

            $detail = $log['Chi tiết'] ?? '';
            $time = $this->parseDateTime($log['Thời gian'] ?? '');

            $entityType = null;
            $entityId = null;

            if (preg_match('/ID:\s*([A-Z0-9]+(?:-[0-9]+)?)/', $detail, $m)) {
                $code = $m[1];
                if (str_contains($code, '-')) {
                    [$projCode, $taskPart] = explode('-', $code, 2);
                    $projectName = $codeToName[$projCode] ?? null;
                    if ($projectName) {
                        $project = $projects->firstWhere('name', $projectName);
                        if ($project && preg_match('/Tên:\s*([^,]+)/', $detail, $m2)) {
                            $taskName = trim($m2[1]);
                            $key = $project->id.'|'.$taskName;
                            if (isset($taskLookup[$key])) {
                                $entityType = Task::class;
                                $entityId = $taskLookup[$key]->id;
                            }
                        }
                    }
                } else {
                    $projectName = $codeToName[$code] ?? null;
                    if ($projectName) {
                        $project = $projects->firstWhere('name', $projectName);
                        if ($project) {
                            $entityType = Project::class;
                            $entityId = $project->id;
                        }
                    }
                }
            }

            if (! $entityType || ! $entityId) {
                continue;
            }

            $action = $this->mapActivityAction($log['Hành động'] ?? '');

            ActivityLog::create([
                'user_id' => $actor->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'old_values' => [],
                'new_values' => [],
                'ip_address' => null,
                'user_agent' => null,
                'created_at' => $time,
            ]);
            $count++;
        }

        $this->command->info('Imported '.$count.' activity logs.');
    }

    private function mapActivityAction(string $action): string
    {
        return match (trim($action)) {
            'Thêm dự án' => 'created',
            'Cập nhật dự án' => 'updated',
            'Xóa dự án' => 'deleted',
            'Thêm nhiệm vụ' => 'created',
            'Cập nhật nhiệm vụ' => 'updated',
            'Xóa nhiệm vụ' => 'deleted',
            default => $action,
        };
    }

    private function mapProjectStatus(string $status): string
    {
        return match (trim($status)) {
            'Đang thực hiện' => ProjectStatus::Running->value,
            'Chưa bắt đầu' => ProjectStatus::Init->value,
            'Hoàn thành' => ProjectStatus::Completed->value,
            'Tạm dừng' => ProjectStatus::Paused->value,
            'Đã hủy' => ProjectStatus::Cancelled->value,
            'Quá hạn' => ProjectStatus::Overdue->value,
            default => ProjectStatus::Init->value,
        };
    }

    private function mapTaskStatus(string $status): string
    {
        return match (trim($status)) {
            'Hoàn thành' => TaskStatus::Completed->value,
            'Đang thực hiện' => TaskStatus::InProgress->value,
            'Chưa bắt đầu' => TaskStatus::Pending->value,
            'Trễ hạn' => TaskStatus::Late->value,
            'Chờ duyệt' => TaskStatus::WaitingApproval->value,
            'Đã hủy' => TaskStatus::Cancelled->value,
            default => TaskStatus::Pending->value,
        };
    }

    private function mapTaskPriority(string $priority): string
    {
        return match (trim($priority)) {
            'Cao' => TaskPriority::High->value,
            'Trung bình' => TaskPriority::Medium->value,
            'Thấp' => TaskPriority::Low->value,
            'Khẩn cấp' => TaskPriority::Urgent->value,
            default => TaskPriority::Medium->value,
        };
    }

    private function mapTaskTypeToSLA(string $type): string
    {
        return match (trim(strtolower($type))) {
            'vận hành', 'operation', 'van hanh' => 'operation',
            'kỹ thuật', 'technical', 'ky thuat' => 'technical',
            'báo cáo', 'report', 'bao cao' => 'report',
            'hành chính', 'admin', 'hanh chinh' => 'admin',
            default => 'other',
        };
    }

    private function parseDate(?string $dateStr): ?string
    {
        if (! $dateStr) {
            return null;
        }
        try {
            $dt = Carbon::parse($dateStr);

            return $dt->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseDateTime(?string $dateStr): ?string
    {
        if (! $dateStr) {
            return null;
        }
        try {
            $dt = Carbon::parse($dateStr);

            return $dt->toDateTimeString();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function mapDepartmentCode(?string $deptName): string
    {
        if (! $deptName) {
            return '';
        }
        $name = strtolower(trim($deptName));

        return match (true) {
            str_contains($name, 'giám đốc') || str_contains($name, 'ban giam doc') || str_contains($name, 'bgd') => 'BGD',
            str_contains($name, 'công nghệ') || str_contains($name, 'it') => 'IT',
            str_contains($name, 'vận hành') || str_contains($name, 'van hanh') || str_contains($name, 'ops') => 'OPS',
            str_contains($name, 'logistics') || str_contains($name, 'log') => 'LOG',
            str_contains($name, 'kiểm soát') || str_contains($name, 'kiem soat') || str_contains($name, 'qa') => 'QA',
            str_contains($name, 'tài chính') || str_contains($name, 'tai chinh') || str_contains($name, 'fin') => 'FIN',
            str_contains($name, 'nhân sự') || str_contains($name, 'hanh chinh') || str_contains($name, 'hr') => 'HR',
            str_contains($name, 'kinh doanh') || str_contains($name, 'sale') => 'SALE',
            default => '',
        };
    }

    private function seedKpiScores(Collection $pics): void
    {
        foreach ($pics as $pic) {
            KpiScore::syncForUser((int) $pic->id);
        }
    }

    /**
     * Đếm số tasks mỗi project từ JSON (project_name => count)
     */
    private function countTasksByProject(string $jsonDir): Collection
    {
        $tasksData = json_decode(file_get_contents("$jsonDir/tasks.json"), true);
        $projectsData = json_decode(file_get_contents("$jsonDir/projects.json"), true);

        $codeToName = [];
        foreach ($projectsData as $p) {
            $codeToName[$p['Mã dự án']] = $p['Tên dự án'];
        }

        $counts = collect();
        foreach ($tasksData as $t) {
            $code = $t['project_code'] ?? '';
            $name = $codeToName[$code] ?? null;
            if ($name) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Tạo phases dựa trên số lượng tasks ước lượng (trước khi import tasks chi tiết)
     * Đảm bảo phase_id có giá trị khi import tasks
     */
    private function createPhasesFromCounts(Collection $projects, Collection $taskCounts): void
    {
        foreach ($projects as $project) {
            $totalTasks = $taskCounts->get($project->name, 0);

            if ($totalTasks == 0) {
                $this->createDefaultPhase($project);

                continue;
            }

            $numPhases = match (true) {
                $totalTasks <= 4 => 1,
                $totalTasks <= 10 => 2,
                $totalTasks <= 20 => 3,
                $totalTasks <= 30 => 4,
                default => 5,
            };

            $projectStart = Carbon::parse($project->start_date);
            $projectEnd = $project->end_date ? Carbon::parse($project->end_date) : $projectStart->copy()->addDays($totalTasks * 2);
            $totalDays = $projectStart->diffInDays($projectEnd) + 1;
            $daysPerPhase = max(1, (int) ceil($totalDays / $numPhases));

            $currentStart = $projectStart->copy();
            $weightPerPhase = round(100 / $numPhases, 2);
            $createdBy = $project->created_by;

            for ($i = 1; $i <= $numPhases; $i++) {
                $phaseStart = $currentStart->copy();
                if ($i === $numPhases) {
                    $phaseEnd = $projectEnd->copy();
                } else {
                    $phaseEnd = $phaseStart->copy()->addDays($daysPerPhase - 1);
                    if ($phaseEnd->gt($projectEnd)) {
                        $phaseEnd = $projectEnd->copy();
                    }
                }

                $phaseName = $this->getPhaseName($i);

                Phase::create([
                    'project_id' => $project->id,
                    'name' => $phaseName,
                    'description' => 'Dự kiến chứa ~'.ceil($totalTasks / $numPhases).' tasks.',
                    'weight' => $weightPerPhase,
                    'order_index' => $i,
                    'start_date' => $phaseStart->toDateString(),
                    'end_date' => $phaseEnd->toDateString(),
                    'progress' => 0,
                    'status' => 'pending',
                    'is_template' => false,
                    'created_by' => $createdBy,
                ]);

                $currentStart = $phaseEnd->copy()->addDay();
            }
        }
    }

    private function getPhaseName(int $index): string
    {
        return match ($index) {
            1 => 'Giai đoạn 1 - Khởi động',
            2 => 'Giai đoạn 2 - Phát triển',
            3 => 'Giai đoạn 3 - Hoàn thiện',
            4 => 'Giai đoạn 4 - Triển khai',
            5 => 'Giai đoạn 5 - Bàn giao',
            default => 'Giai đoạn '.$index,
        };
    }

    private function createDefaultPhase(Project $project): void
    {
        Phase::create([
            'project_id' => $project->id,
            'name' => 'Giai đoạn 1 - Khởi động',
            'description' => 'Không có task nào được import',
            'weight' => 100,
            'order_index' => 1,
            'start_date' => $project->start_date,
            'end_date' => $project->end_date ?? now()->addMonths(6)->toDateString(),
            'progress' => 0,
            'status' => 'pending',
            'is_template' => false,
            'created_by' => $project->created_by,
        ]);
    }

    /**
     * Sau khi import tasks, cập nhật phases với thông tin thực tế từ tasks
     * - dates (min start, max deadline)
     * - weight theo số tasks thực tế
     * - status và progress từ tasks
     */
    private function refinePhasesFromTasks(Collection $projects): void
    {
        foreach ($projects as $project) {
            $phases = $project->phases()->orderBy('order_index')->get();
            if ($phases->isEmpty()) {
                continue;
            }

            $totalTasks = $project->tasks()->count();
            if ($totalTasks == 0) {
                continue;
            }

            foreach ($phases as $phase) {
                $tasks = $phase->tasks;
                $count = $tasks->count();
                if ($count == 0) {
                    continue;
                }

                // Weight theo số tasks thực tế
                $weight = round(($count / $totalTasks) * 100, 2);

                // Dates từ tasks
                $startDates = $tasks->pluck('started_at')->filter()->map(fn ($d) => is_string($d) ? substr($d, 0, 10) : $d?->toDateString())->filter();
                $deadlines = $tasks->pluck('deadline')->filter()->map(fn ($d) => is_string($d) ? substr($d, 0, 10) : $d?->toDateString())->filter();

                $phaseStart = $startDates->min() ?? $phase->start_date;
                $phaseEnd = $deadlines->max() ?? $phase->end_date;

                // Status và progress
                $allCompleted = $tasks->every(fn ($t) => $t->status === 'completed');
                $hasActive = $tasks->contains(fn ($t) => $t->status === 'in_progress' || $t->status === 'Đang thực hiện' || $t->status === 'active');
                $phaseStatus = $allCompleted ? 'completed' : ($hasActive ? 'active' : 'pending');
                $progress = $allCompleted ? 100 : ($hasActive ? 50 : 0); // tạm, refreshProgressFromPhases sẽ chính xác hơn

                $phase->update([
                    'weight' => $weight,
                    'start_date' => $phaseStart,
                    'end_date' => $phaseEnd,
                    'status' => $phaseStatus,
                    'progress' => $progress,
                ]);
            }

            // Refresh project progress
            $project->refreshProgressFromPhases();
        }

        $this->command->info('Refined phases from actual task data.');
    }
}
