**HỆ THỐNG QUẢN LÝ DỰ ÁN NỘI BỘ**

**TaskXPro**

*TÀI LIỆU ĐẶC TẢ YÊU CẦU PHẦN MỀM*

*Software Requirements Specification (SRS)*

  -----------------------------------------------------------------------
  **Phiên bản**               v1.0 -- Draft for Production
  --------------------------- -------------------------------------------
  **Mục đích**                Chuyển giao Bộ phận Sản xuất / Freelancer

  **Phân loại**               Internal -- Nội bộ Hạn chế
  -----------------------------------------------------------------------

  -----------------------------------------------------------------------
  **I. TỔNG QUAN DỰ ÁN**
  -----------------------------------------------------------------------

  -----------------------------------------------------------------------

## 1.1 Mục tiêu hệ thống

TaskXPro là nền tảng quản lý dự án nội bộ được thiết kế cho doanh nghiệp
đa lĩnh vực (Logistics, Phần mềm, R&D). Hệ thống chuẩn hóa toàn bộ vòng
đời dự án từ khởi tạo, phân rã giai đoạn, quản lý task chuyên sâu đến
đánh giá KPI/SLA realtime.

+-----------------------------------+-----------------------------------+
| **Mục tiêu cốt lõi**              | **Ngoài phạm vi (Out of Scope)**  |
|                                   |                                   |
| -   Chuẩn hóa quy trình quản lý   | -   Quản lý lương chi tiết        |
|     dự án                         |                                   |
|                                   | -   CRM quản lý quan hệ khách     |
| -   Kiểm soát tiến độ -- chất     |     hàng bên ngoài                |
|     lượng -- chi phí -- SLA       |                                   |
|                                   |                                   |
| -   Hỗ trợ điều hành đa dự án     |                                   |
|     song song                     |                                   |
|                                   |                                   |
| -   Liên kết KPI -- đánh giá --   |                                   |
|     thưởng phạt nhân sự           |                                   |
+===================================+===================================+
+-----------------------------------+-----------------------------------+

## 1.2 Stakeholders

  -----------------------------------------------------------------------
  **Vai trò**     **Đại diện**      **Trách nhiệm**
  --------------- ----------------- -------------------------------------
  CEO             Ban lãnh đạo      Theo dõi Dashboard tổng thể, điều
                                    chỉnh nguồn lực, quyết định cao nhất

  Leader          Trưởng nhóm/PM    Quản lý dự án, phê duyệt kết quả
                                    task, đánh giá nhân sự

  PIC             Nhân viên thực    Thực hiện task, cập nhật tiến độ,
                  hiện              upload giao phẩm

  Kiểm soát nội   Compliance        Giám sát tuân thủ quy trình và chỉ số
  bộ                                SLA
  -----------------------------------------------------------------------

  -----------------------------------------------------------------------
  **II. MÔ TẢ CHI TIẾT CÁC MODULE**
  -----------------------------------------------------------------------

  -----------------------------------------------------------------------

## MODULE 1 -- Quản lý Dự án

  -----------------------------------------------------------------------
  **Mục      Tạo và quản lý toàn bộ vòng đời dự án, là entry point cho
  đích**     mọi hoạt động trong hệ thống.
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

### Tính năng chính

  -----------------------------------------------------------------------
  **Tính năng**          **Mô tả chi tiết**
  ---------------------- ------------------------------------------------
  Tạo dự án mới          Form nhập: Tên, Loại, Ngân sách (tùy chọn),
                         Leader, Ngày bắt đầu, Mục tiêu tổng thể

  Phân loại dự án        Enum: Kho / Hải quan / Trucking / GMS / Tháp
                         điều hành / Phần mềm

  Gán Leader             Chọn từ danh sách nhân sự, có thể gán nhiều
                         Leader

  Theo dõi % hoàn thành  Tự động tổng hợp từ % Phase → Task, hiển thị
                         progress bar

  Thiết lập ngân sách    Nhập ngân sách dự kiến, theo dõi chi tiêu (nếu
                         có module tài chính)

  Trạng thái dự án       Enum: Khởi tạo / Đang chạy / Tạm dừng / Hoàn
                         thành / Hủy
  -----------------------------------------------------------------------

### Business Rule

  -------------------------------------------------------------------------
  **BR-001**   Chỉ CEO / Leader có quyền tạo dự án. Hệ thống tự động khởi
               tạo cấu trúc Phase mẫu theo loại dự án ngay khi tạo.
  ------------ ------------------------------------------------------------

  -------------------------------------------------------------------------

## MODULE 2 -- Phân rã Giai đoạn (Phase)

  -----------------------------------------------------------------------
  **Mục      Chia dự án thành các giai đoạn có trọng số, timeline riêng.
  đích**     % hoàn thành dự án = tổng hợp từ trọng số các Phase.
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

### Tính năng chính

  -----------------------------------------------------------------------
  **Tính năng**          **Mô tả chi tiết**
  ---------------------- ------------------------------------------------
  Tạo/Sắp xếp Phase      Thêm, xóa, kéo thả sắp xếp thứ tự Phase trong dự
                         án

  Thiết lập timeline     Ngày bắt đầu -- kết thúc từng Phase, hiển thị
                         Gantt view

  Trọng số hoàn thành    Mỗi Phase có weight (%), tổng = 100%. VD:
                         GĐ1=25%, GĐ2=25%, GĐ3=30%, GĐ4=20%

  Phase mẫu theo loại    Hệ thống gợi ý Phase mặc định theo project_type
                         (xem bảng bên dưới)

  Tính % theo trọng số   \% Phase = avg(% task trong Phase) × weight; %
                         Project = Σ(% Phase)
  -----------------------------------------------------------------------

### Phase mẫu theo Loại dự án

  -----------------------------------------------------------------------
  **Loại dự án**  **Các Phase mẫu (gợi ý mặc định)**
  --------------- -------------------------------------------------------
  Kho             GĐ1: SOP & SLA → GĐ2: Sale Kit → GĐ3: Triển khai vận
                  hành → GĐ4: Giám sát & cải tiến

  Phần mềm        GĐ1: Phân tích yêu cầu → GĐ2: Thiết kế UI/UX → GĐ3:
                  Phát triển → GĐ4: Test & UAT → GĐ5: Go-live

  Hải quan        GĐ1: Khảo sát quy trình → GĐ2: Chuẩn hóa hồ sơ → GĐ3:
                  Triển khai → GĐ4: Kiểm tra tuân thủ

  Trucking        GĐ1: Lập kế hoạch tuyến → GĐ2: Thiết lập SLA → GĐ3: Vận
                  hành thử → GĐ4: Tối ưu & báo cáo

  GMS / Tháp ĐH   GĐ1: Khởi động → GĐ2: Thiết lập hạ tầng → GĐ3: Vận hành
                  → GĐ4: Đánh giá
  -----------------------------------------------------------------------

## MODULE 3 -- Quản lý Task Chuyên sâu

  -----------------------------------------------------------------------
  **Mục      Đơn vị công việc nhỏ nhất trong hệ thống. Mỗi Task gắn PIC,
  đích**     deadline, dependency và workflow phê duyệt riêng.
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

### Cấu trúc dữ liệu Task

  ------------------------------------------------------------------------
  **Trường**         **Kiểu dữ liệu**    **Mô tả**
  ------------------ ------------------- ---------------------------------
  Tên task           String (255)        Tiêu đề ngắn, rõ ràng, dễ hiểu

  Mô tả chi tiết     Text/Rich text      Nội dung công việc, yêu cầu đầu
                                         ra, tiêu chí hoàn thành

  Loại task          Enum                Phân loại: Hành chính / Kỹ thuật
                                         / Vận hành / Báo cáo / Khác

  PIC chính          FK → User           Người chịu trách nhiệm chính, duy
                                         nhất

  PIC phối hợp       FK\[\] → User       Danh sách người hỗ trợ (nhiều
                                         người)

  Deadline           DateTime            Thời hạn hoàn thành. Trigger cảnh
                                         báo T-3 ngày

  Trạng thái         Enum (5)            Chưa bắt đầu / Đang thực hiện /
                                         Chờ duyệt / Hoàn thành / Trễ

  \% Tiến độ         Integer 0--100      PIC tự cập nhật, hiển thị
                                         progress bar

  Mức ưu tiên        Enum                Thấp / Trung bình / Cao / Khẩn
                                         cấp

  Task phụ thuộc     FK → Task           Dependency: Task A phải hoàn
                                         thành trước khi mở Task B

  Link giao phẩm     URL                 Link Google Drive hoặc hệ thống
                                         lưu trữ nội bộ

  File đính kèm      File\[\]            Upload file hỗ trợ, tối đa
                                         50MB/file

  Ghi chú tồn tại    Text                Vấn đề tồn đọng, rủi ro, điểm cần
                                         chú ý

  Kiến nghị          Text                Đề xuất cải tiến từ PIC hoặc
                                         Leader
  ------------------------------------------------------------------------

### State Machine -- Vòng đời Task

  -----------------------------------------------------------------------
  **Luồng    Chưa bắt đầu → (PIC Start) → Đang thực hiện → (PIC Upload) →
  chính**    Chờ duyệt → (Leader Duyệt) → Hoàn thành
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

  -----------------------------------------------------------------------
  **Trả      Chờ duyệt → (Leader từ chối) → Đang thực hiện \[PIC nhận
  lại**      thông báo Telegram\]
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

  -----------------------------------------------------------------------
  **Tự động  Nếu NOW() \> Deadline VÀ Status != Hoàn thành → Hệ thống tự
  Trễ**      động chuyển trạng thái sang Trễ
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

### Business Rule -- Dependency

  -------------------------------------------------------------------------
  **BR-001**   Nút \'Bắt đầu\' của Task B bị khóa nếu Task A (dependency)
               chưa ở trạng thái Hoàn thành. Nếu Task A bị hủy, Leader phải
               mở khóa Task B thủ công.
  ------------ ------------------------------------------------------------

  -------------------------------------------------------------------------

## MODULE 4 -- Workflow & Phê duyệt

  -----------------------------------------------------------------------
  **Mục      Kiểm soát chất lượng giao phẩm qua luồng phê duyệt bắt buộc
  đích**     trước khi Task được đánh dấu Hoàn thành.
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

### Cấu hình Workflow

  -----------------------------------------------------------------------
  **Loại workflow**  **Mô tả**
  ------------------ ----------------------------------------------------
  1 cấp duyệt        PIC gửi → Leader duyệt → Hoàn thành. Phù hợp task
                     nội bộ thông thường.

  2 cấp duyệt        PIC gửi → Leader duyệt → BLĐ/CEO duyệt → Hoàn thành.
                     Áp dụng cho task quan trọng, ngân sách lớn.
  -----------------------------------------------------------------------

### Luồng phê duyệt chi tiết (1 cấp)

  -----------------------------------------------------------------------------
  **\#**   **Actor**        **Hành động**            **Kết quả**
  -------- ---------------- ------------------------ --------------------------
  1        PIC              Upload link giao phẩm +  Task chuyển sang \'Chờ
                            ghi chú → Bấm \'Gửi      duyệt\'
                            duyệt\'                  

  2a       Leader           Kiểm tra giao phẩm → Bấm Task → Hoàn thành.
                            \'Đạt\'                  Telegram gửi cho PIC

  2b       Leader           Kiểm tra giao phẩm → Bấm Task → Đang thực hiện.
                            \'Không đạt\' + lý do    Telegram gửi cho PIC

  3        System           Log lịch sử phê duyệt    Audit trail đầy đủ
                            vào Activity_Log         
  -----------------------------------------------------------------------------

## MODULE 5 -- SLA & KPI

  -----------------------------------------------------------------------
  **Mục      Đo lường và tự động tính điểm hiệu suất nhân sự dựa trên
  đích**     deadline, SLA phòng ban và đánh giá chất lượng.
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

### Cấu hình SLA

  -----------------------------------------------------------------------
  **Chiều SLA**          **Mô tả**
  ---------------------- ------------------------------------------------
  Theo Phòng ban         Mỗi phòng có standard_hours khác nhau cho cùng
                         loại task

  Theo Loại dịch vụ      SLA riêng cho: Hành chính / Kỹ thuật / Vận hành
                         / Báo cáo

  Theo Giai đoạn         SLA có thể thắt chặt hơn tại các Phase quan
                         trọng (VD: Go-live)
  -----------------------------------------------------------------------

### Công thức tính Điểm KPI (BR-002)

  -----------------------------------------------------------------------
  **Điểm KPI = (% Đúng hạn × 0.4) + (% SLA đạt × 0.4) + (Điểm đánh giá
  sao × 0.2)**
  -----------------------------------------------------------------------

  -----------------------------------------------------------------------

  -----------------------------------------------------------------------
  **Thành phần**         **Trọng số**      **Cách tính**
  ---------------------- ----------------- ------------------------------
  \% Đúng hạn            40%               Số task hoàn thành đúng hạn /
                                           Tổng task trong kỳ × 100

  \% SLA đạt             40%               Số task hoàn thành trong
                                           standard_hours / Tổng task ×
                                           100

  Đánh giá sao           20%               Leader chấm 1--5 sao khi duyệt
                                           giao phẩm, quy đổi thành %
  -----------------------------------------------------------------------

### Chỉ số tự động tính

-   \% SLA đạt theo kỳ (tuần / tháng / quý)

-   Số ngày vượt SLA (delay_days)

-   Điểm hiệu suất cá nhân (cập nhật realtime sau mỗi task hoàn thành)

-   Cảnh báo Overload: AI cảnh báo khi PIC được gán quá nhiều task trùng
    deadline

## MODULE 6 -- Dashboard Điều hành

  -----------------------------------------------------------------------
  **Mục      Cung cấp góc nhìn tổng thể và cá nhân theo phân quyền (CEO /
  đích**     Leader / PIC). Dữ liệu realtime.
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

  -----------------------------------------------------------------------
  **Dashboard**   **Các Widget / Chỉ số hiển thị**
  --------------- -------------------------------------------------------
  CEO             Tổng dự án đang chạy \| Dự án trễ \| Task trễ theo
                  phòng ban \| Biểu đồ tiến độ theo tháng \| Tỷ lệ SLA
                  đạt toàn công ty \| Top nhân sự hiệu suất cao/thấp

  Leader          Task của team \| Task sắp đến hạn (≤3 ngày) \| Task quá
                  hạn \| Hiệu suất từng nhân sự \| Task đang chờ Leader
                  duyệt \| Cảnh báo Overload PIC

  PIC (Cá nhân)   Việc tôi đang làm \| Việc sắp đến hạn \| Điểm hiệu suất
                  cá nhân \| Lịch sử task hoàn thành \| Thông báo trả lại
                  / duyệt
  -----------------------------------------------------------------------

  -----------------------------------------------------------------------
  **Phân     Row-level Security: PIC chỉ thấy task của mình. Leader chỉ
  quyền hiển thấy dự án mình quản lý. CEO thấy toàn bộ.
  thị**      
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

## MODULE 7 -- Báo cáo & Xuất dữ liệu

  -----------------------------------------------------------------------
  **Mục      Xuất báo cáo định kỳ và theo yêu cầu phục vụ điều hành và
  đích**     đánh giá nhân sự.
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

  -----------------------------------------------------------------------
  **Loại báo cáo**       **Nội dung**
  ---------------------- ------------------------------------------------
  Báo cáo tuần           Tóm tắt task hoàn thành, trễ, đang chạy trong
                         tuần. Gửi tự động qua Telegram mỗi Thứ 6

  Báo cáo tháng          KPI nhân sự, % SLA đạt, tiến độ dự án, ngân sách
                         (nếu có)

  Báo cáo dự án          Timeline, Phase, Task, giao phẩm, PIC, lịch sử
                         thay đổi

  Báo cáo SLA            Phân tích SLA theo phòng ban, loại task, giai
                         đoạn

  Báo cáo đánh giá sao   Thống kê chất lượng giao phẩm, điểm sao trung
                         bình theo nhân sự
  -----------------------------------------------------------------------

### Định dạng xuất file

-   PDF -- Báo cáo trình bày, ký duyệt

-   Excel (XLSX) -- Phân tích dữ liệu thô

-   Link chia sẻ nội bộ -- Truy cập realtime qua trình duyệt

## MODULE 8 -- Tài liệu & Giao phẩm

  -----------------------------------------------------------------------
  **Mục      Trung tâm lưu trữ tài liệu dự án với version control và
  đích**     audit trail đầy đủ.
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

### Tích hợp & Tính năng

  -----------------------------------------------------------------------
  **Tính năng**         **Mô tả**
  --------------------- -------------------------------------------------
  Tích hợp Google Drive Liên kết file Drive vào task, xem preview, đồng
                        bộ tự động qua Google Drive API

  Version control       Lưu lịch sử từng phiên bản file, so sánh v1 vs
                        v2, rollback khi cần

  Audit trail           Log ai chỉnh sửa, thời gian, nội dung thay đổi
                        (Activity_Log)

  Loại tài liệu         SOP / Biểu mẫu / Báo giá / Hợp đồng / Tài liệu kỹ
                        thuật / Giao phẩm task

  Phân quyền tài liệu   Xem / Chỉnh sửa / Chia sẻ theo role và dự án
  -----------------------------------------------------------------------

## MODULE 9 -- Nhắc việc & Cảnh báo

  -----------------------------------------------------------------------
  **Mục      Proactive notification system đảm bảo không task nào bị bỏ
  đích**     sót. SLA uptime 99.9%.
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

  -----------------------------------------------------------------------
  **Loại cảnh báo**      **Trigger**       **Kênh gửi**
  ---------------------- ----------------- ------------------------------
  Nhắc trước deadline    Deadline - 3 ngày Telegram Bot + Email

  Cảnh báo task trễ      NOW() \> Deadline Telegram + Dashboard badge
                         & Status ≠ Hoàn   
                         thành             

  Task chờ duyệt \> 24h  Chờ duyệt \> 24   Telegram gửi Leader
                         giờ chưa xử lý    

  Báo cáo cuối tuần      Cron: Thứ 6,      Telegram + Email tổng hợp
                         17:00             

  Cảnh báo Overload      PIC có \>3 task   Dashboard Leader + Popup khi
                         deadline trùng    gán task
                         nhau              

  Kết quả phê duyệt      Leader duyệt Đạt  Telegram gửi PIC ngay lập tức
                         / Không đạt       
  -----------------------------------------------------------------------

  -----------------------------------------------------------------------
  **III. LUỒNG CHÍNH HỆ THỐNG (MAIN FLOWS)**
  -----------------------------------------------------------------------

  -----------------------------------------------------------------------

## Flow 1 -- Tạo và triển khai dự án mới

  ----------------------------------------------------------------------------
  **Bước**   **Actor**     **Hành động**          **Kết quả hệ thống**
  ---------- ------------- ---------------------- ----------------------------
  1          CEO/Leader    Tạo dự án: nhập tên,   Dự án khởi tạo, cấu trúc
                           chọn loại, ngân sách,  Phase mẫu tự động sinh
                           leader                 

  2          Leader        Chỉnh sửa Phase:       Phase được lưu, Gantt chart
                           timeline, trọng số,    cập nhật
                           thêm bớt               

  3          Leader        Tạo Task cho từng      Task tạo với status \'Chưa
                           Phase: điền form đầy   bắt đầu\', PIC nhận thông
                           đủ, gán PIC            báo

  4          PIC           Bấm \'Bắt đầu\' task   Status → \'Đang thực hiện\',
                           (nếu dependency đã     bắt đầu tính SLA
                           hoàn thành)            

  5          PIC           Cập nhật % tiến độ,    Status → \'Chờ duyệt\',
                           upload giao phẩm, bấm  Leader nhận Telegram
                           \'Gửi duyệt\'          

  6          Leader        Kiểm tra giao phẩm,    Hoàn thành hoặc trả lại, KPI
                           bấm \'Đạt\' / \'Không  cập nhật, log ghi nhận
                           đạt\'                  
  ----------------------------------------------------------------------------

## Flow 2 -- Quản lý rủi ro Dependency

  -----------------------------------------------------------------------
  **Kịch     Task A (dependency của Task B và C) bị trễ. Hệ thống tự động
  bản**      khóa Task B, C. Leader nhận cảnh báo dây chuyền. Nếu Task A
             bị hủy, Leader phải mở khóa thủ công từng task phụ thuộc.
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

## Flow 3 -- Tính KPI cuối kỳ

-   Cuối tháng: Hệ thống tổng hợp tất cả task hoàn thành của mỗi PIC

-   Áp dụng công thức BR-002: Điểm = (% đúng hạn × 0.4) + (% SLA đạt ×
    0.4) + (sao × 0.2)

-   Cập nhật Dashboard cá nhân và Dashboard Leader

-   Xuất báo cáo KPI tháng dạng PDF/Excel theo yêu cầu

  -----------------------------------------------------------------------
  **IV. TÍCH HỢP & API**
  -----------------------------------------------------------------------

  -----------------------------------------------------------------------

  ------------------------------------------------------------------------
  **Tích hợp**       **Mục đích**        **Chi tiết kỹ thuật**
  ------------------ ------------------- ---------------------------------
  Telegram Bot API   Nhắc việc & cảnh    Trigger: Deadline-3d, Task trễ,
                     báo realtime        Kết quả duyệt. Uptime 99.9%

  Google Drive API   Lưu trữ và Version  OAuth2 xác thực, metadata sync,
                     control giao phẩm   revision history

  Internal Report    Xuất báo cáo        REST API, trigger thủ công hoặc
  API                PDF/Excel           cron job

  Email SMTP         Backup channel cho  Gửi báo cáo tuần, cảnh báo quan
                     Telegram            trọng
  ------------------------------------------------------------------------

  -----------------------------------------------------------------------
  **V. DATA MODEL TÓM TẮT**
  -----------------------------------------------------------------------

  -----------------------------------------------------------------------

  -----------------------------------------------------------------------
  **Entity**      **Các trường chính**
  --------------- -------------------------------------------------------
  Project         id (PK), name, type (Enum), budget, leader_id (FK),
                  status (Enum), created_at

  Phase           id (PK), project_id (FK), name, weight (float),
                  start_date, end_date, order_index

  Task            id (PK), phase_id (FK), name, description, type, pic_id
                  (FK), co_pics (FK\[\]), dependency_id (FK), deadline,
                  status (Enum), progress (0-100), priority (Enum),
                  deliverable_url, issue_note

  SLA_Config      id, dept_id, service_type, standard_hours,
                  effective_date

  Approval_Log    id, task_id, reviewer_id, action (Enum), comment,
                  star_rating, timestamp

  Activity_Log    id, user_id, entity_type, entity_id, action, old_value,
                  new_value, timestamp

  KPI_Score       id, user_id, period (month/quarter), on_time_rate,
                  sla_rate, avg_star, final_score
  -----------------------------------------------------------------------

## MODULE 10 -- Danh sách Phòng ban

  -----------------------------------------------------------------------
  **Mục      Cung cấp danh mục phòng ban và nhân sự nội bộ dạng chỉ xem
  đích**     (read-only). Dùng làm nguồn dữ liệu tra cứu khi gán Leader,
             PIC vào dự án / task. Không có chức năng thêm, sửa, xóa trực
             tiếp trong module này.
  ---------- ------------------------------------------------------------

  -----------------------------------------------------------------------

### Tính năng chính

  -----------------------------------------------------------------------
  **Tính năng**          **Mô tả chi tiết**
  ---------------------- ------------------------------------------------
  Danh sách phòng ban    Hiển thị danh sách toàn bộ phòng ban trong công
                         ty: Mã phòng ban, Tên phòng ban, Số nhân sự,
                         Trưởng phòng, Trạng thái (Hoạt động / Ngừng)

  Xem chi tiết phòng ban Nhấp vào tên phòng ban để xem toàn bộ nhân sự
                         thuộc phòng: Avatar, Họ tên, Chức danh, Email,
                         Số điện thoại, Trạng thái (Đang làm việc / Nghỉ
                         phép / Nghỉ việc)

  Tìm kiếm & lọc         Tìm kiếm nhân sự theo tên, mã nhân viên, chức
                         danh. Lọc theo phòng ban, trạng thái

  Xem profile nhân sự    Nhấp vào tên nhân sự để xem: Thông tin cá nhân,
                         Phòng ban, Chức danh, Danh sách task đang phụ
                         trách (link sang Module 3), Điểm KPI gần nhất
  -----------------------------------------------------------------------

### Phân quyền truy cập

  ------------------------------------------------------------------------
  **Role**        **Danh sách PB**  **Nhân sự PB**    **Ghi chú**
  --------------- ----------------- ----------------- --------------------
  CEO             ✓ Toàn bộ         ✓ Toàn bộ         Xem tất cả phòng ban

  Leader          ✓ Toàn bộ         ✓ Toàn bộ         Xem tất cả phòng ban
                                                      (cần tra cứu khi gán
                                                      PIC)

  PIC             ✓ Toàn bộ         ✓ Chỉ phòng ban   Giới hạn theo
                                    của mình          Row-level Security
  ------------------------------------------------------------------------

### Cấu trúc dữ liệu hiển thị

  -----------------------------------------------------------------------
  **Đối tượng**    **Trường hiển thị**     **Ghi chú**
  ---------------- ----------------------- ------------------------------
  **Phòng ban      Mã PB \| Tên PB \| Số   Enum Trạng thái: Hoạt động /
  (Department)**   nhân sự \| Trưởng phòng Ngừng
                   \| Trạng thái           

  **Nhân sự        Avatar \| Họ tên \| Mã  Enum Trạng thái: Đang làm việc
  (Staff)**        NV \| Chức danh \|      / Nghỉ phép / Nghỉ việc
                   Email \| SĐT \| Trạng   
                   thái                    
  -----------------------------------------------------------------------

  ----------------------------------------------------------------------------
  **READ-ONLY**   Module này không có chức năng thêm / sửa / xóa. Dữ liệu
                  phòng ban và nhân sự được quản lý từ hệ thống HRM / quản trị
                  nội bộ. Module này chỉ đóng vai trò tra cứu và làm nguồn dữ
                  liệu dropdown khi gán Leader / PIC trong các module khác.
  --------------- ------------------------------------------------------------

  ----------------------------------------------------------------------------

  -----------------------------------------------------------------------
  **VI. YÊU CẦU PHI CHỨC NĂNG & BÀN GIAO**
  -----------------------------------------------------------------------

  -----------------------------------------------------------------------

## 6.1 Bảo mật & Phân quyền

-   Row-level Security: PIC chỉ thấy task của mình

-   Leader chỉ thấy dự án và nhân sự mình quản lý

-   CEO có quyền xem toàn bộ hệ thống

-   Audit log lưu mọi thay đổi: ai, làm gì, khi nào

## 6.2 Hiệu năng & Độ tin cậy

  -----------------------------------------------------------------------
  **Chỉ số**             **Yêu cầu**
  ---------------------- ------------------------------------------------
  Telegram uptime        99.9% -- nhắc việc không được miss

  Response time          \< 2 giây cho các query thông thường
  Dashboard              

  Concurrent users       Hỗ trợ tối thiểu 100 users online đồng thời

  Backup                 Daily backup, retention 30 ngày
  -----------------------------------------------------------------------

## 6.3 Rủi ro & Biện pháp

  -----------------------------------------------------------------------
  **Rủi ro**                  **Biện pháp xử lý**
  --------------------------- -------------------------------------------
  PIC bị Overload -- quá      AI cảnh báo tự động trên Dashboard Leader
  nhiều task trùng deadline   khi gán task

  Dữ liệu SLA thực tế không   Module Kiểm soát nội bộ cập nhật định kỳ
  khớp cấu hình               SLA_Config

  Issue note chứa \'Thiếu dữ  Hệ thống tự động đề xuất họp với phòng ban
  liệu\' / \'Phụ thuộc\'      liên quan

  Task A trễ gây ảnh hưởng    Dashboard hiển thị impact map, Leader nhận
  dây chuyền B, C             cảnh báo ngay
  -----------------------------------------------------------------------

*TaskXPro SRS v1.0 \| Tài liệu nội bộ -- Không phát hành bên ngoài \|
Dùng cho bàn giao Production / Freelancer*