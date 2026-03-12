<?php

use App\Services\Tasks\TaskService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tasks:mark-late', function (TaskService $taskService): void {
    $affectedTasks = $taskService->markLateTasks();

    $this->info("Da cap nhat {$affectedTasks} task sang trang thai tre han.");
})->purpose('Cap nhat task tre han theo deadline');

Schedule::command('tasks:mark-late')->everyFiveMinutes();

// Ngày cuối tháng
Schedule::call(function () {
    // Logic thực hiện vào ngày cuối cùng của mỗi tháng sẽ được viết ở đây
    // Cập nhập Kpi của người dùng
})->lastDayOfMonth('23:59');

// Mỗi 17:00 thứ 6 hàng tuần
Schedule::call(function () {
    // Logic thực hiện vào 17:00 thứ 6 hàng tuần
    // 1. Báo cáo task hàng tuần // cho leader cho ceo

})->weekly()->fridays()->at('17:00');

// Mỗi 07:00 hàng ngày
Schedule::call(function () {
    // Logic thực hiện vào 07:00 hàng ngày
    // 1. Nhắc nhở các task sắp tới hạn < 3 ngày với deadline
    // 2. Nhắc nhở Pic đang làm > 3 task trễ deadline
    // 3. Nhắc nhở Task đang trạng thái chờ duyệt quá 24h thì thông báo cho leader
    // 4. Kiểm tra các Task quá hạn mà status != completed thì cập nhật status thành tre han
})->daily()->at('07:00');
