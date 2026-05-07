# Migrate PDF Export to Spatie/laravel-pdf Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate PDF export functionality from Maatwebsite/Excel (DOMPDF) to spatie/laravel-pdf while keeping Excel export unchanged.

**Architecture:** Replace the PDF writer branch in Livewire components with direct spatie/laravel-pdf usage. Render the same Blade views to HTML, then convert with `Pdf::loadHtml()`. Also fix font URLs in export views to use `file://` scheme for Chromium compatibility.

**Tech Stack:** Laravel 12, Livewire 4, spatie/laravel-pdf v2.8, Maatwebsite/Excel v3.1

---

## File Structure

**Files to modify (6 total):**

| File | Purpose |
|------|---------|
| `resources/views/components/kpi/ceo.blade.php` | Replace export logic, add `Pdf` import |
| `resources/views/components/kpi/leader.blade.php` | Replace export logic, add `Pdf` import |
| `resources/views/components/kpi/pic.blade.php` | Replace export logic, add `Pdf` import |
| `resources/views/components/dashboard/ceo-view.blade.php` | Replace export logic, add `Pdf` import |
| `resources/views/exports/kpi.blade.php` | Fix `@font-face` URLs (add `file://`) |
| `resources/views/exports/dashboard-report.blade.php` | Fix `@font-face` URLs (add `file://`) |

**Optional:**
- `scripts/generate_pdf.php` - Update if still used (check before modifying)

**Tests to verify (manual + existing):**
- `tests/Feature/KpiExportViewTest.php` - May need mock updates
- `tests/Feature/DashboardCeoExportTest.php` - May need mock updates

---

## Task 1: Update KPI CEO Component (`ceo.blade.php`)

**Files:**  
- Modify: `resources/views/components/kpi/ceo.blade.php:525-550`

- [ ] **Step 1:** Locate the `exportReport` method in `ceo.blade.php`. Find the block setting `$writer` and returning `Excel::download(...)`.

- [ ] **Step 2:** Add `use Spatie\Pdf\Facades\Pdf;` at the top with other imports (around line 13).

```php
use Spatie\Pdf\Facades\Pdf;
```

- [ ] **Step 3:** Replace the export logic (currently lines ~535-545) with conditional branching:

```php
$filename = 'kpi-ceo-' . $this->selectedValue . '-' . $this->selectedYear . '.' . ($format === 'pdf' ? 'pdf' : 'xlsx');

$this->dispatch('toast', message: 'Bắt đầu xuất file ' . strtoupper($format), type: 'info');

$meta = [
    'generated_at' => now()->format('d/m/Y H:i'),
    'generated_by' => auth()->user()?->name ?? 'Hệ thống',
    'formula' => 'Điểm = (% đúng hạn x 0.4) + (% SLA đạt x 0.4) + (sao x 0.2)',
];

if ($format === 'pdf') {
    $html = view('exports.kpi', [
        'data' => $stats,
        'title' => $title,
        'periodLabel' => $periodLabel,
        'exportType' => 'ceo',
        'meta' => $meta,
    ])->render();

    return Pdf::loadHtml($html)
        ->format('a4')
        ->download($filename);
}

return Excel::download(
    new KpiExport($stats, $title, $periodLabel, 'ceo', $meta),
    $filename,
    \Maatwebsite\Excel\Excel::XLSX
);
```

- [ ] **Step 4:** Remove the old `$writer` variable assignment (no longer needed).

- [ ] **Step 5:** Save and commit.

```bash
git add resources/views/components/kpi/ceo.blade.php
git commit -m "feat: migrate PDF export to spatie/laravel-pdf for CEO KPI"
```

---

## Task 2: Update KPI Leader Component (`leader.blade.php`)

**Files:**  
- Modify: `resources/views/components/kpi/leader.blade.php:545-570`

- [ ] **Step 1:** Add `use Spatie\Pdf\Facades\Pdf;` at the top (line 12, after other imports).

- [ ] **Step 2:** Locate `exportReport` method and replace the export logic (around line 556-566) with the same conditional pattern:

```php
$filename = 'kpi-team-'.$this->selectedValue.'-'.$this->selectedYear.'.'.($format === 'pdf' ? 'pdf' : 'xlsx');

$this->dispatch('toast', message: 'Bắt đầu xuất file '.strtoupper($format), type: 'info');

$meta = [
    'generated_at' => now()->format('d/m/Y H:i'),
    'generated_by' => auth()->user()?->name ?? 'Hệ thống',
    'formula' => 'Điểm = (% đúng hạn x 0.4) + (% SLA đạt x 0.4) + (sao x 0.2)',
];

if ($format === 'pdf') {
    $html = view('exports.kpi', [
        'data' => $scores,
        'title' => $title,
        'periodLabel' => $periodLabel,
        'exportType' => 'leader',
        'meta' => $meta,
    ])->render();

    return Pdf::loadHtml($html)
        ->format('a4')
        ->download($filename);
}

return Excel::download(
    new KpiExport($scores, $title, $periodLabel, 'leader', $meta),
    $filename,
    \Maatwebsite\Excel\Excel::XLSX
);
```

- [ ] **Step 3:** Remove old `$writer` assignment.

- [ ] **Step 4:** Save and commit.

```bash
git add resources/views/components/kpi/leader.blade.php
git commit -m "feat: migrate PDF export to spatie/laravel-pdf for Leader KPI"
```

---

## Task 3: Update KPI PIC Component (`pic.blade.php`)

**Files:**  
- Modify: `resources/views/components/kpi/pic.blade.php:90-110`

- [ ] **Step 1:** Add `use Spatie\Pdf\Facades\Pdf;` at the top (line 12, after other imports).

- [ ] **Step 2:** Locate `exportReport` method and replace logic (lines ~99-109):

```php
$filename = 'kpi-ca-nhan-' . $ownerSlug . '-' . $this->historyYear . '.' . ($format === 'pdf' ? 'pdf' : 'xlsx');

$this->dispatch('toast', message: 'Bắt đầu xuất file ' . strtoupper($format), type: 'info');

$meta = [
    'generated_at' => now()->format('d/m/Y H:i'),
    'generated_by' => auth()->user()?->name ?? 'Hệ thống',
    'formula' => 'Điểm = (% đúng hạn x 0.4) + (% SLA đạt x 0.4) + (sao x 0.2)',
];

if ($format === 'pdf') {
    $html = view('exports.kpi', [
        'data' => $scores,
        'title' => $title,
        'periodLabel' => $periodLabel,
        'exportType' => 'pic',
        'meta' => $meta,
    ])->render();

    return Pdf::loadHtml($html)
        ->format('a4')
        ->download($filename);
}

return Excel::download(
    new KpiExport($scores, $title, $periodLabel, 'pic', $meta),
    $filename,
    \Maatwebsite\Excel\Excel::XLSX
);
```

- [ ] **Step 3:** Remove old `$writer` assignment.

- [ ] **Step 4:** Save and commit.

```bash
git add resources/views/components/kpi/pic.blade.php
git commit -m "feat: migrate PDF export to spatie/laravel-pdf for PIC KPI"
```

---

## Task 4: Update Dashboard CEO Component (`ceo-view.blade.php`)

**Files:**  
- Modify: `resources/views/components/dashboard/ceo-view.blade.php:44-61`

- [ ] **Step 1:** Add `use Spatie\Pdf\Facades\Pdf;` at the top (line 7, after `use Maatwebsite\Excel\Facades\Excel;`).

```php
use Spatie\Pdf\Facades\Pdf;
```

- [ ] **Step 2:** Replace `exportReport` method body (lines 44-61):

```php
public function exportReport(string $format = 'xlsx')
{
    $filename = 'dashboard-ceo-' . now()->format('Y-m-d-His') . '.' . ($format === 'pdf' ? 'pdf' : 'xlsx');

    $this->dispatch('toast', message: 'Bắt đầu xuất báo cáo CEO', type: 'info');

    if ($format === 'pdf') {
        $html = view('exports.dashboard-report', [
            'data' => $this->data,
            'title' => 'Báo cáo dashboard CEO',
            'periodLabel' => 'Toàn bộ công ty',
            'generated_by' => auth()->user()?->name ?? 'Hệ thống',
        ])->render();

        return Pdf::loadHtml($html)
            ->format('a4')
            ->download($filename);
    }

    return Excel::download(
        new DashboardReportExport(
            $this->data,
            'Báo cáo dashboard CEO',
            'Toàn bộ công ty',
            auth()->user()?->name ?? 'Hệ thống',
        ),
        $filename,
        \Maatwebsite\Excel\Excel::XLSX
    );
}
```

Note: `DashboardReportExport` constructor expects same params; adjust view data accordingly.

- [ ] **Step 3:** Remove old `$writer` variable.

- [ ] **Step 4:** Save and commit.

```bash
git add resources/views/components/dashboard/ceo-view.blade.php
git commit -m "feat: migrate PDF export to spatie/laravel-pdf for Dashboard CEO"
```

---

## Task 5: Fix Font URLs in Export Views (file:// scheme)

**Rationale:** `storage_path()` returns filesystem path (e.g., `/Users/.../storage/fonts/...`). For Chromium, we need `file://` URL scheme.

**Files:**
- `resources/views/exports/kpi.blade.php`
- `resources/views/exports/dashboard-report.blade.php`

### 5a. Update `kpi.blade.php`

- [ ] **Step 1:** Open `resources/views/exports/kpi.blade.php` and locate all four `@font-face` blocks (lines 19-42).

- [ ] **Step 2:** For each `src: url('{{ storage_path(...) }}')` replace with:

```css
src: url('file://{{ storage_path("fonts/NotoSans-Regular.ttf") }}') format('truetype');
```

Do this for all four variants (Regular, Bold, Italic, BoldItalic).

Example diff:
```diff
- src: url('{{ storage_path("fonts/NotoSans-Regular.ttf") }}') format('truetype');
+ src: url('file://{{ storage_path("fonts/NotoSans-Regular.ttf") }}') format('truetype');
```

- [ ] **Step 3:** Save and stage changes (but don't commit yet; wait until both views updated).

### 5b. Update `dashboard-report.blade.php`

- [ ] **Step 4:** Apply identical changes to `resources/views/exports/dashboard-report.blade.php` (lines 19-42).

- [ ] **Step 5:** Verify both files have `file://` prefix for all font URLs.

- [ ] **Step 6:** Commit both files together.

```bash
git add resources/views/exports/kpi.blade.php resources/views/exports/dashboard-report.blade.php
git commit -m "fix: use file:// scheme for font URLs in export views for Chromium compatibility"
```

---

## Task 6: Optional: Update `scripts/generate_pdf.php`

**Check:** Determine if this script is still used. If yes, update; if not, skip.

- [ ] **Step 1:** Read `scripts/generate_pdf.php` to see if it's referenced anywhere or used in CI/自动化.

```bash
cat scripts/generate_pdf.php
```

- [ ] **Step 2:** If script is active, replace its core logic:

Current:
```php
$pdf = Excel::raw(new KpiExport($data, ...), \Maatwebsite\Excel\Excel::DOMPDF);
```

Change to:
```php
$html = view('exports.kpi', [...])->render();
$pdf = Pdf::loadHtml($html)->format('a4')->output();
```

- [ ] **Step 3:** If updated, commit.

```bash
git add scripts/generate_pdf.php
git commit -m "chore: update generate_pdf script to use spatie/laravel-pdf"
```

- [ ] **Step 4:** If script is deprecated, add a note in the commit to delete later, or skip.

---

## Task 7: Manual Testing

**Goal:** Verify both Excel and PDF exports work correctly for all four components.

- [ ] **Step 1:** Start local dev server if not running:

```bash
php artisan serve
```

- [ ] **Step 2:** For each component (CEO, Leader, PIC, Dashboard CEO):
  - Navigate to the page in browser.
  - Test **Export PDF** → downloads, opens correctly, all data visible, fonts render, Vietnamese chars OK.
  - Test **Export Excel** → downloads .xlsx, opens in Excel/LibreOffice, data intact.

- [ ] **Step 3:** Check PDF specifics:
  - Page size: A4
  - Margins: default (no truncation)
  - Table borders and colors preserved
  - Meta info (generated_at, generated_by, formula) appears at top
  - No missing font warnings (check console if any)

- [ ] **Step 4:** Check Excel specifics:
  - Auto-size columns work (should auto-fit)
  - Styles (bold headers, colors) preserved

- [ ] **Step 5:** If any issue found, fix and re-test before proceeding.

---

## Task 8: Feature Tests (if applicable)

**Review existing tests to ensure they pass:**

- [ ] **Step 1:** Run all tests:

```bash
php artisan test --compact
```

- [ ] **Step 2:** Check `tests/Feature/KpiExportViewTest.php` and `DashboardCeoExportTest.php` for failures.

- [ ] **Step 3:** If tests mock `Excel::download()` or `Excel::raw()`, update mocks to also allow `Pdf::loadHtml()`.

Example mock adjustment:
```php
// Before
Excel::shouldReceive('download')->once()->andReturn(...);

// After: also mock Pdf facade if testing PDF branch
Pdf::shouldReceive('loadHtml')->once()->andReturnSelf()
   ->shouldReceive('format')->andReturnSelf()
   ->shouldReceive('download')->andReturn(...);
```

- [ ] **Step 4:** Re-run tests until all pass.

```bash
php artisan test --compact
```

- [ ] **Step 5:** Commit test changes.

```bash
git add tests/Feature/
git commit -m "test: update mocks for spatie/pdf migration"
```

---

## Task 9: Code Formatting

- [ ] **Step 1:** Run Laravel Pint on all modified PHP files:

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 2:** Review output; ensure no unfixable issues. Pint should auto-fix.

- [ ] **Step 3:** Commit formatting changes if any.

```bash
git add -A
git commit -m "style: format code with Laravel Pint"
```

---

## Task 10: Final Verification & Merge Preparation

- [ ] **Step 1:** Review all commits in this feature branch. Should be linear and clean.

- [ ] **Step 2:** Run full test suite one final time:

```bash
php artisan test --compact
```

- [ ] **Step 3:** Ensure no lint errors:

```bash
vendor/bin/pint --test --format agent
```

- [ ] **Step 4:** Write a concise commit message for final merge (if squash merging) or ensure merge commit is clear.

- [ ] **Step 5:** Push branch to remote (if collaboration needed) or create PR.

---

## Rollback Notes

If spatie/laravel-pdf fails unexpectedly:
1. Revert each component's export logic to original `$writer` pattern.
2. Restore original `@font-face` URLs (remove `file://`).
3. Git revert the entire feature commit series.

---

## Notes

- All views are shared between Excel and PDF — no duplication.
- Font files are in `storage/fonts/` and accessible via absolute path.
- Use frequent small commits for easier debugging.
- Test PDF in multiple browsers if possible (Chrome/Edge).
