<?php

namespace Database\Seeders;

use App\Models\EduClass;
use Illuminate\Database\Seeder;

class EduClassSeeder extends Seeder
{
    public function run(): void
    {
        EduClass::factory()->count(10)->create();
    }
}
