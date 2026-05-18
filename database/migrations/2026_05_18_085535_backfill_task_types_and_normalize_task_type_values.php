<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// TaskType enum removed; backfill uses best-effort mapping from existing values

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1) Ensure task_types table exists
        if (! Schema::hasTable('task_types')) {
            Schema::create('task_types', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('label');
                $table->timestamps();
            });
        }

        // 2) Seed known canonical keys
        $known = [
            'admin' => 'Hành chính',
            'technical' => 'Kỹ thuật',
            'operation' => 'Vận hành',
            'report' => 'Báo cáo',
            'other' => 'Khác',
        ];

        foreach ($known as $k => $label) {
            DB::table('task_types')->updateOrInsert([
                'key' => $k,
            ], [
                'label' => $label,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Also ensure any existing distinct task.type values are present as labels
        if (Schema::hasTable('tasks')) {
            $distinct = DB::table('tasks')->select('type')->distinct()->pluck('type')->filter()->map(fn ($v) => trim((string) $v))->unique()->values();
            foreach ($distinct as $val) {
                $key = mb_strtolower($val);
                if (! DB::table('task_types')->where('key', $key)->exists()) {
                    DB::table('task_types')->insert([
                        'key' => $key,
                        'label' => mb_convert_case($val, MB_CASE_TITLE, 'UTF-8'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 3) Normalize existing tasks.type values to canonical keys (lowercase)
        if (Schema::hasTable('tasks')) {
            $rows = DB::table('tasks')->select('id', 'type')->get();
            foreach ($rows as $row) {
                $type = trim(mb_strtolower((string) $row->type));
                if ($type === '') {
                    continue;
                }

                // If a task_types record exists for this key/label, use its key
                $match = DB::table('task_types')
                    ->whereRaw('LOWER(`key`) = ?', [$type])
                    ->orWhereRaw('LOWER(`label`) = ?', [$type])
                    ->value('key');

                $newType = $match ?: $type;

                DB::table('tasks')->where('id', $row->id)->update(['type' => $newType]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We won't delete task_types or revert task.type values on rollback
        // to avoid data loss.
    }
};
