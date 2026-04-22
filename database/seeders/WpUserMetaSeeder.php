<?php

namespace Database\Seeders;

use App\Models\WpUserMeta;
use Illuminate\Database\Seeder;

class WpUserMetaSeeder extends Seeder
{
    public function run(): void
    {
        WpUserMeta::factory()->count(20)->create();
    }
}
