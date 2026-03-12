<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('task_type', ['admin', 'technical', 'operation', 'report', 'other', 'all'])->default('all');
            $table->enum('project_type', ['warehouse', 'customs', 'trucking', 'software', 'gms', 'tower', 'all'])->default('all');
            $table->decimal('standard_hours', 6, 2);
            $table->date('effective_date');
            $table->date('expired_date')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['department_id', 'task_type'], 'idx_sla_dept_type');
            $table->index('effective_date', 'idx_sla_effective');
        });

        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users');
            $table->enum('approval_level', ['leader', 'ceo']);
            $table->enum('action', ['submitted', 'approved', 'rejected']);
            $table->unsignedTinyInteger('star_rating')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('task_id', 'idx_approval_task');
            $table->index('reviewer_id', 'idx_approval_reviewer');
            $table->index('action', 'idx_approval_action');
        });

        Schema::create('kpi_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('period_type', ['monthly', 'quarterly', 'yearly']);
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_value');
            $table->unsignedSmallInteger('total_tasks')->default(0);
            $table->unsignedSmallInteger('on_time_tasks')->default(0);
            $table->decimal('on_time_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('sla_met_tasks')->default(0);
            $table->decimal('sla_rate', 5, 2)->default(0);
            $table->decimal('avg_star', 3, 2)->default(0);
            $table->decimal('final_score', 5, 2)->default(0);
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'period_type', 'period_year', 'period_value'], 'uq_kpi_user_period');
            $table->index('final_score', 'idx_kpi_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_scores');
        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('sla_configs');
    }
};
