<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Exports\KpiExport;
use Maatwebsite\Excel\Facades\Excel;

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

$pdf = Excel::raw(new KpiExport($data, 'Báo cáo KPI Toàn công ty', 'Tháng 5/2026', 'ceo', ['generated_at' => now()->format('d/m/Y H:i'), 'generated_by' => 'Nguyen Duc Minh']), \Maatwebsite\Excel\Excel::DOMPDF);
file_put_contents(storage_path('app/kpi_test.pdf'), $pdf);
echo storage_path('app/kpi_test.pdf').PHP_EOL;
