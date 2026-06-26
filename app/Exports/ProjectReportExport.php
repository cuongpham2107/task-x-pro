<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectReportExport
{
    private const COLOR_HEADER_BG = '16A34A';

    private const COLOR_HEADER_FG = 'FFFFFF';

    private const STAT_COLORS = [
        'total' => ['bg' => '16A34A', 'fg' => 'FFFFFF', 'val' => '334155'],
        'running' => ['bg' => '3B82F6', 'fg' => 'FFFFFF', 'val' => '1D4ED8'],
        'overdue' => ['bg' => 'EF4444', 'fg' => 'FFFFFF', 'val' => '991B1B'],
        'slow' => ['bg' => 'F59E0B', 'fg' => 'FFFFFF', 'val' => '92400E'],
        'completed' => ['bg' => '22C55E', 'fg' => 'FFFFFF', 'val' => '166534'],
    ];

    private const COLOR_TABLE_HEADER_BG = '16A34A';

    private const COLOR_TABLE_HEADER_FG = 'FFFFFF';

    private const STATUS_STYLES = [
        'init' => ['bg' => 'E2E8F0', 'fg' => '475569'],
        'Khởi tạo' => ['bg' => 'E2E8F0', 'fg' => '475569'],
        'running' => ['bg' => 'BFDBFE', 'fg' => '1D4ED8'],
        'Đang chạy' => ['bg' => 'BFDBFE', 'fg' => '1D4ED8'],
        'paused' => ['bg' => 'FEF08A', 'fg' => 'A16207'],
        'Tạm dừng' => ['bg' => 'FEF08A', 'fg' => 'A16207'],
        'completed' => ['bg' => 'BBF7D0', 'fg' => '15803D'],
        'Hoàn thành' => ['bg' => 'BBF7D0', 'fg' => '15803D'],
        'overdue' => ['bg' => 'FECACA', 'fg' => 'B91C1C'],
        'Quá hạn' => ['bg' => 'FECACA', 'fg' => 'B91C1C'],
        'cancelled' => ['bg' => 'F1F5F9', 'fg' => '94A3B8'],
        'Đã hủy' => ['bg' => 'F1F5F9', 'fg' => '94A3B8'],
    ];

    public function __construct(
        protected array $projects,
        protected array $summary,
        protected string $fromDate,
        protected string $toDate,
        protected string $generatedBy,
    ) {}

    public function download(string $filename = 'bao-cao-tien-do.xlsx'): StreamedResponse
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

    // ── Xây dựng Spreadsheet ──────────────────────────────────
    private function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Báo cáo');

        // Font mặc định
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $row = 1;
        $row = $this->writeHeader($sheet, $row);
        $row = $this->writeStatCards($sheet, $row);
        $row = $this->writeProjectTable($sheet, $row);

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(36);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(16);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(16);

        return $spreadsheet;
    }

    // ── 1. Tiêu đề & meta ────────────────────────────────────
    private function writeHeader($sheet, int $row): int
    {
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", 'BÁO CÁO TIẾN ĐỘ DỰ ÁN');
        $sheet->getRowDimension($row)->setRowHeight(28);
        $this->applyStyle($sheet, "A{$row}:H{$row}", [
            'font' => ['bold' => true, 'size' => 20, 'color' => self::COLOR_HEADER_FG],
            'fill' => self::COLOR_HEADER_BG,
            'alignment' => 'center',
        ]);
        $row++;

        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->setCellValue("A{$row}", "Thời gian: Từ {$this->fromDate} đến {$this->toDate}");
        $this->applyStyle($sheet, "A{$row}:D{$row}", [
            'fill' => 'F0FDF4',
            'alignment' => 'left',
            'font' => ['size' => 9, 'italic' => true],
        ]);

        $sheet->mergeCells("E{$row}:H{$row}");
        $sheet->setCellValue("E{$row}", 'Ngày xuất báo cáo: '.now()->format('d/m/Y H:i'));
        $this->applyStyle($sheet, "E{$row}:H{$row}", [
            'fill' => 'F0FDF4',
            'alignment' => 'right',
            'font' => ['size' => 9, 'italic' => true],
        ]);

        $row++;
        $row++; // dòng trống

        return $row;
    }

    // ── 2. Stat cards (Section I) ─────────────────────────────
    private function writeStatCards($sheet, int $row): int
    {
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", 'I. THỐNG KÊ TỔNG QUAN');
        $sheet->getRowDimension($row)->setRowHeight(18);
        $this->applyStyle($sheet, "A{$row}:H{$row}", [
            'font' => ['bold' => true, 'size' => 12, 'color' => self::COLOR_HEADER_BG],
            'alignment' => 'left',
        ]);
        $row++;

        $total = $this->summary['total'] ?? 0;
        $running = $this->summary['running'] ?? 0;
        $overdue = $this->summary['overdue'] ?? 0;
        $slow = $this->summary['slow'] ?? 0;
        $completed = $this->summary['completed'] ?? 0;

        $cards = [
            ['label' => 'Tổng số dự án',          'value' => $total,     'key' => 'total'],
            ['label' => 'Số dự án đang chạy',     'value' => $running,   'key' => 'running'],
            ['label' => 'Số dự án đã trễ hạn',    'value' => $overdue,   'key' => 'overdue'],
            ['label' => 'Số dự án chậm trễ',      'value' => $slow,      'key' => 'slow'],
            ['label' => 'Số dự án hoàn thành',    'value' => $completed, 'key' => 'completed'],
        ];

        $colMerges = [
            ['A', 'B'],
            ['C', 'D'],
            ['E', 'F'],
            ['G', 'G'],
            ['H', 'H'],
        ];

        $sheet->getRowDimension($row)->setRowHeight(22);
        foreach ($cards as $i => $card) {
            [$c1, $c2] = $colMerges[$i];
            $range = $c1 === $c2 ? "{$c1}{$row}" : "{$c1}{$row}:{$c2}{$row}";
            if ($c1 !== $c2) {
                $sheet->mergeCells($range);
            }
            $sheet->setCellValue("{$c1}{$row}", $card['label']);
            $colors = self::STAT_COLORS[$card['key']];
            $this->applyStyle($sheet, $range, [
                'fill' => $colors['bg'],
                'font' => ['bold' => true, 'size' => 9, 'color' => $colors['fg']],
                'alignment' => 'center',
                'border' => true,
            ]);
        }
        $row++;

        $sheet->getRowDimension($row)->setRowHeight(36);
        foreach ($cards as $i => $card) {
            [$c1, $c2] = $colMerges[$i];
            $range = $c1 === $c2 ? "{$c1}{$row}" : "{$c1}{$row}:{$c2}{$row}";
            if ($c1 !== $c2) {
                $sheet->mergeCells($range);
            }
            $sheet->setCellValue("{$c1}{$row}", $card['value']);
            $colors = self::STAT_COLORS[$card['key']];
            $this->applyStyle($sheet, $range, [
                'fill' => 'FFFFFF',
                'font' => ['bold' => true, 'color' => $colors['val'], 'size' => 22],
                'alignment' => 'center',
                'border' => true,
            ]);
        }
        $row++;
        $row++;

        $totalPct = $total > 0;

        $subLabels = [
            'Dự án đang chạy trong khoảng thời gian được chọn',
            'Dự án đã quá hạn (quá thời gian kết thúc)',
            'Tiến độ < 60% & sắp đến hạn',
            'Dự án đã hoàn thành',
        ];
        $subValues = [
            $totalPct ? round(($running / $total) * 100).'%' : '0%',
            $totalPct ? round(($overdue / $total) * 100).'%' : '0%',
            $totalPct ? round(($slow / $total) * 100).'%' : '0%',
            $totalPct ? round(($completed / $total) * 100).'%' : '0%',
        ];

        $subCols = [
            ['C', 'D'],
            ['E', 'F'],
            ['G', 'G'],
            ['H', 'H'],
        ];

        $labelRow = $row;
        $sheet->mergeCells("A{$labelRow}:B".($labelRow + 1));
        $sheet->setCellValue("A{$labelRow}", 'Tổng số dự án trong khoảng thời gian được chọn');
        $this->applyStyle($sheet, "A{$labelRow}:B".($labelRow + 1), [
            'font' => ['size' => 9],
            'alignment' => 'left',
            'border' => true,
            'wrap' => true,
        ]);

        foreach ($subValues as $i => $val) {
            [$c1, $c2] = $subCols[$i];
            $range = $c1 === $c2 ? "{$c1}{$row}" : "{$c1}{$row}:{$c2}{$row}";
            if ($c1 !== $c2) {
                $sheet->mergeCells($range);
            }
            $sheet->setCellValue("{$c1}{$row}", $val);
            $this->applyStyle($sheet, $range, [
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => 'center',
                'border' => true,
            ]);
        }
        $row++;

        foreach ($subLabels as $i => $label) {
            [$c1, $c2] = $subCols[$i];
            $range = $c1 === $c2 ? "{$c1}{$row}" : "{$c1}{$row}:{$c2}{$row}";
            if ($c1 !== $c2) {
                $sheet->mergeCells($range);
            }
            $sheet->setCellValue("{$c1}{$row}", $label);
            $this->applyStyle($sheet, $range, [
                'font' => ['size' => 8, 'color' => '64748B'],
                'alignment' => 'center',
                'border' => true,
                'wrap' => true,
            ]);
        }
        $row++;
        $row++;

        return $row;
    }

    private function writeProjectTable($sheet, int $row): int
    {
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", 'II. DANH SÁCH CÁC DỰ ÁN');
        $sheet->getRowDimension($row)->setRowHeight(18);
        $this->applyStyle($sheet, "A{$row}:H{$row}", [
            'font' => ['bold' => true, 'size' => 12, 'color' => self::COLOR_HEADER_BG],
            'alignment' => 'left',
        ]);
        $row++;

        $headers = ['STT', 'Tên dự án', 'Leader', 'Thời gian bắt đầu', 'Thời gian kết thúc',
            'Tiến độ hiện tại', 'Tiến độ 7 ngày trước', 'Trạng thái'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

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

        if (empty($this->projects)) {
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->setCellValue("A{$row}", 'Không có dự án nào trong khoảng thời gian này.');
            $this->applyStyle($sheet, "A{$row}:H{$row}", ['alignment' => 'center', 'border' => true]);
            $row++;

            return $row;
        }

        foreach ($this->projects as $index => $project) {
            $sheet->getRowDimension($row)->setRowHeight(20);

            $statusKey = $project['status_key'] ?? $project['status_label'] ?? '';
            $statusLabel = $project['status_label'] ?? $statusKey;
            $progress7d = $project['progress_7d_ago'] !== null
                ? $project['progress_7d_ago'].'%'
                : '—';

            $data = [
                $index + 1,
                $project['name'] ?? '',
                $project['leader_names'] ?? '',
                $project['start_date'] ?? '',
                $project['end_date'] ?? '',
                ($project['progress'] ?? 0).'%',
                $progress7d,
                $statusLabel,
            ];

            $rowBg = $index % 2 === 0 ? 'FFFFFF' : 'F8FAFC';

            foreach ($data as $i => $value) {
                $cell = $cols[$i].$row;
                $sheet->setCellValue($cell, $value);

                $style = ['fill' => $rowBg, 'border' => true, 'alignment' => 'center'];

                if ($i === 1) {
                    $style['alignment'] = 'left';
                }

                if ($i === 7) {
                    $s = self::STATUS_STYLES[$statusKey]
                        ?? self::STATUS_STYLES[$statusLabel]
                        ?? ['bg' => 'E2E8F0', 'fg' => '475569'];
                    $style['fill'] = $s['bg'];
                    $style['font'] = ['bold' => true, 'color' => $s['fg']];
                }

                $this->applyStyle($sheet, $cell, $style);
            }
            $row++;
        }

        return $row;
    }

    // ── Helper: apply style ───────────────────────────────────
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
