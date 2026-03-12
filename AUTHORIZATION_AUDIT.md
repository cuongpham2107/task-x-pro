# Báo cáo Đánh giá và Khắc phục Hệ thống Phân quyền

## 1. Tổng quan
Báo cáo này tóm tắt quá trình rà soát, phát hiện và khắc phục các vấn đề liên quan đến phân quyền (Authorization) trong hệ thống TaskXPro. Mục tiêu là đảm bảo tính bảo mật, chính xác trong việc kiểm soát truy cập giữa các vai trò (CEO, Leader, PIC, Super Admin).

## 2. Các vấn đề phát hiện được

### 2.1. Lỗi hiển thị Menu Header
*   **Vấn đề**: Tài khoản `super_admin` không thấy menu "Mẫu phase" và "Nhật ký hoạt động" mặc dù có toàn quyền.
*   **Nguyên nhân**: Logic trong `header.blade.php` kiểm tra role `ceo` một cách cứng nhắc (`hasRole('ceo')`) hoặc kiểm tra permission không chính xác (`project.update` cho activity log), dẫn đến việc loại trừ `super_admin` hoặc bao gồm sai `leader`.
*   **Khắc phục**: Chuyển sang sử dụng `can('viewAny', Model::class)` kết hợp với việc cập nhật Policy để tận dụng cơ chế `Gate::before` của `super_admin`.

### 2.2. Leader xem được danh sách User và tạo User
*   **Vấn đề**: Vai trò Leader (không có quyền quản lý user) vẫn có thể truy cập trang danh sách User và nút tạo User.
*   **Nguyên nhân**: `UserPolicy` sử dụng các permission của Project (`project.view`, `project.create`) để kiểm tra quyền truy cập User. Do Leader có quyền quản lý Project, họ vô tình có quyền quản lý User.
*   **Khắc phục**:
    *   Tách biệt permission: Tạo mới các permission `user.view`, `user.create`, `user.update`, `user.delete`.
    *   Cập nhật `UserPolicy` để sử dụng các permission mới này.
    *   Cập nhật Seeder để chỉ gán các permission này cho `ceo` (và `super_admin` mặc định).

### 2.3. Policy kiểm tra quyền không chính xác
*   **Vấn đề**: `ActivityLogPolicy` và `PhaseTemplatePolicy` sử dụng lại permission của module khác (`project.view`, `phase.create`), gây ra sự chồng chéo quyền hạn không mong muốn.
*   **Khắc phục**:
    *   Tạo permission riêng biệt: `activity_log.view`, `phase_template.*`.
    *   Cập nhật Policy để kiểm tra đúng permission tương ứng.

## 3. Chi tiết thay đổi

### 3.1. Database Seeder (`TaskXProSeeder.php`)
Đã thêm các permission mới vào danh sách quyền của `ceo`:
*   `user.view`, `user.create`, `user.update`, `user.delete`
*   `department.view`, `department.create`, `department.update`, `department.delete`
*   `phase_template.view`, `phase_template.create`, `phase_template.update`, `phase_template.delete`
*   `activity_log.view`

### 3.2. Policies
Đã cập nhật các file Policy sau để sử dụng permission chuẩn xác:
*   `UserPolicy.php`: Chuyển từ `project.*` sang `user.*`.
*   `PhaseTemplatePolicy.php`: Chuyển từ `phase.*` sang `phase_template.*`.
*   `ActivityLogPolicy.php`: Chuyển từ `project.view` sang `activity_log.view`.

### 3.3. Views (`header.blade.php`)
*   Cập nhật logic kiểm tra hiển thị menu hệ thống (System Config).
*   Sử dụng `@can` hoặc `$user->can()` thay vì check role cứng, đảm bảo tính linh hoạt và hỗ trợ `super_admin`.

## 4. Kết quả kiểm tra
*   **Super Admin**: Đã thấy đầy đủ các menu hệ thống.
*   **CEO**: Thấy đầy đủ các menu và quyền quản lý User, System Config.
*   **Leader**:
    *   Không còn thấy menu "Mẫu phase" và "Nhật ký".
    *   Không còn thấy menu "Người dùng" (Users).
    *   Vẫn giữ quyền quản lý Project và Task như cũ.
*   **PIC**: Chỉ thấy các menu được phép (Dashboard, Dự án, Tài liệu - view only).

## 5. Khuyến nghị
*   Chạy lại seeder để áp dụng các permission mới vào database: `php artisan db:seed --class=TaskXProSeeder`.
*   Tiếp tục rà soát các view chi tiết (nếu có) để đảm bảo không còn nút bấm/link nào bị lộ cho role không được phép.
