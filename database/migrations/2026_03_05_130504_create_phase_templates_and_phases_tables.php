<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase_templates', function (Blueprint $table) {
            $table->id();
            $table->enum('project_type', ['warehouse', 'customs', 'trucking', 'software', 'gms', 'tower']);
            $table->string('phase_name');
            $table->text('phase_description')->nullable();
            $table->unsignedSmallInteger('order_index');
            $table->decimal('default_weight', 5, 2);
            $table->unsignedSmallInteger('default_duration_days')->nullable();
            $table->boolean('is_active')->default(true);

            $table->index(['project_type', 'order_index'], 'idx_tmpl_type');
        });

        Schema::create('phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2);
            $table->unsignedSmallInteger('order_index');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->enum('status', ['pending', 'active', 'completed'])->default('pending');
            $table->boolean('is_template')->default(false);
            $table->timestamps();

            $table->index('project_id', 'idx_phases_project');
            $table->index(['project_id', 'order_index'], 'idx_phases_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phases');
        Schema::dropIfExists('phase_templates');
    }
};
