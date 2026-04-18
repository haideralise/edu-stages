<?php

namespace Database\Seeders;

use App\Models\EduClassUser;
use Illuminate\Database\Seeder;

class EduClassUserSeeder extends Seeder
{
    public function run(): void
    {
        EduClassUser::factory()->count(10)->create();
    }
}
