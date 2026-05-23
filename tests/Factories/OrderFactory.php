<?php

namespace Omaressaouaf\LaravelStatistician\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Omaressaouaf\LaravelStatistician\Tests\Models\Order;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'total' => $this->faker->randomFloat(2, 100, 1000),
        ];
    }
}
