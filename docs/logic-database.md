**TaskXPro**

Database Design Document

_Laravel · MySQL 8.0 · v1.0_

**I. Tổng quan kiến trúc Database**

Database được thiết kế cho ứng dụng TaskXPro trên nền tảng Laravel, sử dụng MySQL 8.0. Tổng cộng 16 bảng chia thành 5 nhóm chức năng:

**① Nhân sự & Tổ chức:** users, departments

**② Dự án & Tiến độ:** projects, project_leaders, phases, phase_templates

**③ Task & Công việc:** tasks, task_co_pics, task_attachments

**④ Quản lý chất lượng:** sla_configs, approval_logs, kpi_scores

**⑤ Hệ thống & Log:** activity_logs, notifications, documents, document_versions

**II. Sơ đồ Quan hệ (ERD)**

Tổng hợp các quan hệ chính giữa các bảng trong hệ thống:

---

**Quan hệ** **Kiểu** **FK** **Ghi chú**

---

**users → departments** N:1 users.department_id → departments.id Mỗi user thuộc 1 phòng ban

**projects → users (creator)** N:1 projects.created_by → users.id CEO/Leader tạo dự án

**project_leaders (pivot)** M:N projects ↔ users Nhiều leader trên 1 project

**phases → projects** N:1 phases.project_id → projects.id Mỗi phase thuộc 1 project

**tasks → phases** N:1 tasks.phase_id → phases.id Mỗi task thuộc 1 phase

**tasks → users (PIC)** N:1 tasks.pic_id → users.id PIC chính duy nhất

**tasks → tasks (dep.)** N:1 tasks.dependency_task_id → tasks.id Self-reference: task phụ thuộc

**task_co_pics (pivot)** M:N tasks ↔ users PIC phối hợp, nhiều người

**task_attachments → tasks** N:1 task_attachments.task_id → tasks.id File đính kèm theo task

**sla_configs → departments** N:1 sla_configs.department_id → departments.id SLA theo phòng ban

**approval_logs → tasks** N:1 approval_logs.task_id → tasks.id Nhiều lần duyệt trả lại

**kpi_scores → users** N:1 kpi_scores.user_id → users.id 1 bản ghi/user/kỳ

**notifications → users** N:1 notifications.user_id → users.id Hàng đợi thông báo

**documents → projects/tasks** N:1 (poly) documents.(project/task)\_id Tài liệu gắn dự án hoặc task

**document_versions → documents** N:1 document_versions.document_id → documents.id Version history

**activity_logs → \*** polymorphic entity_type + entity_id Audit log toàn hệ thống

---

**III. Định nghĩa Chi tiết Các Bảng**

**1. users --- Người dùng hệ thống**

_Lưu thông tin tất cả nhân sự (CEO, Leader, PIC). Đồng bộ read-only từ HRM qua API._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK Auto-increment primary key

**employee_code** varchar(20) UNIQUE Mã nhân viên (VD: NV001)

**name** varchar(255) NOT NULL Họ và tên đầy đủ

**email** varchar(255) UNIQUE NOT NULL Email đăng nhập, duy nhất

**password** varchar(255) NOT NULL Bcrypt hash mật khẩu

**avatar** varchar(500) nullable URL ảnh đại diện

**phone** varchar(20) nullable Số điện thoại

**role** enum(\'ceo\',\'leader\',\'pic\') NOT NULL Vai trò hệ thống: ceo / leader / pic

**job_title** varchar(255) nullable Chức danh (VD: PM, DevOps Engineer)

**department_id** bigint UNSIGNED FK → departments Phòng ban trực thuộc

**status** enum(\'active\',\'on_leave\',\'resigned\') DEFAULT \'active\' Trạng thái: đang làm / nghỉ phép / nghỉ việc

**telegram_id** varchar(100) nullable Telegram chat ID để nhận thông báo bot

**email_verified_at** timestamp nullable Thời điểm xác thực email

**remember_token** varchar(100) nullable Laravel remember token

**created_at** timestamp nullable Thời điểm tạo bản ghi

**updated_at** timestamp nullable Thời điểm cập nhật cuối

---

**Indexes:**

> INDEX idx_users_department (department_id)
>
> INDEX idx_users_role (role)
>
> INDEX idx_users_status (status)

**2. departments --- Phòng ban**

_Danh mục phòng ban nội bộ, read-only từ HRM. Được dùng làm nguồn dropdown gán PIC/Leader._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK Auto-increment primary key

**code** varchar(20) UNIQUE NOT NULL Mã phòng ban (VD: IT, OPS, LOG)

**name** varchar(255) NOT NULL Tên phòng ban đầy đủ

**head_user_id** bigint UNSIGNED FK → users, nullable Trưởng phòng hiện tại

**status** enum(\'active\',\'inactive\') DEFAULT \'active\' Trạng thái hoạt động

**created_at** timestamp nullable

**updated_at** timestamp nullable

---

**Indexes:**

> INDEX idx_departments_status (status)

**3. projects --- Dự án**

_Thực thể trung tâm. Mỗi dự án có loại, ngân sách, trạng thái và tổng hợp % hoàn thành tự động._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**name** varchar(255) NOT NULL Tên dự án

**type** enum(\'warehouse\',\'customs\',\'trucking\',\'software\',\'gms\',\'tower\') NOT NULL Loại dự án --- dùng để sinh Phase mẫu

**status** enum(\'init\',\'running\',\'paused\',\'completed\',\'cancelled\') DEFAULT \'init\' Trạng thái vòng đời dự án

**budget** decimal(18,2) nullable Ngân sách dự kiến (VND)

**budget_spent** decimal(18,2) DEFAULT 0 Chi tiêu thực tế (nếu tích hợp module tài chính)

**objective** text nullable Mục tiêu tổng thể của dự án

**start_date** date nullable Ngày bắt đầu dự kiến

**end_date** date nullable Ngày kết thúc dự kiến

**progress** tinyint UNSIGNED DEFAULT 0 \% hoàn thành tổng (0-100), tính tự động từ Phase

**created_by** bigint UNSIGNED FK → users Người tạo dự án (CEO hoặc Leader)

**created_at** timestamp nullable

**updated_at** timestamp nullable

**deleted_at** timestamp nullable Soft delete

---

**Indexes:**

> INDEX idx_projects_status (status)
>
> INDEX idx_projects_type (type)
>
> INDEX idx_projects_created_by (created_by)

**4. project_leaders --- Pivot: Dự án ↔ Leader**

_Quan hệ nhiều-nhiều giữa projects và users (role=leader). Một dự án có thể có nhiều Leader._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**project_id** bigint UNSIGNED FK → projects

**user_id** bigint UNSIGNED FK → users Leader được gán

**assigned_at** timestamp DEFAULT CURRENT_TIMESTAMP Thời điểm gán

**assigned_by** bigint UNSIGNED FK → users Người thực hiện gán (CEO/Leader)

---

**Indexes:**

> UNIQUE KEY uq_project_leader (project_id, user_id)
>
> INDEX idx_pl_user (user_id)

**5. phases --- Giai đoạn dự án**

_Chia dự án thành các giai đoạn có trọng số. % Project = Σ(% Phase × weight). Hỗ trợ Gantt view._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**project_id** bigint UNSIGNED FK → projects Dự án chứa phase này

**name** varchar(255) NOT NULL Tên giai đoạn (VD: GĐ1: Phân tích yêu cầu)

**description** text nullable Mô tả chi tiết giai đoạn

**weight** decimal(5,2) NOT NULL Trọng số % (VD: 25.00). Tổng các phase = 100

**order_index** smallint UNSIGNED NOT NULL Thứ tự sắp xếp (kéo thả)

**start_date** date nullable Ngày bắt đầu giai đoạn

**end_date** date nullable Ngày kết thúc giai đoạn

**progress** tinyint UNSIGNED DEFAULT 0 \% hoàn thành phase, tính từ avg(task.progress)

**status** enum(\'pending\',\'active\',\'completed\') DEFAULT \'pending\' Trạng thái phase

**is_template** boolean DEFAULT false Phase mẫu (sinh tự động khi tạo project)

**created_at** timestamp nullable

**updated_at** timestamp nullable

---

**Indexes:**

> INDEX idx_phases_project (project_id)
>
> INDEX idx_phases_order (project_id, order_index)

**6. tasks --- Task công việc**

_Đơn vị công việc nhỏ nhất. Mỗi task có PIC chính, deadline, dependency, workflow phê duyệt riêng._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**phase_id** bigint UNSIGNED FK → phases Giai đoạn chứa task

**name** varchar(255) NOT NULL Tiêu đề task

**description** longtext nullable Mô tả chi tiết, yêu cầu đầu ra, tiêu chí hoàn thành (Rich text)

**type** enum(\'admin\',\'technical\',\'operation\',\'report\',\'other\') NOT NULL Loại task: Hành chính / Kỹ thuật / Vận hành / Báo cáo / Khác

**status** enum(\'pending\',\'in_progress\',\'waiting_approval\',\'completed\',\'late\') DEFAULT \'pending\' Trạng thái task theo state machine

**priority** enum(\'low\',\'medium\',\'high\',\'urgent\') DEFAULT \'medium\' Mức ưu tiên

**progress** tinyint UNSIGNED DEFAULT 0 \% tiến độ (0-100), PIC tự cập nhật

**pic_id** bigint UNSIGNED FK → users NOT NULL PIC chính --- duy nhất, chịu trách nhiệm chính

**dependency_task_id** bigint UNSIGNED FK → tasks, nullable Task phải hoàn thành trước khi task này được bắt đầu

**deadline** datetime NOT NULL Deadline. Trigger cảnh báo T-3 ngày và tự động chuyển Trễ

**started_at** timestamp nullable Thời điểm PIC bấm Bắt đầu (dùng tính SLA thực tế)

**completed_at** timestamp nullable Thời điểm task → Hoàn thành

**deliverable_url** varchar(1000) nullable Link Google Drive hoặc hệ thống lưu trữ giao phẩm

**issue_note** text nullable Ghi chú tồn tại, rủi ro, điểm cần chú ý

**recommendation** text nullable Kiến nghị cải tiến từ PIC hoặc Leader

**workflow_type** enum(\'single\',\'double\') DEFAULT \'single\' 1 cấp duyệt (Leader) hoặc 2 cấp (Leader + CEO)

**sla_standard_hours** decimal(6,2) nullable SLA giờ chuẩn snapshot tại thời điểm tạo task

**sla_met** boolean nullable Có đạt SLA không (tính khi task Hoàn thành)

**delay_days** decimal(6,2) DEFAULT 0 Số ngày vượt SLA thực tế

**created_by** bigint UNSIGNED FK → users Người tạo task (Leader)

**created_at** timestamp nullable

**updated_at** timestamp nullable

**deleted_at** timestamp nullable Soft delete

---

**Indexes:**

> INDEX idx_tasks_phase (phase_id)
>
> INDEX idx_tasks_pic (pic_id)
>
> INDEX idx_tasks_status (status)
>
> INDEX idx_tasks_deadline (deadline)
>
> INDEX idx_tasks_dependency (dependency_task_id)
>
> INDEX idx_tasks_priority (priority)

**7. task_co_pics --- Pivot: Task ↔ PIC phối hợp**

_Quan hệ nhiều-nhiều giữa task và users (PIC phối hợp). Tách riêng khỏi pic_id chính._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**task_id** bigint UNSIGNED FK → tasks

**user_id** bigint UNSIGNED FK → users PIC phối hợp

**assigned_at** timestamp DEFAULT CURRENT_TIMESTAMP

---

**Indexes:**

> UNIQUE KEY uq_task_co_pic (task_id, user_id)

**8. task_attachments --- File đính kèm task**

_Lưu metadata các file upload cho task. Tối đa 50MB/file. Hỗ trợ version control._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**task_id** bigint UNSIGNED FK → tasks

**uploader_id** bigint UNSIGNED FK → users Người upload file

**original_name** varchar(500) NOT NULL Tên file gốc

**stored_path** varchar(1000) NOT NULL Đường dẫn lưu trữ trên server hoặc cloud

**disk** varchar(50) DEFAULT \'local\' Disk lưu trữ: local / s3 / google

**mime_type** varchar(100) nullable MIME type (VD: application/pdf)

**size_bytes** bigint UNSIGNED nullable Kích thước file (byte), max 50MB = 52428800

**version** smallint UNSIGNED DEFAULT 1 Phiên bản file (version control)

**google_drive_id** varchar(255) nullable Google Drive File ID nếu sync qua API

**created_at** timestamp nullable

---

**Indexes:**

> INDEX idx_attachments_task (task_id)

**9. sla_configs --- Cấu hình SLA**

_Cấu hình giờ chuẩn SLA theo phòng ban × loại task × loại dự án. Kiểm soát nội bộ cập nhật định kỳ._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**department_id** bigint UNSIGNED FK → departments, nullable null = áp dụng toàn công ty

**task_type** enum(\'admin\',\'technical\',\'operation\',\'report\',\'other\',\'all\') DEFAULT \'all\' Loại task áp dụng

**project_type** enum(\'warehouse\',\'customs\',\'trucking\',\'software\',\'gms\',\'tower\',\'all\') DEFAULT \'all\' Loại dự án áp dụng

**standard_hours** decimal(6,2) NOT NULL Số giờ chuẩn hoàn thành task

**effective_date** date NOT NULL Ngày bắt đầu hiệu lực

**expired_date** date nullable Ngày hết hiệu lực (null = còn hiệu lực)

**note** text nullable Ghi chú về SLA config này

**created_by** bigint UNSIGNED FK → users Người tạo config (Kiểm soát nội bộ)

**created_at** timestamp nullable

**updated_at** timestamp nullable

---

**Indexes:**

> INDEX idx_sla_dept_type (department_id, task_type)
>
> INDEX idx_sla_effective (effective_date)

**10. approval_logs --- Lịch sử phê duyệt**

_Ghi lại toàn bộ hành động phê duyệt (approve/reject) theo từng cấp. Audit trail bắt buộc._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**task_id** bigint UNSIGNED FK → tasks

**reviewer_id** bigint UNSIGNED FK → users Người review (Leader hoặc CEO)

**approval_level** enum(\'leader\',\'ceo\') NOT NULL Cấp duyệt (phù hợp workflow_type)

**action** enum(\'submitted\',\'approved\',\'rejected\') NOT NULL Hành động: nộp / duyệt đạt / trả lại

**star_rating** tinyint UNSIGNED nullable Điểm sao chất lượng (1-5), Leader chấm khi duyệt Đạt

**comment** text nullable Lý do trả lại hoặc ghi chú khi duyệt

**created_at** timestamp nullable Thời điểm hành động

---

**Indexes:**

> INDEX idx_approval_task (task_id)
>
> INDEX idx_approval_reviewer (reviewer_id)
>
> INDEX idx_approval_action (action)

**11. activity_logs --- Audit Trail toàn hệ thống**

_Log mọi thay đổi: ai làm gì, khi nào, trên entity nào. Không xóa, không sửa. Dùng cho kiểm soát nội bộ._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**user_id** bigint UNSIGNED FK → users, nullable Người thực hiện (null = system/cron)

**entity_type** varchar(100) NOT NULL Loại entity: App\\Models\\Task, Project, Phase\...

**entity_id** bigint UNSIGNED NOT NULL ID của bản ghi bị thay đổi

**action** varchar(100) NOT NULL Hành động: created, updated, status_changed, approved\...

**old_values** json nullable Giá trị cũ (JSON)

**new_values** json nullable Giá trị mới (JSON)

**ip_address** varchar(45) nullable IP người dùng

**user_agent** text nullable User-agent trình duyệt

**created_at** timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP

---

**Indexes:**

> INDEX idx_actlog_entity (entity_type, entity_id)
>
> INDEX idx_actlog_user (user_id)
>
> INDEX idx_actlog_created (created_at)

**12. kpi_scores --- Điểm KPI theo kỳ**

_Lưu điểm KPI tổng hợp theo tháng/quý. Tính theo BR-002: (on_time×0.4) + (sla×0.4) + (star×0.2)._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**user_id** bigint UNSIGNED FK → users Nhân sự được tính KPI

**period_type** enum(\'monthly\',\'quarterly\') NOT NULL Kỳ tính KPI

**period_year** smallint UNSIGNED NOT NULL Năm (VD: 2025)

**period_value** tinyint UNSIGNED NOT NULL Tháng (1-12) hoặc Quý (1-4)

**total_tasks** smallint UNSIGNED DEFAULT 0 Tổng số task hoàn thành trong kỳ

**on_time_tasks** smallint UNSIGNED DEFAULT 0 Số task hoàn thành đúng hạn

**on_time_rate** decimal(5,2) DEFAULT 0 \% đúng hạn = on_time_tasks / total_tasks × 100

**sla_met_tasks** smallint UNSIGNED DEFAULT 0 Số task đạt SLA

**sla_rate** decimal(5,2) DEFAULT 0 \% SLA đạt = sla_met_tasks / total_tasks × 100

**avg_star** decimal(3,2) DEFAULT 0 Điểm sao trung bình (1.00 - 5.00)

**final_score** decimal(5,2) DEFAULT 0 Điểm tổng BR-002: (on_time×0.4)+(sla×0.4)+(star×0.2)

**calculated_at** timestamp DEFAULT CURRENT_TIMESTAMP Thời điểm tính gần nhất

**created_at** timestamp nullable

**updated_at** timestamp nullable

---

**Indexes:**

> UNIQUE KEY uq_kpi_user_period (user_id, period_type, period_year, period_value)
>
> INDEX idx_kpi_score (final_score)

**13. notifications --- Thông báo hệ thống**

_Hàng đợi thông báo đa kênh (Telegram, Email). Lưu trạng thái gửi để retry khi thất bại._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**user_id** bigint UNSIGNED FK → users Người nhận thông báo

**type** varchar(100) NOT NULL VD: deadline_reminder, task_late, approval_result, weekly_report

**channel** enum(\'telegram\',\'email\',\'both\') DEFAULT \'both\' Kênh gửi

**title** varchar(500) NOT NULL Tiêu đề thông báo

**body** text NOT NULL Nội dung chi tiết

**notifiable_type** varchar(100) nullable Polymorphic: App\\Models\\Task, Project\...

**notifiable_id** bigint UNSIGNED nullable ID của entity liên quan

**status** enum(\'pending\',\'sent\',\'failed\') DEFAULT \'pending\' Trạng thái gửi

**sent_at** timestamp nullable Thời điểm gửi thành công

**error_message** text nullable Thông báo lỗi nếu gửi thất bại

**retry_count** tinyint UNSIGNED DEFAULT 0 Số lần đã retry

**scheduled_at** timestamp nullable Thời điểm lên lịch gửi (cron job)

**created_at** timestamp nullable

---

**Indexes:**

> INDEX idx_notif_user_status (user_id, status)
>
> INDEX idx_notif_scheduled (scheduled_at)
>
> INDEX idx_notif_type (type)

**14. documents --- Tài liệu & Giao phẩm**

_Trung tâm lưu trữ tài liệu dự án. Hỗ trợ version control, audit trail, tích hợp Google Drive._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**project_id** bigint UNSIGNED FK → projects, nullable Dự án liên quan

**task_id** bigint UNSIGNED FK → tasks, nullable Task liên quan (nếu là giao phẩm task)

**uploader_id** bigint UNSIGNED FK → users Người upload

**name** varchar(500) NOT NULL Tên tài liệu

**document_type** enum(\'sop\',\'form\',\'quote\',\'contract\',\'technical\',\'deliverable\',\'other\') NOT NULL Loại tài liệu

**description** text nullable Mô tả nội dung tài liệu

**google_drive_id** varchar(255) nullable Google Drive File ID (sync tự động)

**google_drive_url** varchar(1000) nullable URL xem/download trực tiếp

**current_version** smallint UNSIGNED DEFAULT 1 Phiên bản hiện tại

**permission** enum(\'view\',\'edit\',\'share\') DEFAULT \'view\' Quyền truy cập mặc định

**created_at** timestamp nullable

**updated_at** timestamp nullable

**deleted_at** timestamp nullable Soft delete

---

**Indexes:**

> INDEX idx_docs_project (project_id)
>
> INDEX idx_docs_task (task_id)
>
> INDEX idx_docs_type (document_type)

**15. document_versions --- Lịch sử phiên bản tài liệu**

_Version control cho từng tài liệu. Cho phép so sánh v1 vs v2, rollback về phiên bản cũ._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**document_id** bigint UNSIGNED FK → documents

**version_number** smallint UNSIGNED NOT NULL Số thứ tự phiên bản (1, 2, 3\...)

**uploader_id** bigint UNSIGNED FK → users Người upload phiên bản này

**stored_path** varchar(1000) NOT NULL Đường dẫn lưu trữ phiên bản này

**google_drive_revision_id** varchar(255) nullable Google Drive Revision ID

**change_summary** text nullable Tóm tắt thay đổi so với phiên bản trước

**file_size_bytes** bigint UNSIGNED nullable Kích thước file (byte)

**created_at** timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP Thời điểm upload phiên bản

---

**Indexes:**

> UNIQUE KEY uq_doc_version (document_id, version_number)

**16. phase_templates --- Phase mẫu theo loại dự án**

_Cấu hình phase mẫu mặc định. Khi tạo project mới, hệ thống tự động sinh phases từ template theo project_type._

---

**Column** **Type** **Constraint** **Ghi chú**

---

**id** bigint UNSIGNED PK

**project_type** enum(\'warehouse\',\'customs\',\'trucking\',\'software\',\'gms\',\'tower\') NOT NULL Loại dự án

**phase_name** varchar(255) NOT NULL Tên phase mẫu

**phase_description** text nullable Mô tả phase mẫu

**order_index** smallint UNSIGNED NOT NULL Thứ tự trong dự án

**default_weight** decimal(5,2) NOT NULL Trọng số gợi ý (%)

**default_duration_days** smallint UNSIGNED nullable Số ngày ước tính cho phase

**is_active** boolean DEFAULT true Đang kích hoạt

---

**Indexes:**

> INDEX idx_tmpl_type (project_type, order_index)

**IV. Business Rules & Logic Tính toán**

Các rule nghiệp vụ cần implement ở Application Layer (Service / Observer / Job):

---

**Mã** **Module** **Mô tả**

---

**BR-001** **Tạo dự án** Chỉ CEO / Leader có quyền tạo project. Hệ thống tự động sinh phases từ phase_templates theo project.type khi tạo mới.

**BR-002** **Công thức KPI** final_score = (on_time_rate × 0.4) + (sla_rate × 0.4) + (avg_star / 5 × 100 × 0.2). Cập nhật realtime sau mỗi task hoàn thành.

**BR-003** **Dependency lock** tasks.status = \'pending\' bị lock nút Bắt đầu nếu dependency_task.status ≠ \'completed\'. Leader mở khóa thủ công khi task phụ thuộc bị hủy.

**BR-004** **Tự động Trễ** Cron job mỗi 5 phút: UPDATE tasks SET status=\'late\' WHERE deadline \< NOW() AND status NOT IN (\'completed\',\'late\').

**BR-005** **SLA snapshot** Khi tạo task, hệ thống lookup sla_configs theo (department_id, task_type, project_type, effective_date) và snapshot vào tasks.sla_standard_hours.

**BR-006** **Cảnh báo Overload** Khi Leader gán PIC: đếm task của PIC có deadline trong ±1 ngày. Nếu \> 3 task → warning. Ghi vào notifications + hiển thị popup.

**BR-007** **SLA tính sau complete** Khi task → \'completed\': sla_met = (completed_at - started_at) \<= sla_standard_hours × 3600 seconds. delay_days = max(0, (completed_at - deadline) / 86400).

**BR-008** **Trọng số Phase** Tổng weight của tất cả phases trong 1 project phải = 100. Validate ở application layer trước khi save.

**BR-009** **% Project** projects.progress = SUM(phase.progress × phase.weight / 100). Trigger update sau mỗi lần task.progress thay đổi.

**BR-010** **Workflow 2 cấp** workflow_type=\'double\': phải có 2 approval_logs: level=\'leader\' (approved) + level=\'ceo\' (approved) mới → \'completed\'.

---

**V. Laravel Migrations**

Thứ tự chạy migration quan trọng do ràng buộc FK. Chạy theo thứ tự file dưới đây:

**Migration: departments + users**

> Schema::create(\'departments\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>string(\'code\', 20)-\>unique();
>
> \$table-\>string(\'name\');
>
> \$table-\>foreignId(\'head_user_id\')-\>nullable()-\>constrained(\'users\')-\>nullOnDelete();
>
> \$table-\>enum(\'status\', \[\'active\',\'inactive\'\])-\>default(\'active\');
>
> \$table-\>timestamps();
>
> });
>
> Schema::create(\'users\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>string(\'employee_code\', 20)-\>unique()-\>nullable();
>
> \$table-\>string(\'name\');
>
> \$table-\>string(\'email\')-\>unique();
>
> \$table-\>string(\'password\');
>
> \$table-\>string(\'avatar\', 500)-\>nullable();
>
> \$table-\>string(\'phone\', 20)-\>nullable();
>
> \$table-\>enum(\'role\', \[\'ceo\',\'leader\',\'pic\'\]);
>
> \$table-\>string(\'job_title\')-\>nullable();
>
> \$table-\>foreignId(\'department_id\')-\>nullable()-\>constrained()-\>nullOnDelete();
>
> \$table-\>enum(\'status\', \[\'active\',\'on_leave\',\'resigned\'\])-\>default(\'active\');
>
> \$table-\>string(\'telegram_id\', 100)-\>nullable();
>
> \$table-\>timestamp(\'email_verified_at\')-\>nullable();
>
> \$table-\>rememberToken();
>
> \$table-\>timestamps();
>
> \$table-\>index(\[\'department_id\', \'role\', \'status\'\]);
>
> });

**Migration: projects + project_leaders**

> Schema::create(\'projects\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>string(\'name\');
>
> \$table-\>enum(\'type\', \[\'warehouse\',\'customs\',\'trucking\',\'software\',\'gms\',\'tower\'\]);
>
> \$table-\>enum(\'status\', \[\'init\',\'running\',\'paused\',\'completed\',\'cancelled\'\])-\>default(\'init\');
>
> \$table-\>decimal(\'budget\', 18, 2)-\>nullable();
>
> \$table-\>decimal(\'budget_spent\', 18, 2)-\>default(0);
>
> \$table-\>text(\'objective\')-\>nullable();
>
> \$table-\>date(\'start_date\')-\>nullable();
>
> \$table-\>date(\'end_date\')-\>nullable();
>
> \$table-\>unsignedTinyInteger(\'progress\')-\>default(0);
>
> \$table-\>foreignId(\'created_by\')-\>constrained(\'users\');
>
> \$table-\>timestamps();
>
> \$table-\>softDeletes();
>
> \$table-\>index(\[\'status\', \'type\'\]);
>
> });
>
> Schema::create(\'project_leaders\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'project_id\')-\>constrained()-\>cascadeOnDelete();
>
> \$table-\>foreignId(\'user_id\')-\>constrained()-\>cascadeOnDelete();
>
> \$table-\>foreignId(\'assigned_by\')-\>constrained(\'users\');
>
> \$table-\>timestamp(\'assigned_at\')-\>useCurrent();
>
> \$table-\>unique(\[\'project_id\', \'user_id\'\]);
>
> });

**Migration: phases + phase_templates**

> Schema::create(\'phase_templates\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>enum(\'project_type\', \[\'warehouse\',\'customs\',\'trucking\',\'software\',\'gms\',\'tower\'\]);
>
> \$table-\>string(\'phase_name\');
>
> \$table-\>text(\'phase_description\')-\>nullable();
>
> \$table-\>unsignedSmallInteger(\'order_index\');
>
> \$table-\>decimal(\'default_weight\', 5, 2);
>
> \$table-\>unsignedSmallInteger(\'default_duration_days\')-\>nullable();
>
> \$table-\>boolean(\'is_active\')-\>default(true);
>
> \$table-\>index(\[\'project_type\', \'order_index\'\]);
>
> });
>
> Schema::create(\'phases\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'project_id\')-\>constrained()-\>cascadeOnDelete();
>
> \$table-\>string(\'name\');
>
> \$table-\>text(\'description\')-\>nullable();
>
> \$table-\>decimal(\'weight\', 5, 2);
>
> \$table-\>unsignedSmallInteger(\'order_index\');
>
> \$table-\>date(\'start_date\')-\>nullable();
>
> \$table-\>date(\'end_date\')-\>nullable();
>
> \$table-\>unsignedTinyInteger(\'progress\')-\>default(0);
>
> \$table-\>enum(\'status\', \[\'pending\',\'active\',\'completed\'\])-\>default(\'pending\');
>
> \$table-\>boolean(\'is_template\')-\>default(false);
>
> \$table-\>timestamps();
>
> \$table-\>index(\[\'project_id\', \'order_index\'\]);
>
> });

**Migration: tasks + task_co_pics + task_attachments**

> Schema::create(\'tasks\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'phase_id\')-\>constrained()-\>cascadeOnDelete();
>
> \$table-\>string(\'name\');
>
> \$table-\>longText(\'description\')-\>nullable();
>
> \$table-\>enum(\'type\', \[\'admin\',\'technical\',\'operation\',\'report\',\'other\'\]);
>
> \$table-\>enum(\'status\', \[\'pending\',\'in_progress\',\'waiting_approval\',
>
> \'completed\',\'late\'\])-\>default(\'pending\');
>
> \$table-\>enum(\'priority\', \[\'low\',\'medium\',\'high\',\'urgent\'\])-\>default(\'medium\');
>
> \$table-\>unsignedTinyInteger(\'progress\')-\>default(0);
>
> \$table-\>foreignId(\'pic_id\')-\>constrained(\'users\');
>
> \$table-\>foreignId(\'dependency_task_id\')-\>nullable()-\>constrained(\'tasks\')-\>nullOnDelete();
>
> \$table-\>dateTime(\'deadline\');
>
> \$table-\>timestamp(\'started_at\')-\>nullable();
>
> \$table-\>timestamp(\'completed_at\')-\>nullable();
>
> \$table-\>string(\'deliverable_url\', 1000)-\>nullable();
>
> \$table-\>text(\'issue_note\')-\>nullable();
>
> \$table-\>text(\'recommendation\')-\>nullable();
>
> \$table-\>enum(\'workflow_type\', \[\'single\',\'double\'\])-\>default(\'single\');
>
> \$table-\>decimal(\'sla_standard_hours\', 6, 2)-\>nullable();
>
> \$table-\>boolean(\'sla_met\')-\>nullable();
>
> \$table-\>decimal(\'delay_days\', 6, 2)-\>default(0);
>
> \$table-\>foreignId(\'created_by\')-\>constrained(\'users\');
>
> \$table-\>timestamps();
>
> \$table-\>softDeletes();
>
> \$table-\>index(\[\'pic_id\', \'status\'\]);
>
> \$table-\>index(\[\'deadline\', \'status\'\]);
>
> \$table-\>index(\'phase_id\');
>
> });
>
> Schema::create(\'task_co_pics\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'task_id\')-\>constrained()-\>cascadeOnDelete();
>
> \$table-\>foreignId(\'user_id\')-\>constrained()-\>cascadeOnDelete();
>
> \$table-\>timestamp(\'assigned_at\')-\>useCurrent();
>
> \$table-\>unique(\[\'task_id\', \'user_id\'\]);
>
> });
>
> Schema::create(\'task_attachments\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'task_id\')-\>constrained()-\>cascadeOnDelete();
>
> \$table-\>foreignId(\'uploader_id\')-\>constrained(\'users\');
>
> \$table-\>string(\'original_name\', 500);
>
> \$table-\>string(\'stored_path\', 1000);
>
> \$table-\>string(\'disk\', 50)-\>default(\'local\');
>
> \$table-\>string(\'mime_type\', 100)-\>nullable();
>
> \$table-\>unsignedBigInteger(\'size_bytes\')-\>nullable();
>
> \$table-\>unsignedSmallInteger(\'version\')-\>default(1);
>
> \$table-\>string(\'google_drive_id\', 255)-\>nullable();
>
> \$table-\>timestamp(\'created_at\')-\>useCurrent();
>
> \$table-\>index(\'task_id\');
>
> });

**Migration: sla_configs + approval_logs + kpi_scores**

> Schema::create(\'sla_configs\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'department_id\')-\>nullable()-\>constrained()-\>nullOnDelete();
>
> \$table-\>enum(\'task_type\', \[\'admin\',\'technical\',\'operation\',\'report\',\'other\',\'all\'\])-\>default(\'all\');
>
> \$table-\>enum(\'project_type\', \[\'warehouse\',\'customs\',\'trucking\',\'software\',\'gms\',\'tower\',\'all\'\])-\>default(\'all\');
>
> \$table-\>decimal(\'standard_hours\', 6, 2);
>
> \$table-\>date(\'effective_date\');
>
> \$table-\>date(\'expired_date\')-\>nullable();
>
> \$table-\>text(\'note\')-\>nullable();
>
> \$table-\>foreignId(\'created_by\')-\>constrained(\'users\');
>
> \$table-\>timestamps();
>
> \$table-\>index(\[\'department_id\', \'task_type\', \'effective_date\'\]);
>
> });
>
> Schema::create(\'approval_logs\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'task_id\')-\>constrained()-\>cascadeOnDelete();
>
> \$table-\>foreignId(\'reviewer_id\')-\>constrained(\'users\');
>
> \$table-\>enum(\'approval_level\', \[\'leader\',\'ceo\'\]);
>
> \$table-\>enum(\'action\', \[\'submitted\',\'approved\',\'rejected\'\]);
>
> \$table-\>unsignedTinyInteger(\'star_rating\')-\>nullable();
>
> \$table-\>text(\'comment\')-\>nullable();
>
> \$table-\>timestamp(\'created_at\')-\>useCurrent();
>
> \$table-\>index(\[\'task_id\', \'action\'\]);
>
> \$table-\>index(\'reviewer_id\');
>
> });
>
> Schema::create(\'kpi_scores\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'user_id\')-\>constrained()-\>cascadeOnDelete();
>
> \$table-\>enum(\'period_type\', \[\'monthly\',\'quarterly\'\]);
>
> \$table-\>unsignedSmallInteger(\'period_year\');
>
> \$table-\>unsignedTinyInteger(\'period_value\'); // month 1-12 or quarter 1-4
>
> \$table-\>unsignedSmallInteger(\'total_tasks\')-\>default(0);
>
> \$table-\>unsignedSmallInteger(\'on_time_tasks\')-\>default(0);
>
> \$table-\>decimal(\'on_time_rate\', 5, 2)-\>default(0);
>
> \$table-\>unsignedSmallInteger(\'sla_met_tasks\')-\>default(0);
>
> \$table-\>decimal(\'sla_rate\', 5, 2)-\>default(0);
>
> \$table-\>decimal(\'avg_star\', 3, 2)-\>default(0);
>
> \$table-\>decimal(\'final_score\', 5, 2)-\>default(0); // BR-002
>
> \$table-\>timestamp(\'calculated_at\')-\>useCurrent();
>
> \$table-\>timestamps();
>
> \$table-\>unique(\[\'user_id\', \'period_type\', \'period_year\', \'period_value\'\]);
>
> });

**Migration: activity_logs + notifications + documents**

> Schema::create(\'activity_logs\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'user_id\')-\>nullable()-\>constrained()-\>nullOnDelete();
>
> \$table-\>string(\'entity_type\', 100);
>
> \$table-\>unsignedBigInteger(\'entity_id\');
>
> \$table-\>string(\'action\', 100);
>
> \$table-\>json(\'old_values\')-\>nullable();
>
> \$table-\>json(\'new_values\')-\>nullable();
>
> \$table-\>string(\'ip_address\', 45)-\>nullable();
>
> \$table-\>text(\'user_agent\')-\>nullable();
>
> \$table-\>timestamp(\'created_at\')-\>useCurrent();
>
> \$table-\>index(\[\'entity_type\', \'entity_id\'\]);
>
> \$table-\>index(\[\'user_id\', \'created_at\'\]);
>
> });
>
> Schema::create(\'notifications\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'user_id\')-\>constrained()-\>cascadeOnDelete();
>
> \$table-\>string(\'type\', 100);
>
> \$table-\>enum(\'channel\', \[\'telegram\',\'email\',\'both\'\])-\>default(\'both\');
>
> \$table-\>string(\'title\', 500);
>
> \$table-\>text(\'body\');
>
> \$table-\>string(\'notifiable_type\', 100)-\>nullable();
>
> \$table-\>unsignedBigInteger(\'notifiable_id\')-\>nullable();
>
> \$table-\>enum(\'status\', \[\'pending\',\'sent\',\'failed\'\])-\>default(\'pending\');
>
> \$table-\>timestamp(\'sent_at\')-\>nullable();
>
> \$table-\>text(\'error_message\')-\>nullable();
>
> \$table-\>unsignedTinyInteger(\'retry_count\')-\>default(0);
>
> \$table-\>timestamp(\'scheduled_at\')-\>nullable();
>
> \$table-\>timestamp(\'created_at\')-\>useCurrent();
>
> \$table-\>index(\[\'user_id\', \'status\'\]);
>
> \$table-\>index(\'scheduled_at\');
>
> });
>
> Schema::create(\'documents\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'project_id\')-\>nullable()-\>constrained()-\>nullOnDelete();
>
> \$table-\>foreignId(\'task_id\')-\>nullable()-\>constrained()-\>nullOnDelete();
>
> \$table-\>foreignId(\'uploader_id\')-\>constrained(\'users\');
>
> \$table-\>string(\'name\', 500);
>
> \$table-\>enum(\'document_type\',\[\'sop\',\'form\',\'quote\',\'contract\',\'technical\',\'deliverable\',\'other\'\]);
>
> \$table-\>text(\'description\')-\>nullable();
>
> \$table-\>string(\'google_drive_id\', 255)-\>nullable();
>
> \$table-\>string(\'google_drive_url\', 1000)-\>nullable();
>
> \$table-\>unsignedSmallInteger(\'current_version\')-\>default(1);
>
> \$table-\>enum(\'permission\', \[\'view\',\'edit\',\'share\'\])-\>default(\'view\');
>
> \$table-\>timestamps();
>
> \$table-\>softDeletes();
>
> });
>
> Schema::create(\'document_versions\', function (Blueprint \$table) {
>
> \$table-\>id();
>
> \$table-\>foreignId(\'document_id\')-\>constrained()-\>cascadeOnDelete();
>
> \$table-\>unsignedSmallInteger(\'version_number\');
>
> \$table-\>foreignId(\'uploader_id\')-\>constrained(\'users\');
>
> \$table-\>string(\'stored_path\', 1000);
>
> \$table-\>string(\'google_drive_revision_id\', 255)-\>nullable();
>
> \$table-\>text(\'change_summary\')-\>nullable();
>
> \$table-\>unsignedBigInteger(\'file_size_bytes\')-\>nullable();
>
> \$table-\>timestamp(\'created_at\')-\>useCurrent();
>
> \$table-\>unique(\[\'document_id\', \'version_number\'\]);
>
> });

**VI. Tổng hợp Enum Values**

---

**Column** **Enum Values**

---

**users.role** ceo \| leader \| pic

**users.status** active \| on_leave \| resigned

**projects.type** warehouse \| customs \| trucking \| software \| gms \| tower

**projects.status** init \| running \| paused \| completed \| cancelled

**phases.status** pending \| active \| completed

**tasks.type** admin \| technical \| operation \| report \| other

**tasks.status** pending \| in_progress \| waiting_approval \| completed \| late

**tasks.priority** low \| medium \| high \| urgent

**tasks.workflow_type** single \| double

**approval_logs.action** submitted \| approved \| rejected

**approval_logs.approval_level** leader \| ceo

**kpi_scores.period_type** monthly \| quarterly

**notifications.channel** telegram \| email \| both

**notifications.status** pending \| sent \| failed

**documents.document_type** sop \| form \| quote \| contract \| technical \| deliverable \| other

**sla_configs.task_type** admin \| technical \| operation \| report \| other \| all

---

_TaskXPro Database Design v1.0 · Laravel + MySQL 8.0 · Internal Use Only_
