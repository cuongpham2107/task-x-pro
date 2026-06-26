<?php

namespace App\Exports;

use App\Models\Project;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDetailExport
{
    private const COLOR_HEADER_BG = '16A34A';

    private const COLOR_HEADER_FG = 'FFFFFF';

    private const COLOR_TABLE_HEADER_BG = '16A34A';

    private const COLOR_TABLE_HEADER_FG = 'FFFFFF';

    private const STAT_COLORS = [
        'total_tasks' => ['bg' => '16A34A', 'fg' => 'FFFFFF', 'val' => '334155'],
        'in_progress' => ['bg' => '3B82F6', 'fg' => 'FFFFFF', 'val' => '1D4ED8'],
        'late' => ['bg' => 'EF4444', 'fg' => 'FFFFFF', 'val' => '991B1B'],
        'completed' => ['bg' => '22C55E', 'fg' => 'FFFFFF', 'val' => '166534'],
        'pending' => ['bg' => '94A3B8', 'fg' => 'FFFFFF', 'val' => '64748B'],
        'progress' => ['bg' => 'F59E0B', 'fg' => 'FFFFFF', 'val' => '92400E'],
    ];

    private const STATUS_STYLES = [
        'pending' => ['bg' => 'E2E8F0', 'fg' => '475569'],
        'Chưa bắt đầu' => ['bg' => 'E2E8F0', 'fg' => '475569'],
        'in_progress' => ['bg' => 'BFDBFE', 'fg' => '1D4ED8'],
        'Đang thực hiện' => ['bg' => 'BFDBFE', 'fg' => '1D4ED8'],
        'waiting_approval' => ['bg' => 'FEF08A', 'fg' => 'A16207'],
        'Chờ duyệt' => ['bg' => 'FEF08A', 'fg' => 'A16207'],
        'completed' => ['bg' => 'BBF7D0', 'fg' => '15803D'],
        'Hoàn thành' => ['bg' => 'BBF7D0', 'fg' => '15803D'],
        'late' => ['bg' => 'FECACA', 'fg' => 'B91C1C'],
        'Trễ hạn' => ['bg' => 'FECACA', 'fg' => 'B91C1C'],
        'cancelled' => ['bg' => 'F1F5F9', 'fg' => '94A3B8'],
        'Đã hủy' => ['bg' => 'F1F5F9', 'fg' => '94A3B8'],
    ];

    private const PRIORITY_COLORS = [
        'cao' => 'DC2626',
        'high' => 'DC2626',
        'trung bình' => 'D97706',
        'medium' => 'D97706',
        'thấp' => '7C3AED',
        'low' => '7C3AED',
    ];

    public function __construct(
        protected Project $project,
        protected array $phases,
        protected array $summary,
        protected string $generatedBy,
    ) {}

    public function download(string $filename = 'bao-cao-du-an.xlsx'): StreamedResponse
    {
        $spreadsheet = $this->build();

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Chi tiết dự án');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $row = 1;
        $row = $this->writeHeader($sheet, $row);
        $row = $this->writeStatCards($sheet, $row);
        $row = $this->writeTaskTable($sheet, $row);

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(32);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(14);
        $sheet->getColumnDimension('G')->setWidth(16);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(16);
        $sheet->getColumnDimension('J')->setWidth(14);

        return $spreadsheet;
    }

    private function writeHeader($sheet, int $row): int
    {
        $sheet->mergeCells("A{$row}:J{$row}");
        $sheet->setCellValue("A{$row}", 'BÁO CÁO TIẾN ĐỘ DỰ ÁN '.mb_strtoupper($this->project->name));
        $sheet->getRowDimension($row)->setRowHeight(28);
        $this->applyStyle($sheet, "A{$row}:J{$row}", [
            'font' => ['bold' => true, 'size' => 20, 'color' => self::COLOR_HEADER_FG],
            'fill' => self::COLOR_HEADER_BG,
            'alignment' => 'center',
        ]);
        $row++;

        $period = 'Thời gian: Từ '.($this->project->start_date?->format('d/m/Y') ?? '—')
            .' đến '.($this->project->end_date?->format('d/m/Y') ?? '—');

        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", $period);
        $this->applyStyle($sheet, "A{$row}:E{$row}", [
            'fill' => 'F0FDF4',
            'alignment' => 'left',
            'font' => ['size' => 9, 'italic' => true],
        ]);

        $sheet->mergeCells("F{$row}:J{$row}");
        $sheet->setCellValue("F{$row}", 'Ngày xuất báo cáo: '.now()->format('d/m/Y H:i'));
        $this->applyStyle($sheet, "F{$row}:J{$row}", [
            'fill' => 'F0FDF4',
            'alignment' => 'right',
            'font' => ['size' => 9, 'italic' => true],
        ]);

        $row++;
        $row++;

        return $row;
    }

    private function writeStatCards($sheet, int $row): int
    {
        $sheet->mergeCells("A{$row}:J{$row}");
        $sheet->setCellValue("A{$row}", 'I. THỐNG KÊ TỔNG QUAN');
        $sheet->getRowDimension($row)->setRowHeight(18);
        $this->applyStyle($sheet, "A{$row}:J{$row}", [
            'font' => ['bold' => true, 'size' => 12, 'color' => '16A34A'],
            'alignment' => 'left',
        ]);
        $row++;

        $total = $this->summary['total_tasks'] ?? 0;
        $inProgress = $this->summary['in_progress_tasks'] ?? 0;
        $late = $this->summary['late_tasks'] ?? 0;
        $completed = $this->summary['completed_tasks'] ?? 0;
        $pending = $this->summary['pending_tasks'] ?? 0;
        $progress = ($this->summary['progress'] ?? 0).'%';

        $labels = [
            'Tổng số task', 'Task đang thực hiện', 'Task trễ hạn',
            'Task hoàn thành', 'Task chưa làm', 'Tiến độ tổng thể',
        ];
        $values = [$total, $inProgress, $late, $completed, $pending, $progress];
        $statKeys = ['total_tasks', 'in_progress', 'late', 'completed', 'pending', 'progress'];
        $colMerges = [
            ['A', 'B'], ['C', 'D'], ['E', 'E'], ['F', 'F'], ['G', 'G'], ['H', 'J'],
        ];

        $sheet->getRowDimension($row)->setRowHeight(22);
        foreach ($labels as $i => $label) {
            [$c1, $c2] = $colMerges[$i];
            $range = $c1 === $c2 ? "{$c1}{$row}" : "{$c1}{$row}:{$c2}{$row}";
            if ($c1 !== $c2) {
                $sheet->mergeCells($range);
            }
            $sheet->setCellValue("{$c1}{$row}", $label);
            $colors = self::STAT_COLORS[$statKeys[$i]];
            $this->applyStyle($sheet, $range, [
                'fill' => $colors['bg'],
                'font' => ['bold' => true, 'size' => 9, 'color' => $colors['fg']],
                'alignment' => 'center',
                'border' => true,
            ]);
        }
        $row++;

        $sheet->getRowDimension($row)->setRowHeight(36);
        foreach ($values as $i => $val) {
            [$c1, $c2] = $colMerges[$i];
            $range = $c1 === $c2 ? "{$c1}{$row}" : "{$c1}{$row}:{$c2}{$row}";
            if ($c1 !== $c2) {
                $sheet->mergeCells($range);
            }
            $sheet->setCellValue("{$c1}{$row}", $val);
            $colors = self::STAT_COLORS[$statKeys[$i]];
            $this->applyStyle($sheet, $range, [
                'fill' => 'FFFFFF',
                'font' => ['bold' => true, 'color' => $colors['val'], 'size' => 20],
                'alignment' => 'center',
                'border' => true,
            ]);
        }
        $row++;
        $row++;

        $leaderNames = $this->project->leaders->pluck('name')->implode(', ');
        $statusLabel = $this->project->status instanceof \App\Enums\ProjectStatus
            ? $this->project->status->label()
            : $this->project->status;

        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("A{$row}", 'Leader: '.$leaderNames);
        $this->applyStyle($sheet, "A{$row}:B{$row}", [
            'font' => ['size' => 9],
            'alignment' => 'left',
            'border' => true,
        ]);

        $sheet->mergeCells("C{$row}:J{$row}");
        $sheet->setCellValue("C{$row}", 'Trạng thái: '.$statusLabel);
        $this->applyStyle($sheet, "C{$row}:J{$row}", [
            'font' => ['size' => 9],
            'alignment' => 'left',
            'border' => true,
        ]);

        $row++;
        $row++;

        return $row;
    }

    private function writeTaskTable($sheet, int $row): int
    {
        $sheet->mergeCells("A{$row}:J{$row}");
        $sheet->setCellValue("A{$row}", 'II. DANH SÁCH GIAI ĐOẠN & TASK');
        $sheet->getRowDimension($row)->setRowHeight(18);
        $this->applyStyle($sheet, "A{$row}:J{$row}", [
            'font' => ['bold' => true, 'size' => 12, 'color' => '16A34A'],
            'alignment' => 'left',
        ]);
        $row++;

        $headers = ['STT', 'Giai đoạn', 'Tên Task', 'Leader', 'PIC',
            'Độ ưu tiên', 'Tiến độ hiện tại', 'Tiến độ 7 ngày trước', 'Trạng thái', 'Deadline'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

        $sheet->getRowDimension($row)->setRowHeight(22);
        foreach ($headers as $i => $h) {
            $cell = $cols[$i].$row;
            $sheet->setCellValue($cell, $h);
            $this->applyStyle($sheet, $cell, [
                'fill' => self::COLOR_TABLE_HEADER_BG,
                'font' => ['bold' => true, 'color' => self::COLOR_TABLE_HEADER_FG, 'size' => 10],
                'alignment' => 'center',
                'border' => true,
                'wrap' => true,
            ]);
        }
        $row++;

        if (empty($this->phases)) {
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->setCellValue("A{$row}", 'Dự án chưa có giai đoạn nào.');
            $this->applyStyle($sheet, "A{$row}:J{$row}", ['alignment' => 'center', 'border' => true]);
            $row++;

            return $row;
        }

        $index = 0;
        foreach ($this->phases as $phase) {
            $tasks = $phase['tasks'] ?? [];

            if (empty($tasks)) {
                $sheet->getRowDimension($row)->setRowHeight(20);
                $sheet->setCellValue("A{$row}", ++$index);
                $sheet->setCellValue("B{$row}", $phase['name']);
                $sheet->mergeCells("C{$row}:J{$row}");
                $sheet->setCellValue("C{$row}", 'Chưa có task.');
                $rowBg = $index % 2 === 0 ? 'FFFFFF' : 'F8FAFC';
                foreach ($cols as $i => $c) {
                    $this->applyStyle($sheet, $c.$row, [
                        'fill' => $rowBg, 'border' => true, 'alignment' => 'center',
                    ]);
                }
                $row++;

                continue;
            }

            foreach ($tasks as $task) {
                $sheet->getRowDimension($row)->setRowHeight(20);
                $index++;
                $statusKey = $task['status_label'] ?? '';
                $priority = strtolower($task['priority_label'] ?? '');
                $progress7d = $task['progress_7d_ago'] !== null
                    ? $task['progress_7d_ago'].'%'
                    : '—';

                $data = [
                    $index,
                    $phase['name'],
                    $task['name'],
                    $task['leader_name'] ?? '—',
                    $task['pic_name'] ?? '—',
                    $task['priority_label'] ?? '—',
                    ($task['progress'] ?? 0).'%',
                    $progress7d,
                    $task['status_label'] ?? '—',
                    $task['deadline'] ?? '—',
                ];

                $rowBg = $index % 2 === 0 ? 'FFFFFF' : 'F8FAFC';

                foreach ($data as $i => $value) {
                    $cell = $cols[$i].$row;
                    $sheet->setCellValue($cell, $value);

                    $style = ['fill' => $rowBg, 'border' => true, 'alignment' => 'center'];

                    if ($i === 2) {
                        $style['alignment'] = 'left';
                    }

                    if ($i === 5) {
                        $priColor = self::PRIORITY_COLORS[$priority] ?? '0F172A';
                        $style['font'] = ['bold' => true, 'color' => $priColor];
                    }

                    if ($i === 8) {
                        $s = self::STATUS_STYLES[$statusKey]
                            ?? self::STATUS_STYLES[$task['status_label'] ?? '']
                            ?? ['bg' => 'E2E8F0', 'fg' => '475569'];
                        $style['fill'] = $s['bg'];
                        $style['font'] = ['bold' => true, 'color' => $s['fg']];
                    }

                    $this->applyStyle($sheet, $cell, $style);
                }
                $row++;
            }
        }

        return $row;
    }

    private function applyStyle($sheet, string $range, array $opts): void
    {
        $styleArr = [];

        if (isset($opts['fill'])) {
            $styleArr['fill'] = [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $opts['fill']],
            ];
        }

        if (isset($opts['font'])) {
            $f = $opts['font'];
            $styleArr['font'] = array_filter([
                'bold' => $f['bold'] ?? null,
                'size' => $f['size'] ?? null,
                'italic' => $f['italic'] ?? null,
                'color' => isset($f['color']) ? ['rgb' => $f['color']] : null,
                'name' => 'Arial',
            ]);
        } else {
            $styleArr['font'] = ['name' => 'Arial'];
        }

        if (isset($opts['alignment'])) {
            $styleArr['alignment'] = [
                'horizontal' => match ($opts['alignment']) {
                    'center' => Alignment::HORIZONTAL_CENTER,
                    'right' => Alignment::HORIZONTAL_RIGHT,
                    default => Alignment::HORIZONTAL_LEFT,
                },
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => $opts['wrap'] ?? false,
            ];
        }

        if (! empty($opts['border'])) {
            $borderStyle = [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CBD5E1'],
            ];
            $styleArr['borders'] = [
                'allBorders' => $borderStyle,
            ];
        }

        $sheet->getStyle($range)->applyFromArray($styleArr);
    }
}
