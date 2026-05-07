<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Pdf\Facades\Pdf;

$data = collect([
    (object) [
        'name' => 'Ban Giam Đốc',
        'head' => (object) ['name' => 'Nguyễn Đức Minh'],
        'active_users_count' => 4,
        'avg_final_score' => 45.5,
        'avg_on_time_rate' => 50,
        'avg_sla_rate' => 37.5,
        'avg_star' => 2.5,
    ],
]);

$html = view('exports.kpi', [
    'data' => $data,
    'title' => 'Báo cáo KPI Toàn công ty',
    'periodLabel' => 'Tháng 5/2026',
    'exportType' => 'ceo',
    'meta' => ['generated_at' => now()->format('d/m/Y H:i'), 'generated_by' => 'Nguyen Duc Minh'],
])->render();

$pdf = Pdf::loadHtml($html)->format('a4')->output();
file_put_contents(storage_path('app/kpi_test.pdf'), $pdf);
echo storage_path('app/kpi_test.pdf').PHP_EOL;
