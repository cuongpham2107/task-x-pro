<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 100);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id'], 'idx_actlog_entity');
            $table->index('user_id', 'idx_actlog_user');
            $table->index('created_at', 'idx_actlog_created');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 100);
            $table->enum('channel', ['telegram', 'email', 'both'])->default('both');
            $table->string('title', 500);
            $table->text('body');
            $table->string('notifiable_type', 100)->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'status'], 'idx_notif_user_status');
            $table->index('scheduled_at', 'idx_notif_scheduled');
            $table->index('type', 'idx_notif_type');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploader_id')->constrained('users');
            $table->string('name', 500);
            $table->enum('document_type', ['sop', 'form', 'quote', 'contract', 'technical', 'deliverable', 'other']);
            $table->text('description')->nullable();
            $table->string('google_drive_id', 255)->nullable();
            $table->string('google_drive_url', 1000)->nullable();
            $table->unsignedSmallInteger('current_version')->default(1);
            $table->enum('permission', ['view', 'edit', 'share'])->default('view');
            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id', 'idx_docs_project');
            $table->index('task_id', 'idx_docs_task');
            $table->index('document_type', 'idx_docs_type');
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->foreignId('uploader_id')->constrained('users');
            $table->string('stored_path', 1000);
            $table->string('google_drive_revision_id', 255)->nullable();
            $table->text('change_summary')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_id', 'version_number'], 'uq_doc_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
    }
};
