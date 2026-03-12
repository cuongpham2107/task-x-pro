# Cong Thuc Tinh Toan TaskXPro

Tai lieu nay tong hop cong thuc dang duoc ap dung trong model de tinh:
- `tasks.progress`
- `phases.progress`
- `projects.progress`
- `kpi_scores` theo BR-002

## 1. Task Progress

- Nguon gia tri: user tu cap nhat.
- Rang buoc: he thong chuan hoa ve khoang `0..100`.
  - Neu `progress < 0` thi luu `0`.
  - Neu `progress > 100` thi luu `100`.

## 2. Phase Progress

- Cong thuc:

```text
phases.progress = AVG(tasks.progress)
```

- Lam tron: luu dang so nguyen (`round`).
- Quy uoc trang thai:
  - `progress = 0` -> `pending`
  - `0 < progress < 100` -> `active`
  - `progress = 100` -> `completed`

## 3. Project Progress (BR-009)

- Dieu kien nghiep vu:

```text
Tong weight cua tat ca phase trong 1 project phai = 100
```

- Cong thuc:

```text
projects.progress = SUM(phase.progress * phase.weight / 100)
```

- Lam tron: luu dang so nguyen (`round`).

## 4. KPI Score (BR-002)

### 4.1. On-time rate

```text
on_time_rate = on_time_tasks / total_tasks * 100
```

Trong do:
- `total_tasks`: so task `completed` cua user trong ky.
- `on_time_tasks`: so task co `completed_at <= deadline`.

### 4.2. SLA rate

```text
sla_rate = sla_met_tasks / total_tasks * 100
```

Trong do:
- `sla_met_tasks`: so task `completed` co `sla_met = true`.

### 4.3. Avg star

```text
avg_star = AVG(approval_logs.star_rating)
```

Dieu kien tinh:
- `approval_logs.action = approved`
- `star_rating IS NOT NULL`
- approval log thuoc task cua user trong ky KPI.

### 4.4. Final score

Cong thuc BR-002:

```text
final_score = (on_time_rate * 0.4)
            + (sla_rate * 0.4)
            + ((avg_star / 5 * 100) * 0.2)
```

## 5. Logic SLA Khi Task Completed (BR-007)

Khi `tasks.status = completed`:
- Ep `tasks.progress = 100`.
- Tinh `sla_met`:

```text
sla_met = (completed_at - started_at) <= sla_standard_hours * 3600
```

- Tinh `delay_days`:

```text
delay_days = max(0, completed_at - deadline) / 86400
```

## 6. Vi Du Nhanh

### 6.1. Tinh phase progress

Neu phase co 3 task: `80, 40, 100`

```text
AVG = (80 + 40 + 100) / 3 = 73.33 -> 73
```

### 6.2. Tinh project progress

Neu project co 3 phase:
- Phase A: `progress=73`, `weight=30`
- Phase B: `progress=50`, `weight=30`
- Phase C: `progress=90`, `weight=40`

```text
Project = 73*30/100 + 50*30/100 + 90*40/100
        = 21.9 + 15 + 36
        = 72.9 -> 73
```

### 6.3. Tinh final score KPI

Neu:
- `total_tasks=10`
- `on_time_tasks=7` -> `on_time_rate=70`
- `sla_met_tasks=8` -> `sla_rate=80`
- `avg_star=4.2`

```text
final_score = 70*0.4 + 80*0.4 + ((4.2/5*100)*0.2)
            = 28 + 32 + 16.8
            = 76.8
```

## 7. Ghi Chu Dong Bo Realtime

Sau moi lan `Task` thay doi:
- Tinh lai `Phase.progress` tu task.
- Tinh lai `Project.progress` tu phase.
- Dong bo lai KPI thang/quy cho PIC lien quan.

