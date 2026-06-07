<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('project_leaders', 'is_primary')) {
            Schema::table('project_leaders', function (Blueprint $table): void {
                $table->boolean('is_primary')->default(false)->after('assigned_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('project_leaders', 'is_primary')) {
            Schema::table('project_leaders', function (Blueprint $table): void {
                $table->dropColumn('is_primary');
            });
        }
    }
};
