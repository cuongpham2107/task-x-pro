<table>
    <thead>
        <tr>
            <th colspan="{{ $exportType === 'ceo' ? 7 : ($exportType === 'leader' ? 7 : 7) }}"
                style="font-size: 16px; font-weight: bold; text-align: center;">{{ $title }}</th>
        </tr>
        <tr>
            <th colspan="{{ $exportType === 'ceo' ? 7 : ($exportType === 'leader' ? 7 : 7) }}"
                style="font-style: italic; text-align: center;">Kỳ báo cáo: {{ $periodLabel }}</th>
        </tr>
        <tr></tr>
        @if ($exportType === 'ceo')
            <tr>a
                <th style="font-weight: bold; bckground-color: #f3f4f6; border: 1px solid #000000;">Phòng ban</th>
                <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">Trưởng bộ phận</th>
                <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    Nhân sự</th>
                <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    Avg Final Score</th>
                <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    SLA %</th>
                <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    Đúng hạn %</th>
                <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: right;">
                    Xếp loại</th>
            </tr>
        @elseif($exportType === 'leader')
            <tr>
                <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">Nhân viên</th>
                <th
                    style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    Tổng Task</th>
                <th
                    style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    Đúng hạn</th>
                <th
                    style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    Đúng hạn %</th>
                <th
                    style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    Đạt SLA %</th>
                <th
                    style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    ⭐ Avg Star</th>
                <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: right;">
                    Final Score</th>
            </tr>
        @else
            <tr>
                <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000;">Kỳ báo cáo</th>
                <th
                    style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    Tổng Task</th>
                <th
                    style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    Điểm thực tế</th>
                <th
                    style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    Chỉ tiêu</th>
                <th
                    style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    SLA %</th>
                <th
                    style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: center;">
                    Đúng hạn %</th>
                <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000000; text-align: right;">
                    Trạng thái</th>
            </tr>
        @endif
    </thead>
    <tbody>
        @if ($exportType === 'ceo')
            @foreach ($data as $dept)
                @php
                    $avgFinal = (float) ($dept->avg_final_score ?? 0);
                    $grade =
                        $avgFinal >= 9.0
                            ? 'Xuất sắc'
                            : ($avgFinal >= 8.0
                                ? 'Giỏi'
                                : ($avgFinal >= 7.0
                                    ? 'Khá'
                                    : ($avgFinal >= 5.0
                                        ? 'Đạt'
                                        : 'Yếu')));
                @endphp
                <tr>
                    <td style="border: 1px solid #000000;">{{ $dept->name }}</td>
                    <td style="border: 1px solid #000000;">{{ $dept->head?->name ?? '—' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $dept->active_users_count }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ number_format($avgFinal, 2) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">
                        {{ number_format((float) $dept->avg_sla_rate ?? 0, 1) }}%</td>
                    <td style="border: 1px solid #000000; text-align: center;">
                        {{ number_format((float) $dept->avg_on_time_rate ?? 0, 1) }}%</td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ $grade }}</td>
                </tr>
            @endforeach
        @elseif($exportType === 'leader')
            @foreach ($data as $score)
                <tr>
                    <td style="border: 1px solid #000000;">{{ $score->user?->name ?? '—' }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $score->total_tasks }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $score->on_time_tasks }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">
                        {{ number_format((float) $score->on_time_rate, 1) }}%</td>
                    <td style="border: 1px solid #000000; text-align: center;">
                        {{ number_format((float) $score->sla_rate, 1) }}%</td>
                    <td style="border: 1px solid #000000; text-align: center;">
                        {{ number_format((float) $score->avg_star, 1) }}</td>
                    <td style="border: 1px solid #000000; text-align: right;">
                        {{ number_format((float) $score->final_score, 1) }}</td>
                </tr>
            @endforeach
        @else
            @foreach ($data as $row)
                @php
                    $period =
                        $row->period_type === 'monthly'
                            ? 'Tháng ' . $row->period_value
                            : ($row->period_type === 'quarterly'
                                ? 'Quý ' . $row->period_value
                                : 'Năm');
                    $statusLabel =
                        $row->status === 'approved'
                            ? 'Đã duyệt'
                            : ($row->status === 'rejected'
                                ? 'Từ chối'
                                : ($row->status === 'locked'
                                    ? 'Đã khóa'
                                    : 'Chờ duyệt'));
                @endphp
                <tr>
                    <td style="border: 1px solid #000000;">{{ $period }} / {{ $row->period_year }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $row->total_tasks }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">
                        {{ number_format((float) ($row->actual_score ?? $row->final_score), 1) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">
                        {{ number_format((float) ($row->target_score ?? 100), 0) }}</td>
                    <td style="border: 1px solid #000000; text-align: center;">
                        {{ number_format((float) $row->sla_rate, 1) }}%</td>
                    <td style="border: 1px solid #000000; text-align: center;">
                        {{ number_format((float) $row->on_time_rate, 1) }}%</td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ $statusLabel }}</td>
                </tr>
            @endforeach
        @endif
    </tbody>
</table>
