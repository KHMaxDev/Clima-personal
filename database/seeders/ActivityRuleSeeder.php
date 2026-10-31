<?php

namespace Database\Seeders;

use App\Models\ActivityCategory;
use App\Models\ActivityRule;
use Illuminate\Database\Seeder;

class ActivityRuleSeeder extends Seeder
{
    public function run(): void
    {
        $running = ActivityCategory::where('slug', 'running')->first();

        if ($running === null) {
            return;
        }

        $rules = [
            [
                'code' => 'rain_high',
                'variable' => 'precipitation_probability',
                'operator' => 'gte',
                'threshold_a' => 60,
                'severity' => 'warning',
                'score_impact' => 20,
                'essential' => false,
                'message_es' => 'Probabilidad de precipitación de {value}% ({threshold}); considera un horario con menor riesgo de lluvia.',
            ],
            [
                'code' => 'heat_extreme',
                'variable' => 'apparent_temperature',
                'operator' => 'gte',
                'threshold_a' => 38,
                'severity' => 'critical',
                'score_impact' => 30,
                'essential' => true,
                'message_es' => 'La sensación térmica alcanza {value} °C ({threshold}); riesgo de golpe de calor.',
            ],
            [
                'code' => 'cold_extreme',
                'variable' => 'apparent_temperature',
                'operator' => 'lte',
                'threshold_a' => 0,
                'severity' => 'critical',
                'score_impact' => 30,
                'essential' => true,
                'message_es' => 'La sensación térmica de {value} °C ({threshold}) implica riesgo por frío.',
            ],
            [
                'code' => 'wind_strong',
                'variable' => 'wind_speed_10m',
                'operator' => 'gte',
                'threshold_a' => 35,
                'severity' => 'warning',
                'score_impact' => 15,
                'essential' => false,
                'message_es' => 'Viento sostenido de {value} km/h ({threshold}); reduce el esfuerzo o busca un horario con menos viento.',
            ],
            [
                'code' => 'uv_high',
                'variable' => 'uv_index',
                'operator' => 'gte',
                'threshold_a' => 8,
                'severity' => 'info',
                'score_impact' => 5,
                'essential' => false,
                'message_es' => 'Índice UV de {value} ({threshold}); usa protección solar.',
            ],
        ];

        foreach ($rules as $rule) {
            ActivityRule::updateOrCreate(
                ['category_id' => $running->id, 'code' => $rule['code']],
                $rule + ['active' => true, 'version' => 1],
            );
        }
    }
}
