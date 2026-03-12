<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_scores', function (Blueprint $table): void {
            $table->string('period_id', 32)->nullable()->after('user_id');
            $table->decimal('target_score', 6, 2)->default(100)->after('avg_star');
            $table->decimal('actual_score', 6, 2)->default(0)->after('target_score');
            $table->string('status', 20)->default('pending')->after('actual_score');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->index('status', 'idx_kpi_status');
            $table->index('approved_at', 'idx_kpi_approved');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE kpi_scores MODIFY period_type ENUM('monthly','quarterly','yearly') NOT NULL");
        }
        DB::statement("UPDATE kpi_scores SET period_id = CONCAT(period_type, '-', period_year, '-', period_value) WHERE period_id IS NULL");
        DB::statement('UPDATE kpi_scores SET target_score = 100, actual_score = final_score WHERE actual_score = 0');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE kpi_scores MODIFY period_type ENUM('monthly','quarterly') NOT NULL");
        }

        Schema::table('kpi_scores', function (Blueprint $table): void {
            $table->dropIndex('idx_kpi_status');
            $table->dropIndex('idx_kpi_approved');
            $table->dropColumn(['period_id', 'target_score', 'actual_score', 'status', 'approved_at']);
        });
    }
};
