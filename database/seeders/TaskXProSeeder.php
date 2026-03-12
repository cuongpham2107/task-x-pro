<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\ApprovalLog;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\KpiScore;
use App\Models\Phase;
use App\Models\PhaseTemplate;
use App\Models\Project;
use App\Models\ProjectLeader;
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
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TaskXProSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $departments = $this->seedDepartments();

            [$ceo, $leaders, $pics] = $this->seedUsers($departments);
            $this->seedAuthorization($ceo, $leaders, $pics);

            $this->assignDepartmentHeads($departments, $ceo, $leaders);
            $this->seedPhaseTemplates();
            $this->seedSlaConfigs($departments, $ceo);

            $projects = $this->seedProjects($leaders, $ceo);

            $this->seedProjectLeaders($projects, $leaders, $ceo);
            $this->seedExecutionData($projects, $leaders, $pics, $ceo);
            $this->seedKpiScores($pics);
        });
    }

    /**
     * @return Collection<int, Department>
     */
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

    /**
     * @param  Collection<int, Department>  $departments
     * @return array{0: User, 1: Collection<int, User>, 2: Collection<int, User>}
     */
    private function seedUsers(Collection $departments): array
    {
        $headOfficeDepartmentId = $departments->firstWhere('code', 'BGD')?->id ?? $departments->first()?->id;
        $supportDepartments = $departments->where('code', '!=', 'BGD')->values();
        $defaultLeaderDepartmentId = $departments->firstWhere('code', 'IT')?->id
            ?? $supportDepartments->first()?->id
            ?? $headOfficeDepartmentId;
        $defaultPicDepartmentId = $departments->firstWhere('code', 'OPS')?->id
            ?? $supportDepartments->first()?->id
            ?? $headOfficeDepartmentId;

        // CEO
        $ceo = User::query()->updateOrCreate(
            ['email' => 'ceo@taskxpro.vn'],
            [
                'employee_code' => 'NV0001',
                'name' => 'Nguyen Duc Minh',
                'password' => Hash::make('password'),
                'phone' => '0909123001',
                'job_title' => 'Tong giam doc',
                'department_id' => $headOfficeDepartmentId,
                'status' => 'active',
                'telegram_id' => 'chat_ceo_0001',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        // Fixed Leader
        $fixedLeader = User::query()->updateOrCreate(
            ['email' => 'leader@taskxpro.vn'],
            [
                'employee_code' => 'NV0002',
                'name' => 'Tran Hoang Kiet',
                'password' => Hash::make('password'),
                'phone' => '0909123002',
                'job_title' => 'Truong nhom du an',
                'department_id' => $defaultLeaderDepartmentId,
                'status' => 'active',
                'telegram_id' => 'chat_leader_0001',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        // Generate Leaders
        $leaders = User::factory()->leader()->count(5)->create();
        $leaders->values()->each(function (User $leader, int $index) use ($supportDepartments): void {
            $department = $supportDepartments->get($index % max(1, $supportDepartments->count()));
            $leader->update([
                'employee_code' => 'LDR'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'department_id' => $department?->id,
            ]);
        });
        $leaders = $leaders
            ->push($fixedLeader)
            ->unique('id')
            ->values();

        // Fixed PIC
        $fixedPic = User::query()->updateOrCreate(
            ['email' => 'pic@taskxpro.vn'],
            [
                'employee_code' => 'NV0003',
                'name' => 'Pham Quoc Dat',
                'password' => Hash::make('password'),
                'phone' => '0909123003',
                'job_title' => 'Chuyen vien trien khai',
                'department_id' => $defaultPicDepartmentId,
                'status' => 'active',
                'telegram_id' => 'chat_pic_0001',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        // Generate PICs
        $pics = User::factory()->pic()->count(25)->create();
        $pics->values()->each(function (User $pic, int $index) use ($supportDepartments): void {
            $department = $supportDepartments->get($index % max(1, $supportDepartments->count()));
            $pic->update([
                'employee_code' => 'PIC'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'department_id' => $department?->id,
            ]);
        });
        $pics = $pics
            ->push($fixedPic)
            ->unique('id')
            ->values();

        // Admin account
        User::query()->updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'employee_code' => 'ADMIN001',
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'job_title' => 'System Administrator',
                'department_id' => $headOfficeDepartmentId,
                'status' => 'active',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        return [$ceo, $leaders->values(), $pics->values()];
    }

    /**
     * @param  Collection<int, User>  $leaders
     * @param  Collection<int, User>  $pics
     */
    private function seedAuthorization(User $ceo, Collection $leaders, Collection $pics): void
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

        $ceo->syncRoles(['ceo', 'super_admin']);
        $leaders->each(fn (User $leader) => $leader->syncRoles(['leader']));
        $pics->each(fn (User $pic) => $pic->syncRoles(['pic']));

        $admin = User::query()->where('email', 'admin@admin.com')->first();
        if ($admin) {
            $admin->syncRoles(['super_admin']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  Collection<int, Department>  $departments
     * @param  Collection<int, User>  $leaders
     */
    private function assignDepartmentHeads(Collection $departments, User $ceo, Collection $leaders): void
    {
        $departments->each(function (Department $department) use ($ceo, $leaders): void {
            $headUserId = $department->code === 'BGD'
                ? $ceo->id
                : $leaders->random()->id;

            $department->update([
                'head_user_id' => $headUserId,
            ]);
        });
    }

    private function seedPhaseTemplates(): void
    {
        $projectTypes = ['warehouse', 'customs', 'trucking', 'software', 'gms', 'tower'];

        $commonTemplates = [
            ['name' => 'Khoi tao', 'desc' => 'Thong nhat muc tieu va pham vi du an.', 'weight' => 15, 'days' => 10],
            ['name' => 'Phan tich', 'desc' => 'Thu thap yeu cau va xac dinh tieu chi nghiem thu.', 'weight' => 20, 'days' => 15],
            ['name' => 'Trien khai', 'desc' => 'Thuc thi cong viec theo ke hoach da duoc phe duyet.', 'weight' => 45, 'days' => 40],
            ['name' => 'Nghiem thu', 'desc' => 'Danh gia ket qua, hoan tat bien ban va ban giao.', 'weight' => 20, 'days' => 10],
        ];

        $softwareTemplates = [
            ['name' => 'Khoi tao & Planning', 'desc' => 'Kick-off du an, xac dinh scope va resource.', 'weight' => 10, 'days' => 7],
            ['name' => 'Requirement & Design', 'desc' => 'Phan tich nghiep vu, thiet ke UI/UX va DB.', 'weight' => 20, 'days' => 21],
            ['name' => 'Development', 'desc' => 'Lap trinh frontend, backend va API.', 'weight' => 40, 'days' => 60],
            ['name' => 'Testing & QC', 'desc' => 'Kiem thu he thong, fix bug.', 'weight' => 20, 'days' => 14],
            ['name' => 'Deployment & Handover', 'desc' => 'Deploy production, training va ban giao.', 'weight' => 10, 'days' => 7],
        ];

        $warehouseTemplates = [
            ['name' => 'Khao sat & Thiet ke', 'desc' => 'Khao sat mat bang, len layout kho.', 'weight' => 20, 'days' => 15],
            ['name' => 'Thi cong & Lap dat', 'desc' => 'Thi cong ke kho, he thong PCCC, camera.', 'weight' => 50, 'days' => 45],
            ['name' => 'Van hanh thu', 'desc' => 'Chay thu quy trinh nhap xuat.', 'weight' => 20, 'days' => 10],
            ['name' => 'Ban giao', 'desc' => 'Nghiem thu va ban giao mat bang.', 'weight' => 10, 'days' => 5],
        ];

        foreach ($projectTypes as $projectType) {
            $templates = match ($projectType) {
                'software' => $softwareTemplates,
                'warehouse' => $warehouseTemplates,
                default => $commonTemplates,
            };

            foreach ($templates as $index => $template) {
                PhaseTemplate::query()->updateOrCreate(
                    [
                        'project_type' => $projectType,
                        'phase_name' => $template['name'],
                        'order_index' => $index + 1,
                    ],
                    [
                        'phase_description' => $template['desc'],
                        'default_weight' => $template['weight'],
                        'default_duration_days' => $template['days'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * @param  Collection<int, Department>  $departments
     */
    private function seedSlaConfigs(Collection $departments, User $ceo): void
    {
        $effectiveDate = now()->startOfYear()->toDateString();

        foreach ($departments as $department) {
            // SLA for specific types
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

            // Generic SLA for department
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

        // Global fallback SLA
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

    /**
     * @param  Collection<int, User>  $leaders
     * @return Collection<int, Project>
     */
    private function seedProjects(Collection $leaders, User $ceo): Collection
    {
        $ownerPool = $leaders->concat([$ceo])->values();

        $projectBlueprints = [
            // Warehouse Projects
            [
                'name' => 'Du an toi uu kho mien Nam',
                'type' => 'warehouse',
                'status' => 'running',
                'budget' => 1200000000,
                'budget_spent' => 460000000,
                'start_offset' => -90,
                'end_offset' => 120,
                'objective' => 'Toi uu quy trinh xuat nhap kho va giam ton kho chet.',
            ],
            [
                'name' => 'Xay dung kho lanh Binh Duong',
                'type' => 'warehouse',
                'status' => 'init',
                'budget' => 5000000000,
                'budget_spent' => 0,
                'start_offset' => 10,
                'end_offset' => 200,
                'objective' => 'Mo rong suc chua hang dong lanh.',
            ],

            // Customs Projects
            [
                'name' => 'Du an tu dong hoa thong quan',
                'type' => 'customs',
                'status' => 'running',
                'budget' => 1850000000,
                'budget_spent' => 980000000,
                'start_offset' => -70,
                'end_offset' => 95,
                'objective' => 'Rut ngan thoi gian thong quan va giam sai sot nghiep vu.',
            ],

            // Trucking Projects
            [
                'name' => 'Du an dieu phoi xe container',
                'type' => 'trucking',
                'status' => 'paused',
                'budget' => 960000000,
                'budget_spent' => 420000000,
                'start_offset' => -120,
                'end_offset' => 80,
                'objective' => 'Toi uu dieu do xe va nang cao he so su dung dau keo.',
            ],

            // Software Projects
            [
                'name' => 'TaskXPro System Upgrade',
                'type' => 'software',
                'status' => 'running',
                'budget' => 2100000000,
                'budget_spent' => 1250000000,
                'start_offset' => -110,
                'end_offset' => 150,
                'objective' => 'Nang cap he thong noi bo theo huong single-page va realtime.',
            ],
            [
                'name' => 'Mobile App Driver',
                'type' => 'software',
                'status' => 'completed',
                'budget' => 850000000,
                'budget_spent' => 820000000,
                'start_offset' => -200,
                'end_offset' => -10,
                'objective' => 'App mobile cho tai xe cap nhat vi tri va nhan lenh.',
            ],

            // GMS & Tower
            [
                'name' => 'Chuan hoa giam sat GMS',
                'type' => 'gms',
                'status' => 'init',
                'budget' => 760000000,
                'budget_spent' => 50000000,
                'start_offset' => -15,
                'end_offset' => 210,
                'objective' => 'Chuan hoa KPI giam sat va dashboard van hanh tap trung.',
            ],
            [
                'name' => 'Quy trinh Tower Control',
                'type' => 'tower',
                'status' => 'cancelled',
                'budget' => 300000000,
                'budget_spent' => 50000000,
                'start_offset' => -300,
                'end_offset' => -100,
                'objective' => 'Thu nghiem quy trinh moi nhung khong hieu qua.',
            ],
        ];

        return collect($projectBlueprints)
            ->values()
            ->map(function (array $item, int $index) use ($ownerPool): Project {
                $owner = $ownerPool->get($index % max(1, $ownerPool->count()));

                return Project::query()->updateOrCreate(
                    ['name' => $item['name']],
                    [
                        'type' => $item['type'],
                        'status' => $item['status'],
                        'budget' => $item['budget'],
                        'budget_spent' => min($item['budget'], $item['budget_spent']),
                        'objective' => $item['objective'],
                        'start_date' => now()->addDays((int) $item['start_offset'])->toDateString(),
                        'end_date' => now()->addDays((int) $item['end_offset'])->toDateString(),
                        'progress' => 0, // Will be updated by phases
                        'created_by' => $owner?->id ?? $ownerPool->first()?->id,
                    ]
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, User>  $leaders
     */
    private function seedProjectLeaders(Collection $projects, Collection $leaders, User $ceo): void
    {
        if ($leaders->isEmpty()) {
            return;
        }

        foreach ($projects->values() as $projectIndex => $project) {
            $leaderCount = $leaders->count();
            // Assign 1-2 leaders per project
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
                        'assigned_at' => now()->subDays(30 - min($projectIndex, 20)),
                    ]
                );
            }
        }
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, User>  $leaders
     * @param  Collection<int, User>  $pics
     */
    private function seedExecutionData(Collection $projects, Collection $leaders, Collection $pics, User $ceo): void
    {
        foreach ($projects as $project) {
            $phaseTemplates = PhaseTemplate::query()
                ->where('project_type', $project->type)
                ->where('is_active', true)
                ->orderBy('order_index')
                ->get();

            // If no templates found, fallback to generic logic (should not happen due to seeder)
            if ($phaseTemplates->isEmpty()) {
                continue;
            }

            $projectStart = $project->start_date !== null
                ? Carbon::parse($project->start_date)->startOfDay()
                : now()->startOfDay();
            $phaseStartCursor = $projectStart->copy();

            $phaseTemplates->values()->each(function (PhaseTemplate $template, int $templateIndex) use ($project, $leaders, $pics, $ceo, &$phaseStartCursor): void {
                $phaseStart = $phaseStartCursor->copy();
                $durationDays = max(1, (int) ($template->default_duration_days ?? 14));
                $phaseEnd = $phaseStart->copy()->addDays($durationDays - 1);
                $phaseStartCursor = $phaseEnd->copy()->addDay();

                // Determine phase status based on project timeline vs now
                $projectStatus = $project->status instanceof \BackedEnum ? $project->status->value : $project->status;

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

                $phase = Phase::query()->updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'order_index' => $template->order_index,
                    ],
                    [
                        'name' => $template->phase_name,
                        'description' => $template->phase_description,
                        'weight' => $template->default_weight,
                        'start_date' => $phaseStart->toDateString(),
                        'end_date' => $phaseEnd->toDateString(),
                        'progress' => 0,
                        'status' => $phaseStatus,
                        'is_template' => false,
                    ]
                );

                $this->seedTasksForPhase($project, $phase, $templateIndex, $leaders, $pics, $ceo);
            });

            $project->refreshProgressFromPhases();
            $project->refresh();

            // Initial Activity Log
            ActivityLog::query()->updateOrCreate(
                [
                    'entity_type' => Project::class,
                    'entity_id' => $project->id,
                    'action' => 'seeded_progress_snapshot',
                ],
                [
                    'user_id' => $project->created_by,
                    'old_values' => null,
                    'new_values' => [
                        'status' => $project->status,
                        'progress' => $project->progress,
                    ],
                ]
            );
        }
    }

    /**
     * @param  Collection<int, User>  $leaders
     * @param  Collection<int, User>  $pics
     */
    private function seedTasksForPhase(Project $project, Phase $phase, int $templateIndex, Collection $leaders, Collection $pics, User $ceo): void
    {
        if ($pics->isEmpty()) {
            return;
        }

        $projectLeaderIds = ProjectLeader::query()
            ->where('project_id', $project->id)
            ->pluck('user_id');

        $creatorPool = ($projectLeaderIds->isEmpty() ? $leaders->pluck('id') : $projectLeaderIds)
            ->filter()
            ->values();
        if ($creatorPool->isEmpty()) {
            $creatorPool = collect([$ceo->id]);
        }

        // Determine number of tasks for this phase
        $taskCount = match ($project->type) {
            'software' => 6,
            'warehouse' => 4,
            default => 3,
        };

        $createdTasks = collect();

        for ($i = 0; $i < $taskCount; $i++) {
            $pic = $pics->get(($templateIndex + $i) % $pics->count()) ?? $pics->first();
            if (! $pic instanceof User) {
                continue;
            }

            // Task Properties
            $taskName = 'Task #'.($i + 1).' - Phase '.($templateIndex + 1);
            $taskType = match ($i % 5) {
                0 => 'operation',
                1 => 'technical',
                2 => 'report',
                3 => 'admin',
                default => 'other',
            };
            $priority = match ($i % 4) {
                0 => 'high',
                1 => 'medium',
                2 => 'low',
                default => 'urgent',
            };
            $workflowType = ($i % 3 === 0) ? 'double' : 'single'; // Some double approval tasks

            // Dates logic
            $phaseStart = Carbon::parse($phase->start_date);
            $phaseEnd = Carbon::parse($phase->end_date);
            $phaseDuration = $phaseStart->diffInDays($phaseEnd);
            $taskDuration = max(2, (int) ($phaseDuration / $taskCount));

            $deadline = $phaseStart->copy()->addDays(($i + 1) * $taskDuration);
            if ($deadline->gt($phaseEnd)) {
                $deadline = $phaseEnd->copy();
            }

            // Status Logic
            $status = 'pending';
            $progress = 0;
            $startedAt = null;
            $completedAt = null;

            if ($phase->status === 'completed') {
                $status = 'completed';
                $progress = 100;
                $startedAt = $deadline->copy()->subDays(rand(2, 5));
                $completedAt = $startedAt->copy()->addDays(rand(1, 4));
            } elseif ($phase->status === 'active') {
                if (now()->gt($deadline)) {
                    $status = 'late';
                    $progress = rand(50, 90);
                    $startedAt = $deadline->copy()->subDays(rand(2, 5));
                } elseif (now()->gt($phaseStart->copy()->addDays($i * $taskDuration))) {
                    // Task should have started
                    $status = rand(0, 1) ? 'in_progress' : 'waiting_approval';
                    $progress = $status === 'in_progress' ? rand(20, 80) : 100;
                    $startedAt = now()->subDays(rand(1, 5));
                }
            }

            // Fix completed_at for late/completed tasks
            if ($status === 'completed' && $completedAt === null) {
                $completedAt = $startedAt ? $startedAt->copy()->addDays(2) : now();
            }

            $creatorId = (int) ($creatorPool->get($i % $creatorPool->count()) ?? $ceo->id);

            $task = Task::query()->updateOrCreate(
                [
                    'phase_id' => $phase->id,
                    'name' => $taskName,
                ],
                [
                    'description' => 'Du lieu seed tu dong cho quy trinh task.',
                    'type' => $taskType,
                    'status' => $status,
                    'priority' => $priority,
                    'progress' => $progress,
                    'pic_id' => $pic->id,
                    'deadline' => $deadline,
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'workflow_type' => $workflowType,
                    'sla_standard_hours' => rand(12, 48), // Simulated snapshot
                    'created_by' => $creatorId,
                ]
            );

            $createdTasks->push($task);
        }

        // Set Dependencies (Task N depends on Task N-1)
        foreach ($createdTasks as $index => $task) {
            if ($index > 0) {
                $prevTask = $createdTasks[$index - 1];
                $task->update(['dependency_task_id' => $prevTask->id]);
            }
        }

        // Seed support data (comments, attachments, logs)
        foreach ($createdTasks as $index => $task) {
            $this->seedTaskSupportData($project, $task, $pics, $ceo, $creatorPool, $index);
        }

        $phase->refreshProgressFromTasks();
    }

    /**
     * @param  Collection<int, User>  $pics
     * @param  Collection<int, int>  $creatorPool
     */
    private function seedTaskSupportData(Project $project, Task $task, Collection $pics, User $ceo, Collection $creatorPool, int $taskIndex): void
    {
        $taskStatus = $task->status instanceof \BackedEnum
            ? (string) $task->status->value
            : (string) $task->status;
        $workflowType = $task->workflow_type instanceof \BackedEnum
            ? (string) $task->workflow_type->value
            : (string) $task->workflow_type;

        // Co-PICs
        $candidateCoPics = $pics->whereNotIn('id', [$task->pic_id])->values();
        if ($candidateCoPics->isNotEmpty() && rand(0, 1)) {
            $coPic = $candidateCoPics->random();
            TaskCoPic::query()->firstOrCreate(
                ['task_id' => $task->id, 'user_id' => $coPic->id],
                ['assigned_at' => now()->subDays(2)]
            );
        }

        $reviewerId = (int) ($creatorPool->get($taskIndex % $creatorPool->count()) ?? $ceo->id);

        // Comments
        if ($taskStatus !== 'pending') {
            TaskComment::query()->firstOrCreate([
                'task_id' => $task->id,
                'user_id' => $task->pic_id,
                'content' => 'Cap nhat tien do cong viec.',
            ]);
        }

        // Attachments & Documents
        if (in_array($taskStatus, ['waiting_approval', 'completed'], true)) {
            // Task Attachment (Media Library)
            $attachment = TaskAttachment::query()->updateOrCreate(
                ['task_id' => $task->id, 'version' => 1],
                [
                    'uploader_id' => $task->pic_id,
                    'original_name' => 'report_v1.pdf',
                    'stored_path' => '',
                    'disk' => config('media-library.disk_name', 'public'),
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 2048,
                ]
            );
            $this->syncTaskAttachmentMedia($attachment);

            // Document
            $document = Document::query()->updateOrCreate(
                ['task_id' => $task->id, 'name' => 'Giao pham Task #'.$task->id],
                [
                    'project_id' => $project->id,
                    'uploader_id' => $task->pic_id,
                    'document_type' => 'deliverable',
                    'current_version' => 1,
                    'permission' => 'edit',
                ]
            );

            $docVersion = DocumentVersion::query()->updateOrCreate(
                ['document_id' => $document->id, 'version_number' => 1],
                [
                    'uploader_id' => $task->pic_id,
                    'stored_path' => 'docs/v1.pdf',
                    'change_summary' => 'Initial version',
                    'file_size_bytes' => 5000,
                ]
            );
            $this->syncDocumentVersionMedia($docVersion);
        }

        // Approval Logs
        ApprovalLog::query()->where('task_id', $task->id)->delete();
        if (in_array($taskStatus, ['waiting_approval', 'completed'], true)) {
            ApprovalLog::query()->create([
                'task_id' => $task->id,
                'reviewer_id' => $reviewerId,
                'approval_level' => 'leader',
                'action' => $taskStatus === 'completed' ? 'approved' : 'submitted',
                'star_rating' => $taskStatus === 'completed' ? rand(4, 5) : null,
                'comment' => 'Reviewer comment.',
                'created_at' => now()->subHours(5),
            ]);

            if ($taskStatus === 'completed' && $workflowType === 'double') {
                ApprovalLog::query()->create([
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
    }

    private function syncTaskAttachmentMedia(TaskAttachment $taskAttachment): void
    {
        // Simple mock file creation to satisfy Media Library if needed
        // In real seed, we might skip actual file generation to save time,
        // or just create a dummy file.
        $extension = 'txt';
        $temporaryFilePath = $this->createTemporarySeedFile(
            'task-attachment-'.$taskAttachment->id,
            'Dummy content',
            $extension
        );

        try {
            if ($taskAttachment->getMedia('attachment')->isEmpty()) {
                $taskAttachment
                    ->addMedia($temporaryFilePath)
                    ->usingFileName($taskAttachment->original_name)
                    ->toMediaCollection('attachment', $taskAttachment->disk);
            }
        } catch (\Exception $e) {
            // Ignore media errors during seed
        }

        @unlink($temporaryFilePath);
    }

    private function syncDocumentVersionMedia(DocumentVersion $documentVersion): void
    {
        $extension = 'txt';
        $temporaryFilePath = $this->createTemporarySeedFile(
            'doc-version-'.$documentVersion->id,
            'Dummy doc content',
            $extension
        );

        try {
            if ($documentVersion->getMedia('version_file')->isEmpty()) {
                $documentVersion
                    ->addMedia($temporaryFilePath)
                    ->usingFileName('doc.txt')
                    ->toMediaCollection('version_file', config('media-library.disk_name', 'public'));
            }
        } catch (\Exception $e) {
            // Ignore
        }

        @unlink($temporaryFilePath);
    }

    private function createTemporarySeedFile(string $prefix, string $content, string $extension): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), $prefix);
        $targetPath = $temporaryPath.'.'.$extension;
        rename($temporaryPath, $targetPath);
        file_put_contents($targetPath, $content);

        return $targetPath;
    }

    /**
     * @param  Collection<int, User>  $pics
     */
    private function seedKpiScores(Collection $pics): void
    {
        foreach ($pics as $pic) {
            KpiScore::syncForUser((int) $pic->id);
        }
    }
}
