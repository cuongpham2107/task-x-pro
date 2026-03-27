@php
    $generatedAt = $meta['generated_at'] ?? now()->format('d/m/Y H:i');
    $generatedBy = $meta['generated_by'] ?? 'Hệ thống';
    $formula = $meta['formula'] ?? 'Điểm = (% đúng hạn x 0.4) + (% SLA đạt x 0.4) + (sao x 0.2)';

    $columnCount = match ($exportType) {
        'ceo' => 9,
        'leader' => 10,
        default => 10,
    };
@endphp

<meta charset="UTF-8">

<style>
    table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'DejaVu Sans', Arial, sans-serif;
        font-size: 11px;
        color: #0f172a;
    }

    th,
    td {
        border: 1px solid #cbd5e1;
        padding: 6px 8px;
        vertical-align: middle;
    }

    .report-title {
        font-size: 16px;
        font-weight: 700;
        text-align: center;
        background: #e2e8f0;
    }

    .report-meta {
        font-size: 10px;
        color: #334155;
        text-align: left;
        background: #f8fafc;
    }

    .head {
        background: #e2e8f0;
        font-weight: 700;
        text-align: center;
    }

    .right {
        text-align: right;
    }

    .center {
        text-align: center;
    }
</style>

<table>
    <thead>
        <tr>
            <th colspan="{{ $columnCount }}" class="report-title">{{ $title }}</th>
        </tr>
        <tr>
            <th colspan="{{ $columnCount }}" class="report-meta">Kỳ báo cáo: {{ $periodLabel }}</th>
        </tr>
        <tr>
            <th colspan="{{ $columnCount }}" class="report-meta">Thời gian xuất: {{ $generatedAt }} | Người xuất:
                {{ $generatedBy }}</th>
        </tr>
        <tr>
            <th colspan="{{ $columnCount }}" class="report-meta">Công thức BR-002: {{ $formula }}</th>
        </tr>
        <tr></tr>

        @if ($exportType === 'ceo')
            <tr>
                <th class="head">Phòng ban</th>
                <th class="head">Trưởng bộ phận</th>
                <th class="head center">Nhân sự</th>
                <th class="head center">Avg Final Score</th>
                <th class="head center">% Đúng hạn</th>
                <th class="head center">% SLA đạt</th>
                <th class="head center">Avg sao</th>
                <th class="head center">Trạng thái</th>
                <th class="head">Ghi chú</th>
            </tr>
        @elseif($exportType === 'leader')
            <tr>
                <th class="head">Nhân viên</th>
                <th class="head">Chức danh</th>
                <th class="head center">Tổng task</th>
                <th class="head center">Đúng hạn</th>
                <th class="head center">% Đúng hạn</th>
                <th class="head center">SLA đạt</th>
                <th class="head center">% SLA đạt</th>
                <th class="head center">Avg sao</th>
                <th class="head right">Final Score</th>
                <th class="head center">Trạng thái / Duyệt</th>
            </tr>
        @else
            <tr>
                <th class="head">Kỳ</th>
                <th class="head center">Tổng task</th>
                <th class="head center">% Đúng hạn</th>
                <th class="head center">% SLA đạt</th>
                <th class="head center">Avg sao</th>
                <th class="head right">Final Score</th>
                <th class="head center">Điểm mục tiêu</th>
                <th class="head center">Điểm thực tế</th>
                <th class="head center">Trạng thái</th>
                <th class="head center">Ngày duyệt</th>
            </tr>
        @endif
    </thead>

    <tbody>
        @if ($exportType === 'ceo')
            @forelse ($data as $department)
                @php
                    $avgFinal = (float) ($department->avg_final_score ?? 0);
                    $avgOnTime = (float) ($department->avg_on_time_rate ?? 0);
                    $avgSla = (float) ($department->avg_sla_rate ?? 0);
                    $avgStar = (float) ($department->avg_star ?? 0);

                    $status = $avgFinal >= 85 ? 'Ổn định tốt' : ($avgFinal >= 70 ? 'Cần theo dõi' : 'Cần cải thiện');
                    $note = $avgFinal >= 85 ? 'Duy trì hiệu suất' : ($avgFinal >= 70 ? 'Ưu tiên cải thiện SLA/Deadline' : 'Cần kế hoạch nâng hiệu suất');
                @endphp
                <tr>
                    <td>{{ $department->name }}</td>
                    <td>{{ $department->head?->name ?? '—' }}</td>
                    <td class="center">{{ $department->active_users_count }}</td>
                    <td class="center">{{ number_format($avgFinal, 1) }}</td>
                    <td class="center">{{ number_format($avgOnTime, 1) }}%</td>
                    <td class="center">{{ number_format($avgSla, 1) }}%</td>
                    <td class="center">{{ number_format($avgStar, 1) }}</td>
                    <td class="center">{{ $status }}</td>
                    <td>{{ $note }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columnCount }}" class="center">Không có dữ liệu KPI cho bộ lọc đã chọn.</td>
                </tr>
            @endforelse
        @elseif($exportType === 'leader')
            @forelse ($data as $score)
                @php
                    $statusLabel = match ($score->status) {
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Từ chối',
                        'locked' => 'Đã chốt',
                        default => 'Chờ duyệt',
                    };
                @endphp
                <tr>
                    <td>{{ $score->user?->name ?? '—' }}</td>
                    <td>{{ $score->user?->job_title ?? '—' }}</td>
                    <td class="center">{{ $score->total_tasks }}</td>
                    <td class="center">{{ $score->on_time_tasks }}</td>
                    <td class="center">{{ number_format((float) $score->on_time_rate, 1) }}%</td>
                    <td class="center">{{ $score->sla_met_tasks }}</td>
                    <td class="center">{{ number_format((float) $score->sla_rate, 1) }}%</td>
                    <td class="center">{{ number_format((float) $score->avg_star, 1) }}</td>
                    <td class="right">{{ number_format((float) $score->final_score, 1) }}</td>
                    <td class="center">
                        {{ $statusLabel }}
                        @if ($score->approved_at)
                            <br>{{ $score->approved_at->format('d/m/Y') }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columnCount }}" class="center">Không có dữ liệu KPI cho bộ lọc đã chọn.</td>
                </tr>
            @endforelse
        @else
            @forelse ($data as $row)
                @php
                    $periodLabelRow = match ($row->period_type) {
                        'quarterly' => 'Quý ' . $row->period_value . '/' . $row->period_year,
                        'yearly' => 'Năm ' . $row->period_year,
                        default => 'Tháng ' . $row->period_value . '/' . $row->period_year,
                    };

                    $statusLabel = match ($row->status) {
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Từ chối',
                        'locked' => 'Đã chốt',
                        default => 'Chờ duyệt',
                    };
                @endphp
                <tr>
                    <td>{{ $periodLabelRow }}</td>
                    <td class="center">{{ $row->total_tasks }}</td>
                    <td class="center">{{ number_format((float) $row->on_time_rate, 1) }}%</td>
                    <td class="center">{{ number_format((float) $row->sla_rate, 1) }}%</td>
                    <td class="center">{{ number_format((float) $row->avg_star, 1) }}</td>
                    <td class="right">{{ number_format((float) $row->final_score, 1) }}</td>
                    <td class="center">{{ number_format((float) ($row->target_score ?? 100), 0) }}</td>
                    <td class="center">{{ number_format((float) ($row->actual_score ?? $row->final_score), 1) }}</td>
                    <td class="center">{{ $statusLabel }}</td>
                    <td class="center">{{ $row->approved_at?->format('d/m/Y') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columnCount }}" class="center">Không có dữ liệu KPI cho bộ lọc đã chọn.</td>
                </tr>
            @endforelse
        @endif
    </tbody>
</table>
