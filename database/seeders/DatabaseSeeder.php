<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            // TaskXProSeeder::class, // Dummy data mẫu - tắt nếu chỉ dùng data từ Excel
            ExcelImportSeeder::class,   // Data từ file Excel
        ]);
    }
}
