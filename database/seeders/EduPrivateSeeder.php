<?php

namespace Database\Seeders;

use App\Models\EduPrivate;
use Illuminate\Database\Seeder;

class EduPrivateSeeder extends Seeder
{
    public function run(): void
    {
        EduPrivate::factory()->count(10)->create();
    }
}
