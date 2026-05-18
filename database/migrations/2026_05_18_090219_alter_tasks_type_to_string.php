<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasColumn('tasks', 'type')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'maria'], true)) {
            // MySQL/MariaDB: change enum -> varchar safely
            DB::statement('ALTER TABLE `tasks` MODIFY `type` VARCHAR(191) NULL');
        } elseif ($driver === 'pgsql') {
            // Postgres: alter type to varchar
            DB::statement('ALTER TABLE tasks ALTER COLUMN type TYPE VARCHAR');
        } else {
            // SQLite: often stores enum as varchar already; skip.
            // If needed, add a manual table-rebuild step here.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting enum type is destructive and environment-specific.
        // Leave as-is to avoid accidental data loss.
    }
};
