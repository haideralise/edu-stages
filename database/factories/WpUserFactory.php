<?php

namespace Database\Factories;

use App\Models\WpUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class WpUserFactory extends Factory
{
    protected $model = WpUser::class;

    public function definition(): array
    {
        return [
            'user_login'          => fake()->unique()->userName(),
            'user_pass'           => Hash::make('password'),
            'user_nicename'       => fake()->slug(2),
            'user_email'          => fake()->unique()->safeEmail(),
            'user_url'            => '',
            'user_activation_key' => '',
            'user_status'         => 0,
            'display_name'        => fake()->name(),
        ];
    }
}
