<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class DashboardReportExport implements FromView, ShouldAutoSize
{
    public function __construct(
        protected array $data,
        protected string $title,
        protected string $periodLabel,
        protected string $generatedBy,
    ) {}

    public function view(): View
    {
        return view('exports.dashboard-report', [
            'data' => $this->data,
            'title' => $this->title,
            'periodLabel' => $this->periodLabel,
            'generatedBy' => $this->generatedBy,
        ]);
    }
}
