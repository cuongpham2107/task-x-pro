@php
    $generatedAt = now()->format('d/m/Y H:i');
    $projects = $data['projects'] ?? [];
    $phases = $data['phases'] ?? [];
    $tasks = $data['tasks'] ?? [];
    $kpiMonthly = $data['kpi']['monthly'] ?? [];
    $kpiQuarterly = $data['kpi']['quarterly'] ?? [];
    $topPerformers = collect($data['top_performers'] ?? []);
    $recentTasks = collect($data['recent_tasks'] ?? []);
    $approvalTasks = collect($data['approval_tasks'] ?? []);
@endphp

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        @font-face {
    font-family: 'NotoSans';
    src: url('file://{{ storage_path("fonts/NotoSans-Regular.ttf") }}') format('truetype');
    font-weight: 400;
    font-style: normal;
}
        @font-face {
    font-family: 'NotoSans';
    src: url('file://{{ storage_path("fonts/NotoSans-Bold.ttf") }}') format('truetype');
    font-weight: 700;
    font-style: normal;
}
        @font-face {
    font-family: 'NotoSans';
    src: url('file://{{ storage_path("fonts/NotoSans-Italic.ttf") }}') format('truetype');
    font-weight: 400;
    font-style: italic;
}
        @font-face {
    font-family: 'NotoSans';
    src: url('file://{{ storage_path("fonts/NotoSans-BoldItalic.ttf") }}') format('truetype');
    font-weight: 700;
    font-style: italic;
}

html, body {
    font-family: 'NotoSans', sans-serif;
    font-size: 11px;
    color: #0f172a;
}

table, th, td, p, div, span {
    font-family: 'NotoSans', sans-serif;
}
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .title {
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            background: #e2e8f0;
        }

        .meta {
            font-size: 10px;
            color: #334155;
            background: #f8fafc;
        }

        .section {
            font-weight: 700;
            background: #e2e8f0;
        }

        .head {
            background: #f1f5f9;
            font-weight: 700;
        }

        .center {
            text-align: center;
        }
    </style>
</head>

<body>
    <table>
        <tr>
            <th colspan="4" class="title">{{ $title }}</th>
        </tr>
        <tr>
            <th colspan="4" class="meta">Kỳ báo cáo: {{ $periodLabel }}</th>
        </tr>
        <tr>
            <th colspan="4" class="meta">Thời gian xuất: {{ $generatedAt }} | Người xuất: {{ $generatedBy }}</th>
        </tr>
    </table>

    <table>
        <tr>
            <th colspan="4" class="section">Tổng quan công ty</th>
        </tr>
        <tr>
            <th class="head">Chỉ số</th>
            <th class="head center">Giá trị</th>
            <th class="head">Chỉ số</th>
            <th class="head center">Giá trị</th>
        </tr>
        <tr>
            <td>Tổng dự án</td>
            <td class="center">{{ $projects['total'] ?? 0 }}</td>
            <td>Đang thực hiện</td>
            <td class="center">{{ $projects['running'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Tạm dừng</td>
            <td class="center">{{ $projects['paused'] ?? 0 }}</td>
            <td>Hoàn thành</td>
            <td class="center">{{ $projects['completed'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Tiến độ dự án trung bình</td>
            <td class="center">{{ number_format((float) ($projects['avg_progress'] ?? 0), 2) }}%</td>
            <td>Tổng công việc</td>
            <td class="center">{{ $tasks['total'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Đang xử lý</td>
            <td class="center">{{ $tasks['in_progress'] ?? 0 }}</td>
            <td>Chờ phê duyệt</td>
            <td class="center">{{ $tasks['waiting_approval'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Quá hạn</td>
            <td class="center">{{ $tasks['late'] ?? 0 }}</td>
            <td>Sắp đến hạn</td>
            <td class="center">{{ $tasks['due_soon'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Phase tổng</td>
            <td class="center">{{ $phases['total'] ?? 0 }}</td>
            <td>Phase đang hoạt động</td>
            <td class="center">{{ $phases['active'] ?? 0 }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th colspan="4" class="section">KPI tổng hợp</th>
        </tr>
        <tr>
            <th class="head">Kỳ KPI</th>
            <th class="head center">Điểm</th>
            <th class="head center">Tỷ lệ đúng hạn</th>
            <th class="head center">Tỷ lệ SLA</th>
        </tr>
        <tr>
            <td>KPI tháng</td>
            <td class="center">{{ number_format((float) ($kpiMonthly['final_score'] ?? 0), 1) }}</td>
            <td class="center">{{ number_format((float) ($kpiMonthly['on_time_rate'] ?? 0), 1) }}%</td>
            <td class="center">{{ number_format((float) ($kpiMonthly['sla_rate'] ?? 0), 1) }}%</td>
        </tr>
        <tr>
            <td>KPI quý</td>
            <td class="center">{{ number_format((float) ($kpiQuarterly['final_score'] ?? 0), 1) }}</td>
            <td class="center">{{ number_format((float) ($kpiQuarterly['on_time_rate'] ?? 0), 1) }}%</td>
            <td class="center">{{ number_format((float) ($kpiQuarterly['sla_rate'] ?? 0), 1) }}%</td>
        </tr>
    </table>

    <table>
        <tr>
            <th colspan="3" class="section">Top hiệu suất</th>
        </tr>
        <tr>
            <th class="head">Nhân sự</th>
            <th class="head center">Điểm</th>
            <th class="head center">SLA / Đúng hạn</th>
        </tr>
        @forelse ($topPerformers as $performer)
            <tr>
                <td>{{ $performer['user_name'] ?? '—' }}</td>
                <td class="center">{{ number_format((float) ($performer['final_score'] ?? 0), 1) }}</td>
                <td class="center">
                    {{ number_format((float) ($performer['sla_rate'] ?? 0), 1) }}% / {{ number_format((float) ($performer['on_time_rate'] ?? 0), 1) }}%
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="center">Chưa có dữ liệu top performer.</td>
            </tr>
        @endforelse
    </table>

    <table>
        <tr>
            <th colspan="4" class="section">Công việc gần đây</th>
        </tr>
        <tr>
            <th class="head">Công việc</th>
            <th class="head">Dự án / Phase</th>
            <th class="head center">PIC</th>
            <th class="head center">Deadline</th>
        </tr>
        @forelse ($recentTasks->take(10) as $task)
            <tr>
                <td>{{ $task->name }}</td>
                <td>{{ $task->phase?->project?->name ?? 'N/A' }} / {{ $task->phase?->name ?? 'N/A' }}</td>
                <td class="center">{{ $task->pic?->name ?? 'N/A' }}</td>
                <td class="center">{{ $task->deadline?->format('d/m/Y') ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="center">Không có dữ liệu task gần đây.</td>
            </tr>
        @endforelse
    </table>

    <table>
        <tr>
            <th colspan="5" class="section">Task chờ phê duyệt</th>
        </tr>
        <tr>
            <th class="head">Công việc</th>
            <th class="head">Dự án / Phase</th>
            <th class="head center">PIC</th>
            <th class="head center">Tiến độ</th>
            <th class="head center">Deadline</th>
        </tr>
        @forelse ($approvalTasks->take(10) as $task)
            <tr>
                <td>{{ $task->name }}</td>
                <td>{{ $task->phase?->project?->name ?? 'N/A' }} / {{ $task->phase?->name ?? 'N/A' }}</td>
                <td class="center">{{ $task->pic?->name ?? 'N/A' }}</td>
                <td class="center">{{ (int) $task->progress }}%</td>
                <td class="center">{{ $task->deadline?->format('d/m/Y') ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="center">Không có task chờ phê duyệt.</td>
            </tr>
        @endforelse
    </table>
</body>

</html>