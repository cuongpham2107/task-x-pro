# Migration from Maatwebsite/Excel to Spatie/laravel-pdf for PDF Export

## 1. Mục tiêu

- **Giữ nguyên**: Export Excel (XLSX) hoạt động như hiện tại với `Maatwebsite/Excel`
- **Thay đổi**: Export PDF sử dụng `spatie/laravel-pdf` thay vì `Maatwebsite/Excel` với DOMPDF writer
- **Không thay đổi**: `KpiExport` và `DashboardReportExport` classes, cũng như các Blade views

## 2. Hiện trạng

### Luồng export hiện tại:
```
Livewire Component (ceo/leader/pic/ceo-view)
  ↓
$writer = $format === 'pdf' ? \Maatwebsite\Excel\Excel::DOMPDF : \Maatwebsite\Excel\Excel::XLSX;
  ↓
Excel::download(new KpiExport(...), $filename, $writer)
  ↓
Maatwebsite/Excel:
  - XLSX: Creates Excel file from view
  - PDF: Renders view → converts HTML to PDF via DOMPDF
```

### Các file sử dụng export:
| File | Line | Export Type |
|------|------|-------------|
| `resources/views/components/kpi/ceo.blade.php` | 535-545 | KPI CEO |
| `resources/views/components/kpi/leader.blade.php` | 556-566 | KPI Leader |
| `resources/views/components/kpi/pic.blade.php` | 99-109 | KPI PIC |
| `resources/views/components/dashboard/ceo-view.blade.php` | 47-61 | Dashboard CEO |
| `scripts/generate_pdf.php` | 23 | CLI script |

### Export classes:
- `App\Exports\KpiExport` - implements `FromView`, `ShouldAutoSize`, `WithStyles`
- `App\Exports\DashboardReportExport` - implements `FromView`, `ShouldAutoSize`

### Views:
- `resources/views/exports/kpi.blade.php` - HTML template for KPI
- `resources/views/exports/dashboard-report.blade.php` - HTML template for Dashboard

## 3. Giải pháp: Conditional Export Logic

**Principle**: Single Responsibility - Mỗi format (Excel/PDF) có logic riêng, nhưng dùng chung data và view.

### Logic mới trong components:

```php
if ($format === 'pdf') {
    // NEW: Spatie PDF
    $html = view('exports.kpi', [
        'data' => $scores,
        'title' => $title,
        'periodLabel' => $periodLabel,
        'exportType' => 'ceo',
        'meta' => $meta,
    ])->render();

    return Pdf::loadHtml($html)
        ->format('a4')
        ->download($filename);
} else {
    // EXISTING: Maatwebsite Excel
    return Excel::download(
        new KpiExport($scores, $title, $periodLabel, 'ceo', $meta),
        $filename,
        \Maatwebsite\Excel\Excel::XLSX
    );
}
```

## 4. Chi tiết thay đổi

### 4.1. Files cần modify:

#### a) `resources/views/components/kpi/ceo.blade.php`
- **Method**: `exportReport()` (around line 535-545)
- **Change**: Replace writer logic with conditional PDF/Excel branch
- **Add import**: `use Spatie\Pdf\Facades\Pdf;`

#### b) `resources/views/components/kpi/leader.blade.php`
- **Method**: `exportReport()` (around line 556-566)
- **Change**: Same pattern
- **Add import**: `use Spatie\Pdf\Facades\Pdf;`

#### c) `resources/views/components/kpi/pic.blade.php`
- **Method**: `exportReport()` (around line 99-109)
- **Change**: Same pattern
- **Add import**: `use Spatie\Pdf\Facades\Pdf;`

#### d) `resources/views/components/dashboard/ceo-view.blade.php`
- **Method**: `exportReport()` (around line 47-61)
- **Change**: Same pattern, but use `exports.dashboard-report` view
- **Add import**: `use Spatie\Pdf\Facades\Pdf;`

#### e) `scripts/generate_pdf.php` (optional, if still used)
- Update to use spatie/laravel-pdf directly

### 4.2. Không thay đổi:
- `KpiExport` class
- `DashboardReportExport` class
- `resources/views/exports/*.blade.php` (views được dùng chung)
- Tests (có thể cần update expectations nếu PDF output khác)

## 5. Cấu hình spatie/laravel-pdf

Package đã được cài đặt (`^2.8` trong composer.json).

**Default config** (từ `config/pdf.php`):
- Uses Chromium/Headless Chrome for PDF generation
- Default format: A4
- Default options: no custom margins/size needed unless specified

**Usage**: `Pdf::loadHtml($html)->download($filename)`

## 6. Testing Strategy

### 6.1. Feature Tests to Update:

**File**: `tests/Feature/KpiExportViewTest.php`
- May need to update assertions if checking PDF generation
- Currently uses `Excel::raw()` with DOMPDF
- Consider adding separate PDF test or mocking `Pdf` facade

**File**: `tests/Feature/DashboardCeoExportTest.php`
- Similar updates for dashboard export

### 6.2. Manual Testing Checklist:

For each component (CEO, Leader, PIC, Dashboard):
- [ ] Export to PDF → downloads, correct format, readable
- [ ] Export to Excel → downloads .xlsx, correct data
- [ ] PDF page size: A4
- [ ] Font rendering: NotoSans (from storage_path)
- [ ] Table borders, colors preserved
- [ ] Vietnamese characters display correctly (UTF-8)
- [ ] Meta information (generated_at, generated_by, formula) appears

### 6.3. Known Compatibility:

The existing `kpi.blade.php` uses:
- Inline CSS (compatible with Chromium)
- `storage_path()` for fonts (needs verification spatie/pdf can access)
- Table-based layout (works well)

**Potential issue**: `storage_path()` in blade may not resolve correctly when rendered as string. We'll render via `view()->render()` so should be fine.

## 7. Font Handling (Critical)

### Problem:
Views use `@font-face` with `src: url('{{ storage_path("fonts/...") }}')` which outputs a filesystem path. This is NOT a valid URL for Chromium; it needs `file://` scheme.

### Solution:
Update `@font-face` declarations to use `file://` prefix:

```css
src: url('file://{{ storage_path("fonts/NotoSans-Regular.ttf") }}') format('truetype');
```

Renders as: `src: url('file:///Users/.../storage/fonts/NotoSans-Regular.ttf')` — valid for both DOMPDF and Chromium.

### Files to update:
- `resources/views/exports/kpi.blade.php` (lines 19-42)
- `resources/views/exports/dashboard-report.blade.php` (lines 19-42)

Note: `storage_path('fonts/...')` resolves to `/Users/cuongpham/Deverlop/Laravel/TaskXPro/storage/fonts/...` which exists and is readable.

## 8. Implementation Order

1. **Phase 1**: Update ceo.blade.php & leader.blade.php
2. **Phase 2**: Update pic.blade.php & ceo-view.blade.php
3. **Phase 3**: Update `scripts/generate_pdf.php` (if needed)
4. **Phase 4**: Run manual tests for all export types
5. **Phase 5**: Update feature tests if necessary
6. **Phase 6**: Code formatting with Laravel Pint

## 8. Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| spatie/pdf cannot resolve `storage_path()` in rendered HTML | PDF fonts missing | Pre-render font URLs or use absolute URLs |
| CSS differences between DOMPDF and Chromium | Layout shifts | Test and adjust CSS if needed |
| Memory/timeout for large datasets | Export fails | Already using ShouldAutoSize, no row limit issues expected |
| Tests fail due to mock expectations | CI breaks | Update mocks to expect `Pdf` facade calls |

## 9. Rollback Plan

If spatie/pdf fails:
- Revert to `$writer = Excel::DOMPDF` logic
- Keep both options as config flag if needed

---

**Decision**: Proceed with Approach A (conditional logic in components) as designed.
