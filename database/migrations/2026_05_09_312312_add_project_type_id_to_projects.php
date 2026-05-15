<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'project_type_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->foreignId('project_type_id')->nullable()->after('type')->constrained('project_types')->nullOnDelete();
            });

            // Populate project_type_id based on existing enum 'type' if project_types table exists
            if (Schema::hasTable('project_types')) {
                $mapping = \DB::table('project_types')->pluck('id', 'key')->all();

                foreach (\DB::table('projects')->select('id', 'type')->get() as $project) {
                    $key = $project->type;
                    $ptId = $mapping[$key] ?? null;
                    if ($ptId !== null) {
                        \DB::table('projects')->where('id', $project->id)->update(['project_type_id' => $ptId]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('projects', 'project_type_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropConstrainedForeignId('project_type_id');
            });
        }
    }
};
