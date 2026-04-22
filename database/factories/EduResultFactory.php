<?php

namespace Database\Factories;

use App\Models\EduClass;
use App\Models\EduLevel;
use App\Models\EduResult;
use App\Models\WpUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class EduResultFactory extends Factory
{
    protected $model = EduResult::class;

    public function definition(): array
    {
        $lapCount = fake()->numberBetween(1, 5);
        $lapTimes = array_map(
            fn () => round(fake()->randomFloat(3, 20.0, 120.0), 3),
            range(1, $lapCount)
        );
        sort($lapTimes);

        return [
            'class_id'             => EduClass::factory(),
            'class_month'          => sprintf('%04d-%02d', fake()->numberBetween(2020, 2025), fake()->numberBetween(1, 12)),
            'exam_id'              => EduLevel::factory(),
            'user_id'              => WpUser::factory(),
            'first_name'           => fake()->firstName(),
            'last_name'            => fake()->lastName(),
            'gender'               => fake()->randomElement(['M', 'F']),
            'birthdate'            => fake()->date('Y-m-d', '-5 years'),
            'exam_type'            => fake()->randomElement(['lap', 'timed', 'scored']),
            'exam_name'            => fake()->words(3, true),
            'exam_data'            => '',
            'exam_lap_times'       => $lapTimes,
            'exam_fastest_lap_sec' => min($lapTimes),
            'exam_slowest_lap_sec' => max($lapTimes),
            'exam_avg_lap_sec'     => round(array_sum($lapTimes) / $lapCount, 3),
            'exam_date'            => fake()->date('Y-m-d'),
            'exam_history'         => [],
            'exam_note'            => '',
            'created'              => now()->timestamp,
            'status'               => 1,
            'class_year'           => (string) fake()->numberBetween(2020, 2025),
        ];
    }
}
