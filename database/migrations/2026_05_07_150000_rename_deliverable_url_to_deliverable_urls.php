<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->json('deliverable_urls')->nullable()->after('deliverable_url');
        });

        // Migrate existing single URL values into the JSON array column
        DB::table('tasks')
            ->whereNotNull('deliverable_url')
            ->orderBy('id')
            ->chunk(100, function ($rows) {
                foreach ($rows as $r) {
                    DB::table('tasks')->where('id', $r->id)->update([
                        'deliverable_urls' => json_encode([$r->deliverable_url]),
                    ]);
                }
            });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('deliverable_url');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('deliverable_url', 1000)->nullable()->after('completed_at');
        });

        DB::table('tasks')
            ->whereNotNull('deliverable_urls')
            ->orderBy('id')
            ->chunk(100, function ($rows) {
                foreach ($rows as $r) {
                    $urls = json_decode($r->deliverable_urls, true);
                    $first = is_array($urls) && count($urls) ? $urls[0] : null;
                    DB::table('tasks')->where('id', $r->id)->update([
                        'deliverable_url' => $first,
                    ]);
                }
            });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('deliverable_urls');
        });
    }
};
