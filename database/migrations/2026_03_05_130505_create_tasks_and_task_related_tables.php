<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->longText('description')->nullable();
            $table->enum('type', ['admin', 'technical', 'operation', 'report', 'other']);
            $table->enum('status', ['pending', 'in_progress', 'waiting_approval', 'completed', 'late'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->foreignId('pic_id')->constrained('users');
            $table->foreignId('dependency_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->dateTime('deadline');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('deliverable_url', 1000)->nullable();
            $table->text('issue_note')->nullable();
            $table->text('recommendation')->nullable();
            $table->enum('workflow_type', ['single', 'double'])->default('single');
            $table->decimal('sla_standard_hours', 6, 2)->nullable();
            $table->boolean('sla_met')->nullable();
            $table->decimal('delay_days', 6, 2)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('phase_id', 'idx_tasks_phase');
            $table->index('pic_id', 'idx_tasks_pic');
            $table->index('status', 'idx_tasks_status');
            $table->index('deadline', 'idx_tasks_deadline');
            $table->index('dependency_task_id', 'idx_tasks_dependency');
            $table->index('priority', 'idx_tasks_priority');
        });

        Schema::create('task_co_pics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['task_id', 'user_id'], 'uq_task_co_pic');
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploader_id')->constrained('users');
            $table->string('original_name', 500);
            $table->string('stored_path', 1000);
            $table->string('disk', 50)->default('local');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('google_drive_id', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('task_id', 'idx_attachments_task');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_co_pics');
        Schema::dropIfExists('tasks');
    }
};
