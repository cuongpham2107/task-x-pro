<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['warehouse', 'customs', 'trucking', 'software', 'gms', 'tower']);
            $table->enum('status', ['init', 'running', 'paused', 'completed', 'cancelled'])->default('init');
            $table->decimal('budget', 18, 2)->nullable();
            $table->decimal('budget_spent', 18, 2)->default(0);
            $table->text('objective')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_projects_status');
            $table->index('type', 'idx_projects_type');
            $table->index('created_by', 'idx_projects_created_by');
        });

        Schema::create('project_leaders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->constrained('users');

            $table->unique(['project_id', 'user_id'], 'uq_project_leader');
            $table->index('user_id', 'idx_pl_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_leaders');
        Schema::dropIfExists('projects');
    }
};
