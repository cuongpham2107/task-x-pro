@php
    $generatedAt = now()->format('d/m/Y H:i');
@endphp

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            font-family: 'NotoSans', 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: middle; }

        .title {
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            background: #16a34a;
            color: #ffffff;
        }
        .meta {
            font-size: 10px;
            color: #334155;
            background: #f0fdf4;
            text-align: center;
        }
        .meta-right {
            font-size: 10px;
            color: #334155;
            background: #f0fdf4;
            text-align: right;
        }
        .section {
            font-weight: 700;
            font-size: 13px;
            background: #ffffff;
            border: none;
            padding: 10px 0 4px 0;
        }

        /* Stat cards */
        .stat-table { border: none; }
        .stat-table td { border: none; padding: 4px; }
        .stat-card {
            text-align: center;
            border-radius: 4px;
            padding: 10px 6px;
            border: 1px solid #e2e8f0;
        }
        .stat-label { font-size: 10px; font-weight: 700; color: #fff; padding: 4px; border-radius: 3px 3px 0 0; margin: -10px -6px 6px -6px; }
        .stat-value { font-size: 22px; font-weight: 700; }
        .bg-gray   { background: #64748b; }
        .bg-blue   { background: #2563eb; }
        .bg-red    { background: #dc2626; }
        .bg-green  { background: #16a34a; }
        .bg-slate  { background: #94a3b8; }
        .bg-orange { background: #ea580c; }
        .color-gray   { color: #64748b; }
        .color-blue   { color: #2563eb; }
        .color-red    { color: #dc2626; }
        .color-green  { color: #16a34a; }
        .color-slate  { color: #94a3b8; }
        .color-orange { color: #ea580c; }

        /* Task list table */
        .head { background: #334155; color: #ffffff; font-weight: 700; text-align: center; }
        .center { text-align: center; }

        /* Priority colors */
        .pri-high   { color: #dc2626; font-weight: 700; }
        .pri-medium { color: #d97706; font-weight: 700; }
        .pri-low    { color: #7c3aed; font-weight: 700; }

        /* Status badge */
        .status-done       { background: #bbf7d0; color: #15803d; font-weight: 700; border-radius: 3px; padding: 2px 5px; }
        .status-inprogress { background: #bfdbfe; color: #1d4ed8; font-weight: 700; border-radius: 3px; padding: 2px 5px; }
        .status-overdue    { background: #fecaca; color: #b91c1c; font-weight: 700; border-radius: 3px; padding: 2px 5px; }
        .status-todo       { background: #e2e8f0; color: #475569; font-weight: 700; border-radius: 3px; padding: 2px 5px; }
    </style>
</head>

<body>
    {{-- Header --}}
    <table>
        <tr>
            <th colspan="2" class="title">BÁO CÁO TIẾN ĐỘ DỰ ÁN {{ strtoupper($projectName ?? '') }}</th>
        </tr>
        <tr>
            <td class="meta">Thời gian: Từ {{ $fromDate }} đến {{ $toDate }}</td>
            <td class="meta-right">Ngày xuất báo cáo: {{ $generatedAt }}</td>
        </tr>
    </table>

    <br>

    {{-- Section I --}}
    <table class="stat-table" style="border:none;">
        <tr><td colspan="6" style="border:none; font-weight:700; font-size:13px; padding:0 0 6px 0;">I. THỐNG KÊ TỔNG QUAN</td></tr>
        <tr>
            <td style="border:none; padding:4px; width:16.6%">
                <div class="stat-card">
                    <div class="stat-label bg-gray">Tổng số task</div>
                    <div class="stat-value color-gray">{{ $summary['total'] ?? 0 }}</div>
                </div>
            </td>
            <td style="border:none; padding:4px; width:16.6%">
                <div class="stat-card">
                    <div class="stat-label bg-blue">Task đang thực hiện</div>
                    <div class="stat-value color-blue">{{ $summary['running'] ?? 0 }}</div>
                </div>
            </td>
            <td style="border:none; padding:4px; width:16.6%">
                <div class="stat-card">
                    <div class="stat-label bg-red">Task trễ hạn</div>
                    <div class="stat-value color-red">{{ $summary['overdue'] ?? 0 }}</div>
                </div>
            </td>
            <td style="border:none; padding:4px; width:16.6%">
                <div class="stat-card">
                    <div class="stat-label bg-green">Task hoàn thành</div>
                    <div class="stat-value color-green">{{ $summary['completed'] ?? 0 }}</div>
                </div>
            </td>
            <td style="border:none; padding:4px; width:16.6%">
                <div class="stat-card">
                    <div class="stat-label bg-slate">Task chưa làm</div>
                    <div class="stat-value color-slate">{{ $summary['todo'] ?? 0 }}</div>
                </div>
            </td>
            <td style="border:none; padding:4px; width:16.6%">
                <div class="stat-card">
                    <div class="stat-label bg-orange">Tiến độ tổng thể</div>
                    <div class="stat-value color-orange">{{ number_format((float)($summary['avg_progress'] ?? 0), 0) }}%</div>
                </div>
            </td>
        </tr>
    </table>

    <br>

    {{-- Section II --}}
    <table>
        <tr>
            <th colspan="10" class="section">II. DANH SÁCH GIAI ĐOẠN &amp; TASK</th>
        </tr>
        <tr>
            <th class="head" width="28">STT</th>
            <th class="head">Giai đoạn</th>
            <th class="head">Tên Task</th>
            <th class="head">Leader</th>
            <th class="head">PIC</th>
            <th class="head">Độ ưu tiên</th>
            <th class="head center">Tiến độ hiện tại</th>
            <th class="head center">Tiến độ 7 ngày trước</th>
            <th class="head center">Trạng thái</th>
            <th class="head center">Deadline</th>
        </tr>
        @forelse ($projects as $index => $project)
            @php
                $priClass = match(strtolower($project['priority'] ?? '')) {
                    'cao', 'high'   => 'pri-high',
                    'trung bình', 'medium' => 'pri-medium',
                    'thấp', 'low'   => 'pri-low',
                    default         => '',
                };
                $statusClass = match($project['status_key'] ?? $project['status_label'] ?? '') {
                    'completed', 'Hoàn thành'      => 'status-done',
                    'in_progress', 'Đang thực hiện' => 'status-inprogress',
                    'overdue', 'Trễ hạn'            => 'status-overdue',
                    default                          => 'status-todo',
                };
            @endphp
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $project['phase'] ?? '' }}</td>
                <td>{{ $project['name'] }}</td>
                <td>{{ $project['leader_names'] }}</td>
                <td>{{ $project['pic'] ?? '' }}</td>
                <td class="center {{ $priClass }}">{{ $project['priority'] ?? '' }}</td>
                <td class="center">{{ $project['progress'] }}%</td>
                <td class="center">{{ $project['progress_7d_ago'] !== null ? $project['progress_7d_ago'] . '%' : '—' }}</td>
                <td class="center"><span class="{{ $statusClass }}">{{ $project['status_label'] }}</span></td>
                <td class="center">{{ $project['deadline'] ?? $project['end_date'] ?? '' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="center">Không có task nào trong khoảng thời gian này.</td>
            </tr>
        @endforelse
    </table>
</body>

</html>