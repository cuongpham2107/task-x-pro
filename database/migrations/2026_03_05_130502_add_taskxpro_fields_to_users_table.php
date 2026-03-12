<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_code', 20)->nullable()->unique('users_employee_code_unique');
            $table->string('avatar', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('job_title')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['active', 'on_leave', 'resigned'])->default('active');
            $table->string('telegram_id', 100)->nullable();

            $table->index('department_id', 'idx_users_department');
            $table->index('status', 'idx_users_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropUnique('users_employee_code_unique');
            $table->dropIndex('idx_users_department');
            $table->dropIndex('idx_users_status');
            $table->dropColumn([
                'employee_code',
                'avatar',
                'phone',
                'job_title',
                'department_id',
                'status',
                'telegram_id',
            ]);
        });
    }
};
