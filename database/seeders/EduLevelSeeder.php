<?php

namespace Database\Seeders;

use App\Models\EduLevel;
use Illuminate\Database\Seeder;

class EduLevelSeeder extends Seeder
{
    public function run(): void
    {
        EduLevel::factory()->count(5)->create()->each(function (EduLevel $parent) {
            EduLevel::factory()->count(3)->childOf($parent)->create();
        });
    }
}
