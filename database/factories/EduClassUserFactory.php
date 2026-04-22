<?php

namespace Database\Factories;

use App\Models\EduClass;
use App\Models\EduClassUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class EduClassUserFactory extends Factory
{
    protected $model = EduClassUser::class;

    public function definition(): array
    {
        return [
            'class_id'                  => EduClass::factory(),
            'month'                     => sprintf('%02d', fake()->numberBetween(1, 12)),
            'student'                   => [],
            'student_makeup'            => [],
            'student_transfer'          => [],
            'student_order'             => [],
            'order_id'                  => [],
            'teacher'                   => [],
            'days'                      => null,
            'class_year'                => (string) fake()->numberBetween(2020, 2025),
            'class_exam'                => [],
            'sort'                      => 0,
            'history_students_status'   => 0,
        ];
    }
}
