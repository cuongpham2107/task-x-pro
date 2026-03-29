<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('phases', 'created_by')) {
            Schema::table('phases', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('is_template')->constrained('users')->nullOnDelete();
            });
        }

        // Backfill created_by with project's creator
        $projects = DB::table('projects')->select('id', 'created_by')->get();
        foreach ($projects as $project) {
            DB::table('phases')
                ->where('project_id', $project->id)
                ->whereNull('created_by')
                ->update(['created_by' => $project->created_by]);
        }
    }

    public function down(): void
    {
        Schema::table('phases', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
