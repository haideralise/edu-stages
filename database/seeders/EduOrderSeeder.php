<?php

namespace Database\Seeders;

use App\Models\EduOrder;
use Illuminate\Database\Seeder;

class EduOrderSeeder extends Seeder
{
    public function run(): void
    {
        EduOrder::factory()->count(10)->create();
    }
}
