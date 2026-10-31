<?php

namespace Database\Factories;

use App\Models\ActivityCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+5 days')->setTime(14, 0);

        return [
            'user_id' => User::factory(),
            'category_id' => ActivityCategory::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'start_at' => $start,
            'end_at' => (clone $start)->modify('+1 hour'),
            'timezone' => 'America/Cancun',
            'location_name' => 'Cancún, Quintana Roo',
            'latitude' => 21.1619,
            'longitude' => -86.8515,
            'status' => 'planned',
            'preferences' => null,
        ];
    }
}
