<?php

namespace Database\Seeders;

use App\Models\EduResult;
use Illuminate\Database\Seeder;

class EduResultSeeder extends Seeder
{
    public function run(): void
    {
        EduResult::factory()->count(10)->create();
    }
}
