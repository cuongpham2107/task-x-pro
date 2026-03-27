<?php

use App\Models\Department;
use App\Models\KpiScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders kpi export template with vietnamese labels and formula for all report types', function (): void {
    $department = Department::factory()->create(['name' => 'Vận hành']);
    $head = User::factory()->leader()->create(['name' => 'Trưởng phòng KPI']);
    $pic = User::factory()->pic()->create([
        'name' => 'Nhân sự KPI',
        'department_id' => $department->id,
        'job_title' => 'Chuyên viên vận hành',
    ]);

    $score = KpiScore::factory()->create([
        'user_id' => $pic->id,
        'status' => 'approved',
        'approved_at' => now(),
        'period_type' => 'monthly',
        'period_year' => now()->year,
        'period_value' => now()->month,
    ]);
    $score->loadMissing('user');

    $ceoData = collect([
        (object) [
            'name' => $department->name,
            'head' => (object) ['name' => $head->name],
            'active_users_count' => 8,
            'avg_final_score' => 82.5,
            'avg_on_time_rate' => 88.4,
            'avg_sla_rate' => 86.3,
            'avg_star' => 4.2,
        ],
    ]);

    $meta = [
        'generated_at' => now()->format('d/m/Y H:i'),
        'generated_by' => 'Test User',
        'formula' => 'Điểm = (% đúng hạn x 0.4) + (% SLA đạt x 0.4) + (sao x 0.2)',
    ];

    $ceoHtml = view('exports.kpi', [
        'data' => $ceoData,
        'title' => 'Báo cáo KPI Toàn công ty',
        'periodLabel' => 'Tháng '.now()->month.'/'.now()->year,
        'exportType' => 'ceo',
        'meta' => $meta,
    ])->render();

    $leaderHtml = view('exports.kpi', [
        'data' => collect([$score]),
        'title' => 'Báo cáo KPI Phòng ban',
        'periodLabel' => 'Tháng '.now()->month.'/'.now()->year,
        'exportType' => 'leader',
        'meta' => $meta,
    ])->render();

    $picHtml = view('exports.kpi', [
        'data' => collect([$score]),
        'title' => 'Báo cáo KPI Cá nhân',
        'periodLabel' => 'Tháng '.now()->month.'/'.now()->year,
        'exportType' => 'pic',
        'meta' => $meta,
    ])->render();

    expect($ceoHtml)->toContain('Công thức BR-002')
        ->and($ceoHtml)->toContain('Phòng ban')
        ->and($ceoHtml)->not->toContain('<tr>a')
        ->and($ceoHtml)->not->toContain('bckground-color')
        ->and($leaderHtml)->toContain('Trạng thái / Duyệt')
        ->and($leaderHtml)->toContain('Chuyên viên vận hành')
        ->and($picHtml)->toContain('Điểm thực tế')
        ->and($picHtml)->toContain('Ngày duyệt');
});
