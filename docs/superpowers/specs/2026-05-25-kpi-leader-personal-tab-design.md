# KPI Leader — Thêm Tab Cá Nhân

## Vấn đề

Leader hiện tại chỉ xem được giao diện KPI quản lý đội (`kpi.leader`), không xem được KPI cá nhân của chính mình (vốn chỉ dành cho role `pic`).

## Giải pháp

Thêm tab "Cá nhân" / "Quản lý đội" vào page component `pages/kpi-scores/index.blade.php` cho role leader. Mỗi tab render Livewire component tương ứng.

## Kiến trúc

- **1 file thay đổi**: `resources/views/pages/kpi-scores/index.blade.php`
- **Không đụng** `leader.blade.php` hay `pic.blade.php`

### Luồng mới

| Role | Hành vi |
|------|---------|
| `ceo` | Giữ nguyên — chỉ thấy CEO view |
| `leader` | Thấy 2 tab: "Cá nhân" (kpi.pic) + "Quản lý đội" (kpi.leader) |
| Khác (pic, employee) | Giữ nguyên — chỉ thấy PIC view |

### State

- `$activeTab` — `'personal'` | `'team'`, mặc định `'personal'`
- Khi chuyển tab → Livewire unmount component cũ, mount component mới

### Giao diện Tab

- Thanh tab ngang với border-bottom
- Active tab: `border-primary text-primary`
- Inactive tab: `border-transparent text-slate-500`
- Icon Material Symbols: `person` (cá nhân), `groups` (quản lý đội)
- Chỉ hiển thị khi user có role `leader`

## File thay đổi

- `resources/views/pages/kpi-scores/index.blade.php` — thêm class state + HTML tabs
