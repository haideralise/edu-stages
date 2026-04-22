<?php

namespace Database\Seeders;

use App\Models\EduUser;
use Illuminate\Database\Seeder;

class EduUserSeeder extends Seeder
{
    public function run(): void
    {
        EduUser::factory()->count(10)->create();
    }
}
