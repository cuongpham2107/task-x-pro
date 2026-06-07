**KỊCH BẢN BOT TELEGRAM**

**Phần mềm quản lý công việc TaskXPro**

*Tài liệu mô tả các loại thông báo & chức năng kiểm tra tiến độ*

# **1\. Mục tiêu & đối tượng sử dụng**

● **Mục tiêu chức năng**

Cung cấp cho người dùng TaskXPro các thông báo tự động qua Telegram nhằm theo dõi tiến độ task và dự án, nhắc deadline, cảnh báo quá hạn và tổng hợp báo cáo định kỳ. Nhờ đó các vai trò nắm bắt kịp thời tình hình công việc, xử lý phê duyệt đúng hạn và đảm bảo dự án hoàn thành đúng tiến độ.

● **Đối tượng sử dụng**

\- **PIC** — người được giao và phụ trách task.

\- **Leader** — người chủ trì dự án, phê duyệt giao phẩm.

\- **CEO** — theo dõi tổng quan toàn bộ dự án.

# **2\. Chức năng thông báo Telegram**

## **2.1. Thông báo phê duyệt giao phẩm (ĐẠT)** 

● **Mục tiêu:** Báo cho PIC biết giao phẩm của mình đã được Leader phê duyệt đạt.

● **Đối tượng hiển thị:** PIC được giao task.

● **Trigger hiển thị:** Realtime — khi Leader bấm duyệt ĐẠT giao phẩm (task) của PIC phụ trách.

● **Nội dung thông báo:**

| ✅ Task \[Tên task\] thuộc Phase \[Tên phase\] của Dự án \[Tên dự án\] đã được phê duyệt. 📝 Đánh giá: \[Số lượng sao\] / 5       *\[nội dung đánh giá nếu có\]* |
| :---- |

● ***Ví dụ:***

✅ Task **"Thiết kế giao diện trang chủ"** thuộc Phase **"UI/UX"** của Dự án **"Website bán hàng" *đã được phê duyệt.***

📝 Đánh giá: **4/5**

      Hoàn thành tốt, bố cục rõ ràng.

● **Mô tả (cách lấy dữ liệu):**

\- **\[Tên task\]** — lấy theo tên Công việc của task vừa được duyệt.

\- **\[Tên phase\]** — lấy theo tên Phase (giai đoạn) chứa task.

\- **\[Tên dự án\]** — lấy theo tên Dự án chứa task.

\- **\[Số lượng sao\] \-** đánh giá của leader đối với task

\- **\[nội dung đánh giá\]** — lấy theo nội dung ghi chú đánh giá mà Leader nhập khi duyệt (nếu có).

## **2.2. Thông báo giao phẩm bị từ chối (KHÔNG ĐẠT)** 

● **Mục tiêu:** Báo cho PIC biết giao phẩm bị từ chối kèm lý do để chỉnh sửa.

● **Đối tượng hiển thị:** PIC được giao task.

● **Trigger hiển thị:** Realtime — khi Leader từ chối / đánh dấu giao phẩm KHÔNG ĐẠT.

● **Nội dung thông báo:**

| ❌ Task \[Tên task\] thuộc Phase \[Tên phase\] của Dự án \[Tên dự án\] không đạt. ⚠️ Lý do: *\[lý do từ chối\]* |
| :---- |

● **Ví dụ:**

❌ Task **"Viết API thanh toán**" thuộc Phase **"Backend"** của Dự án **"Website bán hàng"**  ***không đạt**.*

⚠️ Lý do: Thiếu xử lý lỗi khi giao dịch thất bại.

● **Mô tả (cách lấy dữ liệu):**

\- **\[Tên task\]** — lấy theo tên Công việc của task bị từ chối.

\- **\[Tên phase\]** — lấy theo tên Phase chứa task.

\- **\[Tên dự án\]** — lấy theo tên Dự án chứa task.

\- **\[lý do từ chối\]** — lấy theo nội dung Lý do mà Leader nhập khi đánh dấu Không đạt.

## **2.3. Thông báo được giao task mới** 

● **Mục tiêu:** Báo cho PIC biết có task mới được giao kèm deadline.

● **Đối tượng hiển thị:** PIC được giao task.

● **Trigger hiển thị:** Realtime — khi một task mới được giao cho người dùng.

● **Nội dung thông báo:**

| 🆕 Task \[Tên task\] vừa được giao cho bạn bởi \[Tên người giao\] 📁 Dự án \[Tên dự án\]  📋 Phase \[Tên phase\]  ⏳ Deadline: \[ngày deadline của task\] |
| :---- |

● **Ví dụ:**

🆕 Task **"Kiểm thử module giỏ hàng"** thuộc Phase **"Testing"** của Dự án **"Website bán hàng"** vừa được giao cho bạn bởi ***Nguyễn Văn A.***

⏳ **Deadline: 10/06/2026**

● **Mô tả (cách lấy dữ liệu):**

\- **\[Tên task\]** — lấy theo tên Công việc vừa được tạo/gán.

\- **\[Tên phase\]** — lấy theo tên Phase chứa task.

\- **\[Tên dự án\]** — lấy theo tên Dự án chứa task.

\- **\[Tên người giao\]** — lấy theo tên người tạo / người gán task.

\- **\[ngày deadline của task\]** — lấy theo ngày Deadline đã gắn cho task.

## **2.4. Số task cần hoàn thành đầu ngày** 

● **Mục tiêu:** Nhắc PIC số lượng task cần làm trong ngày và số task đã quá hạn.

● **Đối tượng hiển thị:** PIC có task được giao.

● **Trigger hiển thị:** 08:30 mỗi ngày.

● **Nội dung thông báo:**

| ☀️ Chào buổi sáng\! Tổng kết công việc hôm nay: 📋 Có \[Số lượng task\] task hôm nay cần hoàn thành. 🔴 Số task quá hạn: \[Số task quá hạn\] |
| :---- |

● **Ví dụ:**

☀️ Chào buổi sáng\! Tổng kết công việc hôm nay:

📋 Có **5** task hôm nay cần hoàn thành.

🔴 Số task quá hạn: **2**

● **Mô tả (cách lấy dữ liệu):**

\- **\[Số lượng task\]** — Đếm tổng số lượng task của người dùng có Deadline \= hôm nay.

\- **\[Số task quá hạn\]** — Đếm số lượng task thỏa mãn đồng thời:

+ Task ở trạng thái “ Trễ hạn” 

## **2.5. Cảnh báo dự án quá hạn** 

● **Mục tiêu:** Cảnh báo Leader về các dự án đã vượt hạn chót nhưng chưa hoàn thành.

● **Đối tượng hiển thị:** Leader chủ trì dự án.

● **Trigger hiển thị:** 08:30 mỗi ngày.

● **Nội dung thông báo:**

| 🚨 Dự án quá hạn: \[Tên dự án\] Dự án đã vượt quá hạn chót \[ngày deadline\] nhưng chưa hoàn thành. *👉 Vui lòng kiểm tra và cập nhật tiến độ ngay.* |
| :---- |

● **Ví dụ:**

🚨 **Dự án quá hạn**: **Website bán hàng**

Dự án đã vượt quá hạn chót **30/05/2026** nhưng chưa hoàn thành.

👉 Vui lòng kiểm tra và cập nhật tiến độ ngay.

● **Mô tả (cách lấy dữ liệu):**

\- **\[Tên dự án\]** — lấy theo tên Dự án có hạn chót vượt quá hôm nay (tối thiểu −1 ngày) 

\- **\[ngày deadline\]** — lấy theo ngày Hạn chót (deadline) của dự án tương ứng.

## **2.6. Nhắc deadline task** 

● **Mục tiêu:** Nhắc PIC khi task sắp đến hạn để chủ động hoàn thành đúng deadline.

● **Đối tượng hiển thị:** PIC có task được giao.

● **Trigger hiển thị:** Khi task:

+ Còn 2 ngày đến deadline (deadline − 2\)  
+ Cảnh báo lặp lại mỗi ngày cho đến ngày deadline.

● **Nội dung thông báo:**

| ⏰ Task \[Tên task\] sắp đến hạn.  Còn \[số ngày đếm ngược\] ngày. 🗓️ Deadline: \[thời gian\]   📁 Dự án: \[Tên dự án\]   🔖 Giai đoạn: \[tên giai đoạn\] |
| :---- |

● **Ví dụ:**

⏰ Task ***"Kiểm thử module giỏ hàng"*** sắp đến hạn. Còn **2** ngày.

🗓️ Deadline: **10/06/2026**  

📁 Dự án: Website bán hàng  

🔖 Giai đoạn: Testing

● **Mô tả (cách lấy dữ liệu):**

\- **\[Tên task\]** — lấy theo tên Công việc của task sắp đến hạn.

\- **\[số ngày đếm ngược\]** — tính bằng Deadline trừ đi hôm nay (kích hoạt khi \= 2 ngày, lặp lại hằng ngày tới deadline).

\- **\[thời gian\]** — lấy theo ngày Deadline của task.

\- **\[Tên dự án\]** — lấy theo tên Dự án chứa task.

\- **\[tên giai đoạn\]** — lấy theo tên Giai đoạn (phase) chứa task.

## **2.7. Báo cáo cuối tuần của PIC** 

● **Mục tiêu:** Tổng hợp kết quả công việc của PIC trong tuần.

● **Đối tượng hiển thị:** PIC.

● **Trigger hiển thị:** 08:00 sáng thứ 7 hàng tuần.

● **Nội dung thông báo:**

| 📊 BÁO CÁO CUỐI TUẦN *(từ \[ngày\] đến \[ngày\]) Khoảng đo: từ thứ 2 đến thứ 7 của tuần báo cáo.* ✅ Tổng số task ĐÃ LÀM trong tuần: \[tổng số task\] 🟢 Số task ĐẠT: \[số task duyệt Đạt\] 🔴 Số task KHÔNG ĐẠT: \[số task duyệt Không đạt\] 🟡 Số task CHƯA được phê duyệt: \[số task chờ duyệt\] |
| :---- |

● **Ví dụ:**

📊 **BÁO CÁO CUỐI TUẦN** *(từ 26/05/2026 đến 31/05/2026)*

✅ Tổng số task **ĐÃ LÀM** trong tuần: **12**

🟢 Số task **ĐẠT**: **9**

🔴 Số task **KHÔNG ĐẠT: 2**

🟡 Số task **CHƯA được phê duyệt: 1**

● **Mô tả (cách lấy dữ liệu):**

\- **\[ngày\] đến \[ngày\]** — khoảng thời gian 1 tuần, từ thứ 2 đến thứ 7 của tuần được báo cáo.

\- **\[tổng số task\]** — Đếm tổng số task có đồng thời các điều kiện:

\+ Tiến độ \= 100%

\+ Đã gửi phê duyệt

\+ Ngày gửi phê duyệt nằm trong khoảng T2–T7 của tuần đó.

\- **\[số task duyệt Đạt\]** — Đếm tổng task có đồng thời các điều kiện:

\+ Được phê duyệt \= Đạt

\+ Ngày được phê duyệt trong khoảng T2–T7 của tuần thống kê

\+ Trạng thái \= "Hoàn thành".

\- **\[số task duyệt Không đạt\]** — Đếm tổng task có đồng thời các điều kiện:

\+ Được phê duyệt \= Không đạt

\+ Ngày được phê duyệt trong khoảng T2–T7 của tuần thống kê

\+ Trạng thái \= "Đang thực hiện".

\- **\[số task chờ duyệt\]** — Đếm tổng task có đồng thời các điều kiện:

\+ Ngày gửi phê duyệt trong khoảng T2–T7 của tuần đó

\+ Trạng thái \= "Chờ duyệt".

## **2.8. Báo cáo cuối tuần của Leader** 

● **Mục tiêu:** Cung cấp cho Leader bức tranh đầy đủ về các dự án mình chủ trì: tiến độ tổng thể từng dự án, tiến độ chi tiết từng giai đoạn và tình hình task bên trong (hoàn thành / đang chạy / chưa làm), giúp Leader nhận diện điểm nghẽn và điều phối kịp thời.

● **Đối tượng hiển thị:** Leader (báo cáo các dự án do chính Leader này chủ trì).

● **Trigger hiển thị:** 08:00 sáng thứ 7 hàng tuần.

● **Nội dung thông báo:**

| 📈 BÁO CÁO CUỐI TUẦN *(từ \[ngày\] đến \[ngày\])* 👤 Leader: \[Tên Leader\] 📊 Tổng quan: \[N\] dự án đang chủ trì  ✅ \[a\] đúng tiến độ | 🟠 \[b\] rủi ro  | 🔴 \[c\] trễ hạn ─────────────── 📁 1\. Dự án \[Tên dự án\] — Tiến độ tổng thể: \[% toàn dự án\] 🗓️ Deadline: \[ngày\] | Trạng thái: \[đúng tiến độ / rủi ro/ trễ hạn\] 📁 2\. Dự án \[Tên dự án\] *— ... (lặp lại cấu trúc trên cho từng dự án)* |
| :---- |

● **Ví dụ:**

📈 **BÁO CÁO CUỐI TUẦN** *(từ 26/05/2026 đến 31/05/2026)*

👤 **Leader: Nguyễn Văn A**

📊 Tổng quan: **2** dự án đang chủ trì 

✅ 1 đúng tiến độ | 🟠 1 rủi ro | 🔴 0 trễ hạn

───────────────

📁 **1\. Dự án "Website bán hàng"** — Tiến độ tổng thể: **65%**

🗓️ Deadline: 30/06/2026 | Trạng thái: ***Đúng tiến độ***

📁 2**. Dự án "App giao hàng"** — Tiến độ tổng thể: **38%**

🗓️ Deadline: 15/06/2026 | Trạng thái: ***Rủi ro***  


● **Mô tả (cách lấy dữ liệu):**

\- **\[ngày\] đến \[ngày\]** — khoảng 1 tuần, từ thứ 2 đến thứ 7 của tuần được báo cáo.

\- **\[Tên Leader\]** — lấy theo tên người dùng nhận báo cáo (chủ trì dự án).

\- **\[N\] dự án đang chủ trì** — Đếm số Dự án:

+ Do Leader này chủ trì và chưa hoàn thành.   
+ Dự án có ngày bắt đầu / kết thúc nằm trong khoảng thời gian đo lường báo cáo  
  \- **\[a\] đúng tiến độ / \[b\] rủi ro / \[c\] trễ hạn** — Phân loại N dự án theo trạng thái: 

  \+ **Đúng tiến độ** — chưa tới deadline và % thực hiện ≥ 60% 

  \+ **Rủi ro**— sắp tới deadline ( trước ⅔ thời gian của tổng thời gian dành cho dự án) nhưng % thực hiện \< 60%.

  ***Ví dụ:*** Dự án làm trong 3 tháng (từ 1/1/2026 \- 31/3/2026)

  ⅔ thời gian của thời gian dự án là hết  tháng đầu \-\> bắt đầu tính từ tháng thứ 3

  Nếu đến 1/3/2026 \-\> tiến độ dự án \< 60% \-\> Dự án thuộc cảnh báo sắp đến hạn

  \+ **Trễ hạn**— đã vượt quá deadline mà chưa hoàn thành.

  \- **\[Tên dự án\]** — lấy theo tên từng Dự án do Leader chủ trì (liệt kê lần lượt).

  \- **\[% toàn dự án\]** — Tỷ lệ tiến độ của toàn dự án

  \- **\[ngày\] (Deadline)** — lấy theo ngày Hạn chót của dự án.

## **2.9. Cảnh báo task chưa phê duyệt của Leader** 

● **Mục tiêu:** Nhắc Leader xử lý các task PIC đã gửi nhưng chưa được phê duyệt.

● **Đối tượng hiển thị:** Leader.

● **Trigger hiển thị:** 17:00 mỗi ngày

● **Nội dung thông báo:**

| ⚠️ Cảnh báo — còn \[Số lượng task chưa phê duyệt\] task chưa được phê duyệt. *👉 Vui lòng xử lý để PIC không bị chậm tiến độ.* |
| :---- |

● **Ví dụ:**

⚠️ **Cảnh báo** — còn **4 task** chưa được phê duyệt.

👉 Vui lòng xử lý để PIC không bị chậm tiến độ.

● **Mô tả (cách lấy dữ liệu):**

\- **\[Số lượng task chưa phê duyệt\]** — Đếm tổng số task các PIC đã gửi đến Leader để phê duyệt nhưng chưa được phê duyệt 

   (Trạng thái \= "Chờ duyệt") 

## **2.10. Thông báo task cần phê duyệt** 

● **Mục tiêu:** Báo ngay cho Leader khi có task PIC vừa gửi, để Leader phê duyệt kịp thời, tránh tồn đọng.

● **Đối tượng hiển thị:** Leader phụ trách phê duyệt task / chủ trì dự án chứa task.

● **Trigger hiển thị:** Realtime — ngay khi PIC gửi task cần phê duyệt cho Leader.

● **Nội dung thông báo:**

| 📤 Task \[Tên task\] đã được \[PIC\] gửi và cần Leader phê duyệt. 📁 Dự án: \[Tên dự án\] 🔖 Giai đoạn: \[Tên Phase\] |
| :---- |

● **Ví dụ:**

📤 Task **"Viết API thanh toán"** đã được ***Trần Thị B*** gửi và cần phê duyệt.

📁 **Dự án:** Website bán hàng

🔖 **Giai đoạn:** Backend

● **Mô tả (cách lấy dữ liệu):**

\- **\[Tên task\]** — lấy theo tên Công việc mà PIC vừa gửi đi phê duyệt.

\- **\[PIC\]** — lấy theo tên người được giao task (người gửi phê duyệt).

\- **\[Tên dự án\]** — lấy theo tên Dự án chứa task.

\- **\[Tên Phase\]** — lấy theo tên Giai đoạn (phase) chứa task.

## **2.11. Báo cáo cuối tuần cho CEO** 

● **Mục tiêu:** Cung cấp cho CEO bức tranh tổng quan toàn công ty trong tuần: quy mô dự án đang vận hành, dự án vừa hoàn thành, các dự án đang chạy kèm tiến độ chi tiết, và đặc biệt làm nổi bật nhóm dự án rủi ro (chậm / trễ) để CEO ra quyết định ưu tiên nguồn lực.

● **Đối tượng hiển thị:** CEO.

● **Trigger hiển thị:** 08:00 sáng thứ 7 hàng tuần.

● **Nội dung thông báo:**

| 🏢 BÁO CÁO CUỐI TUẦN  *(từ \[ngày\] đến \[ngày\])* 📊 TỔNG QUAN    • Tổng dự án đang theo dõi: \[Tổng\]    • ✅ Hoàn thành trong tuần: \[H\]      • 🔄 Đang tiến độ: \[Đ\]    • 🟠 Chậm tiến độ: \[C\]      • 🔴 Trễ hạn: \[T\] ─────────────── ✅ DỰ ÁN HOÀN THÀNH TRONG TUẦN (\[H\])    • \[Tên dự án\] — hoàn thành ngày \[ngày\] 🔄 DỰ ÁN ĐANG TIẾN ĐỘ (\[Đ\])    • \[Tên dự án\] — đạt \[%\] | Deadline \[ngày\] 🟠 DỰ ÁN CHẬM TIẾN ĐỘ (\[C\]) — sắp tới hạn nhưng \< 60%    • \[Tên dự án\] — đạt \[%\] | còn \[n\] ngày tới deadline 🔴 DỰ ÁN TRỄ HẠN (\[T\]) — đã vượt deadline    • \[Tên dự án\] — đạt \[%\] | trễ \[n\] ngày |
| :---- |

● **Ví dụ:**

🏢 **BÁO CÁO CUỐI TUẦN** *(từ 26/05/2026 đến 31/05/2026)*

📊 **TỔNG QUAN**

   • Tổng dự án đang theo dõi: **8**

   • ✅ Hoàn thành trong tuần: **1**   

   • 🔄 Đang tiến độ: **3**

   • 🟠 Chậm tiến độ: **2**   

   • 🔴 Trễ hạn: **1**

───────────────

✅ **DỰ ÁN HOÀN THÀNH TRONG TUẦN** **(1)**

   • *"Landing page sự kiện"* — hoàn thành ngày ***29/05/2026***

🔄 **DỰ ÁN ĐANG TIẾN ĐỘ** **(3)**

   • *"Website bán hàng"* — đạt **65%** | Deadline ***30/06/2026***

   • *"CRM nội bộ"* — đạt **25%** | Deadline **20/07/2026**

   • *"App đặt lịch"* — đạt 50% | Deadline ***10/07/2026***

🟠 **DỰ ÁN CHẬM TIẾN ĐỘ (2)** 

   • *"App giao hàng"* — đạt **38%** | còn **15** ngày tới deadline

   • *"Cổng thanh toán"* — đạt **45%** | còn **9** ngày tới deadline

🔴 **DỰ ÁN TRỄ HẠN** **(1)**

   • *"Hệ thống kho"* — đạt **80%** | trễ **3** ngày

● **Mô tả (cách lấy dữ liệu):**

\- **\[ngày\] đến \[ngày\]** — khoảng 1 tuần, từ thứ 2 đến thứ 7 của tuần được báo cáo.

\- **\[Tổng\] dự án đang theo dõi** — Đếm tất cả dự án, TRỪ các dự án đã hoàn thành ở các tuần trước đó (dự án hoàn thành trong tuần này vẫn được tính).

\- **\[H\] hoàn thành trong tuần** — Đếm số dự án có Trạng thái chuyển sang Hoàn thành trong khoảng T2–T7 của tuần.

\- **\[Đ\] đang tiến độ** — Đếm số dự án đang chạy (chưa hoàn thành, chưa trễ và không thuộc nhóm chậm).

\- **\[C\] chậm tiến độ** — Đếm số dự án sắp đến hạn deadline 

**Điều kiện kích hoạt:**

* Số ngày còn lại đến deadline ≤ 15 ngày.  
* Tỷ lệ hoàn thành dự án \< 60%.  
* Bộ đếm lặp lại hằng ngày từ thời điểm kích hoạt đến ngày deadline.  
  \- **\[T\] Trễ hạn** — Đếm số dự án đã vượt quá deadline mà chưa hoàn thành.

  \- **\[Tên dự án\] (hoàn thành)** — lấy tên \+ ngày hoàn thành của từng dự án thuộc nhóm **\[H\].**

  \- **\[Tên dự án\] (đang tiến độ)** — lấy tên từng dự án đang chạy, kèm \[%\] toàn dự án và Deadline.

  \- **\[%\] toàn dự án** — Tỷ lệ số task đã hoàn thành trên tổng số task của dự án.

  \- **\[Tên dự án\] (chậm)** — lấy tên dự án chậm, \[%\] hiện tại và số ngày còn lại tới deadline (Deadline − hôm nay).

  **Điều kiện kích hoạt:**  
* Số ngày còn lại đến deadline ≤ 15 ngày.  
* Tỷ lệ hoàn thành dự án \< 60%.  
* Bộ đếm lặp lại hằng ngày từ thời điểm kích hoạt đến ngày deadline.  
  \- **\[Tên dự án\] (trễ)** — lấy tên dự án trễ, \[%\] hiện tại và số ngày đã trễ (hôm nay − Deadline).


# 

# **3\. Chức năng Yêu cầu kiểm tra tiến độ dự án** 

● **Mục tiêu chức năng**

Cho phép người dùng chủ động kiểm tra tiến độ chi tiết của một dự án bất kỳ đang chạy: tiến độ tổng thể, tiến độ từng giai đoạn và trạng thái các task bên trong.

● **Đối tượng sử dụng**

Leader / CEO — người chủ động yêu cầu kiểm tra.

● **Các bước thực hiện**

1. Người dùng nhấn nút **Kiểm tra tiến độ dự án**.

2. Bot hiển thị **danh sách các dự án đang chạy**.

3. Người dùng chọn một dự án.

4. Bot gửi **báo cáo tiến độ chi tiết** theo từng giai đoạn và task của dự án.

● **Nội dung tin nhắn:**

| 🔎 BÁO CÁO TIẾN ĐỘ DỰ ÁN: \[Tên dự án\] 📊 Tiến độ tổng thể: \[% toàn dự án\]  |  🗓️ Deadline: \[ngày\] 🔖 Giai đoạn \[Tên giai đoạn\] — đạt \[%\]    ✅ Hoàn thành: \[số\] | ⏳ Đang chạy: \[số\] | ⬜ Chưa làm: \[số\] | ❌ Trễ hạn :\[số\]    • *\[Tên task\]* — \[trạng thái\] — \[deadline\] |
| :---- |

● **Ví dụ:**

🔎 **BÁO CÁO TIẾN ĐỘ DỰ ÁN**: **Website bán hàng**

📊 Tiến độ tổng thể: **65%**  |  🗓️ Deadline: **30/06/2026**

🔖 ***Giai đoạn "Backend"*** — đạt **80%**

   ✅ Hoàn thành: 8 | ⏳ Đang chạy: 1 | ⬜ Chưa làm: 1 |❌Trễ hạn :0

   • "Viết API thanh toán" — Đang thực hiện — 10/06/2026

   • "Tích hợp cổng VNPay" — Hoàn thành — 05/06/2026

🔖 ***Giai đoạn "Testing***" — đạt **40%**

   ✅ Hoàn thành: 2 | ⏳ Đang chạy: 2 | ⬜ Chưa làm: 1|❌Trễ hạn :0

   • "Kiểm thử module giỏ hàng" — Đang thực hiện — 12/06/2026

● **Mô tả (cách lấy dữ liệu):**

\- **Danh sách dự án đang chạy** — lấy các Dự án có Trạng thái khác "Hoàn thành" và chưa bị hủy.

\- **\[Tên dự án\]** — lấy theo tên Dự án mà người dùng đã chọn.

\- **\[% toàn dự án\]** — Tỷ lệ tiến độ của dự án.

\- **\[ngày\] (Deadline)** — lấy theo ngày Hạn chót của dự án.

\- **\[Tên giai đoạn\]** — lấy theo tên từng Giai đoạn (phase) trong dự án.

\- **\[%\] của giai đoạn** — Tỷ lệ số task hoàn thành trên tổng task trong giai đoạn đó.

\- **Hoàn thành / Đang chạy / Chưa làm** — Đếm số task trong giai đoạn theo trạng thái:

\+ Hoàn thành — tiến độ 100%, đã được duyệt Đạt vaf trạng thái \= “Hoàn thành”.

\+ Đang chạy — đã bắt đầu nhưng chưa gửi / chưa được phê duyệt.

\+ Chưa làm — chưa bắt đầu.

\+ Trễ hạn \- quá deadline nhưng chưa hoàn thành

\- **\[Tên task\] — \[trạng thái\] — \[deadline\]** — mỗi task hiển thị tên Công việc, Trạng thái hiện tại và ngày Deadline.