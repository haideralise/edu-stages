<?php

namespace Database\Factories;

use App\Models\EduClass;
use App\Models\EduOrder;
use App\Models\WpUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class EduOrderFactory extends Factory
{
    protected $model = EduOrder::class;

    public function definition(): array
    {
        return [
            'class_id'      => EduClass::factory(),
            'month'         => (string) fake()->numberBetween(1, 12),
            'class_year'    => (string) fake()->numberBetween(2020, 2025),
            'amount'        => fake()->randomFloat(2, 100, 5000),
            'last_days'     => '',
            'gateway'       => fake()->randomElement(['cash', 'transfer', '']),
            'avgfee'        => '',
            'order_date'    => fake()->dateTimeBetween('-1 year', 'now')->getTimestamp(),
            'created'       => now()->timestamp,
            'refund_fee'    => 0,
            'refund_reason' => '',
            'refund_date'   => '',
            'user_id'       => WpUser::factory(),
            'type'          => '',
            'woo_status'    => '',
            'woo_class_name' => '',
            'woo_order_id'  => 0,
            'order_source'  => fake()->randomElement([EduOrder::SOURCE_MANUAL, EduOrder::SOURCE_WOOCOMMERCE]),
        ];
    }

    public function manual(): static
    {
        return $this->state(['order_source' => EduOrder::SOURCE_MANUAL]);
    }

    public function valid(): static
    {
        return $this->state([
            'refund_fee'  => null,
            'refund_date' => null,
            'amount'      => fake()->randomFloat(2, 100, 5000),
        ]);
    }
}
