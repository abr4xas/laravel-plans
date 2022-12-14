<?php

namespace Abr4xas\LaravelPlans\Database\Factories;

use Abr4xas\LaravelPlans\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Testing Plan '.$this->faker->randomDigit(),
            'description' => 'This is a testing plan.',
            'duration' => 30,
        ];
    }
}
