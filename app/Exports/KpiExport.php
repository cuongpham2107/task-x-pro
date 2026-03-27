<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KpiExport implements FromView, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected mixed $data,
        protected string $title,
        protected string $periodLabel,
        protected string $exportType = 'ceo', // ceo, leader, pic
        protected array $meta = []
    ) {}

    public function view(): View
    {
        return view('exports.kpi', [
            'data' => $this->data,
            'title' => $this->title,
            'periodLabel' => $this->periodLabel,
            'exportType' => $this->exportType,
            'meta' => $this->meta,
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['italic' => true, 'size' => 11]],
            3 => ['font' => ['size' => 10, 'color' => ['rgb' => '334155']]],
            5 => ['font' => ['bold' => true]],
        ];
    }
}
