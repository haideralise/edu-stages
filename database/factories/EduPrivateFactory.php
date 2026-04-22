<?php

namespace Database\Factories;

use App\Models\EduPrivate;
use App\Models\WpUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class EduPrivateFactory extends Factory
{
    protected $model = EduPrivate::class;

    public function definition(): array
    {
        $startHour = fake()->numberBetween(8, 17);
        $endHour   = min($startHour + fake()->numberBetween(1, 2), 23);

        return [
            'coach_id'            => WpUser::factory(),
            'enrollment_id'       => 0,
            'student_name'        => fake()->name(),
            'student_phone'       => fake()->phoneNumber(),
            'district'            => fake()->city(),
            'pool'                => fake()->words(2, true),
            'other_location'      => '',
            'class_date'          => fake()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d'),
            'class_time'          => sprintf('%02d:00', $startHour),
            'class_end_time'      => sprintf('%02d:00', $endHour),
            'ratio'               => fake()->randomElement(['1:1', '1:2', '1:3']),
            'type'                => fake()->randomElement(['group', 'private']),
            'fee'                 => fake()->randomFloat(2, 50, 500),
            'status'              => EduPrivate::STATUS_PENDING,
            'payment_date'        => null,
            'refund_date'         => null,
            'attendance'          => 'Present',
            'cumulative_override' => 0,
            'remark'              => null,
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status'       => EduPrivate::STATUS_PAID,
            'payment_date' => now()->format('Y-m-d'),
        ]);
    }
}
