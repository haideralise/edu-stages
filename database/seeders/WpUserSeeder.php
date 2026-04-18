<?php

namespace Database\Seeders;

use App\Models\WpUser;
use Illuminate\Database\Seeder;

class WpUserSeeder extends Seeder
{
    public function run(): void
    {
        WpUser::factory()->count(10)->create();
    }
}
