<?php

namespace Database\Factories;

use App\Models\EduUser;
use App\Models\WpUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class EduUserFactory extends Factory
{
    protected $model = EduUser::class;

    public function definition(): array
    {
        return [
            'user_id'     => WpUser::factory(),
            'note'        => null,
            'hourly_wage' => fake()->randomFloat(2, 20, 200),
            'class_fee'   => fake()->randomFloat(2, 0, 100),
        ];
    }
}
