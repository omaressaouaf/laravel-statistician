<?php

namespace Omaressaouaf\LaravelStatistician\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Omaressaouaf\LaravelStatistician\Tests\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [];
    }
}
