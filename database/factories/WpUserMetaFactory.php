<?php

namespace Database\Factories;

use App\Models\WpUser;
use App\Models\WpUserMeta;
use Illuminate\Database\Eloquent\Factories\Factory;

class WpUserMetaFactory extends Factory
{
    protected $model = WpUserMeta::class;

    public function definition(): array
    {
        return [
            'user_id'    => WpUser::factory(),
            'meta_key'   => fake()->slug(2),
            'meta_value' => fake()->word(),
        ];
    }
}
