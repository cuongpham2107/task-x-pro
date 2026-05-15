<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->timestamps();
        });

        // seed default types
        $types = [
            ['key' => 'warehouse', 'label' => 'Warehouse'],
            ['key' => 'customs', 'label' => 'Customs'],
            ['key' => 'trucking', 'label' => 'Trucking'],
            ['key' => 'software', 'label' => 'Software'],
            ['key' => 'gms', 'label' => 'GMS'],
            ['key' => 'tower', 'label' => 'Tower'],
            ['key' => 'all', 'label' => 'All'],
        ];

        foreach ($types as $t) {
            \DB::table('project_types')->insert(array_merge($t, ['created_at' => now(), 'updated_at' => now()]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_types');
    }
};
