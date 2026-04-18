<?php

namespace Database\Factories;

use App\Models\EduLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class EduLevelFactory extends Factory
{
    protected $model = EduLevel::class;

    public function definition(): array
    {
        return [
            'pid'        => 0,
            'name'       => fake()->words(2, true),
            'data'       => null,
            'file_level' => '',
            'link'       => '',
        ];
    }

    public function childOf(EduLevel $parent): static
    {
        return $this->state(['pid' => $parent->id]);
    }
}
