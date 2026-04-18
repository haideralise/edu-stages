<?php

namespace Database\Factories;

use App\Models\EduClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class EduClassFactory extends Factory
{
    protected $model = EduClass::class;

    public function definition(): array
    {
        $year  = (string) fake()->numberBetween(2020, 2025);
        $month = sprintf('%02d', fake()->numberBetween(1, 12));
        $day   = sprintf('%02d', fake()->numberBetween(1, 28));

        return [
            'class_name'   => fake()->words(3, true),
            'district_id'  => fake()->numberBetween(1, 10),
            'product_id'   => fake()->numberBetween(1, 50),
            'product_name' => fake()->words(2, true),
            'date_time'    => fake()->time(),
            'date_month'   => ["{$year}-{$month}"],
            'class_date'   => ["{$year}-{$month}-{$day}"],
            'class_exam'   => [],
            'lv3'          => fake()->word(),
            'class_year'   => $year,
        ];
    }
}
