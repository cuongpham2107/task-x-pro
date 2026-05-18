<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_types')) {
            Schema::create('task_types', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('label');
                $table->timestamps();
            });
        }

        // seeding will be handled by a dedicated backfill migration
    }

    public function down(): void
    {
        Schema::dropIfExists('task_types');
    }
};
