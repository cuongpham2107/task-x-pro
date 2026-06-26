# Xuất báo cáo dự án (Excel) & ActivityLog cho Project

## 1. Mục tiêu

- Thay nút "Xuất báo cáo" hiện tại thành dropdown với 2 lựa chọn
- Xuất Excel báo cáo tổng dự án theo khoảng ngày
- Xuất Excel báo cáo chi tiết 1 dự án cụ thể
- Thêm ActivityLog tracking cho Project model để có dữ liệu "Tiến độ 7 ngày trước"

## 2. ActivityLog cho Project Model

Thêm vào `Project::booted()`:

- `created` → log `action: 'created'`
- `updated` → log `status_updated` nếu `status` thay đổi, `progress_updated` nếu `progress` thay đổi
- Thêm `activityLogs(): MorphMany` relation
- Trong `refreshProgressFromPhases()`: log trực tiếp `progress_updated` vì method dùng `saveQuietly()` (không fire events)

Pattern giống hệt Task model.

## 3. Export Classes

### ProjectReportExport (báo cáo tổng)
- `app/Exports/ProjectReportExport.php`
- `implements FromView, ShouldAutoSize`
- Blade: `resources/views/exports/project-report.blade.php`
- Data: projects trong date range (`start_date >= from`, `end_date <= to`), thống kê tổng quan
- Cấu trúc:
  - Header: thời gian, ngày xuất
  - I. Thống kê tổng quan: tổng dự án, đang chạy, quá hạn, hoàn thành + %
  - II. Danh sách dự án: STT, Tên, Leader, Start/End, Progress, Progress 7 ngày trước, Trạng thái

### ProjectDetailExport (báo cáo 1 dự án)
- `app/Exports/ProjectDetailExport.php`
- `implements FromView, ShouldAutoSize`
- Blade: `resources/views/exports/project-detail.blade.php`
- Data: project với phases + tasks
- Cấu trúc:
  - Header: tên dự án, thời gian, ngày xuất
  - I. Thống kê tổng quan: tổng task, đang làm, trễ hạn, hoàn thành, chưa làm, tiến độ tổng thể
  - II. Danh sách phase & task: STT, Giai đoạn, Tên Task, Leader, PIC, Độ ưu tiên, Progress, Progress 7 ngày trước, Trạng thái, Deadline

## 4. UI Components

### Dropdown
- Alpine.js inline (`x-data="{ open: false }"`)
- 2 options: "Xuất báo cáo tổng dự án", "Xuất báo cáo dự án cụ thể"

### Modal Date Range
- `x-ui.modal` với `wire:model="showDateRangeModal"`
- 2 `x-ui.datepicker`: filterExportStartDate, filterExportEndDate
- Nút "Xuất Excel" → `exportOverallReport()`

### Modal Chọn dự án
- `x-ui.modal` với `wire:model="showProjectSelectModal"`
- Search input → `exportProjectSearch`
- Danh sách radio → `selectedExportProjectId`
- Nút "Xuất Excel" → `exportProjectDetail()`

## 5. Livewire Component

Thêm vào SFC `resources/views/pages/projects/index.blade.php`:

**Properties:**
- `showDateRangeModal` (bool)
- `showProjectSelectModal` (bool)
- `filterExportStartDate` (?string)
- `filterExportEndDate` (?string)
- `exportProjectSearch` (string)
- `selectedExportProjectId` (?int)

**Methods:**
- `exportOverallReport()`: validate → query → download Excel
- `exportProjectDetail()`: validate → load → download Excel

**Computed:**
- `projectsForExport`: projects list for selection modal (searchable)

## 6. Data Query

### Overall Report
```php
Project::query()
    ->whereDate('start_date', '>=', $from)
    ->whereDate('end_date', '<=', $to)
    ->with(['leaders', 'phases.tasks'])
    ->get();
```

### Progress 7 ngày trước
Từ ActivityLog của project với `action = 'progress_updated'`, lấy `new_values->progress` gần nhất trước 7 ngày.

### Project Detail
```php
Project::query()
    ->with(['leaders', 'phases.tasks' => fn($q) => $q->with(['pic', 'phase']), 'phases.tasks.activityLogs'])
    ->findOrFail($id);
```

## 7. Output Format

Excel (.xlsx) sử dụng `Maatwebsite\Excel\Facades\Excel::download()`.
