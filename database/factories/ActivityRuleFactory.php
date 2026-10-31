<?php

namespace Database\Factories;

use App\Models\ActivityCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => ActivityCategory::factory(),
            'code' => fake()->unique()->slug(2),
            'variable' => 'temperature_2m',
            'operator' => 'gte',
            'threshold_a' => 30,
            'threshold_b' => null,
            'severity' => 'warning',
            'score_impact' => 10,
            'essential' => false,
            'message_es' => 'Mensaje de prueba con valor {value} y umbral {threshold}.',
            'active' => true,
            'version' => 1,
        ];
    }
}
