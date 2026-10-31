<?php

namespace Tests\Unit;

use App\Services\Assessment\ActivityAssessmentService;
use App\Services\Assessment\AssessmentResult;
use PHPUnit\Framework\TestCase;

class ActivityAssessmentServiceTest extends TestCase
{
    private ActivityAssessmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ActivityAssessmentService(
            coverageThreshold: 0.80,
            scoreBands: [
                'favorable' => [80, 100],
                'favorable_with_precautions' => [60, 79],
                'unfavorable' => [40, 59],
                'high_risk' => [0, 39],
            ],
        );
    }

    public function test_critical_failure_yields_high_risk_without_score(): void
    {
        $result = $this->evaluate(
            values: ['precipitation_probability' => 10, 'apparent_temperature' => 39, 'wind_speed_10m' => 10, 'uv_index' => 2],
        );

        $this->assertSame(ActivityAssessmentService::HIGH_RISK, $result->status);
        $this->assertNull($result->score);
        $this->assertContains('heat_extreme', $this->codes($result));
    }

    public function test_warnings_reduce_score_and_map_to_band(): void
    {
        $result = $this->evaluate(
            values: ['precipitation_probability' => 70, 'apparent_temperature' => 30, 'wind_speed_10m' => 40, 'uv_index' => 3],
        );

        $this->assertSame(65, $result->score);
        $this->assertSame(ActivityAssessmentService::FAVORABLE_WITH_PRECAUTIONS, $result->status);
        $this->assertContains('rain_high', $this->codes($result));
        $this->assertContains('wind_strong', $this->codes($result));
    }

    public function test_no_failures_yields_favorable(): void
    {
        $result = $this->evaluate(
            values: ['precipitation_probability' => 5, 'apparent_temperature' => 24, 'wind_speed_10m' => 8, 'uv_index' => 2],
        );

        $this->assertSame(100, $result->score);
        $this->assertSame(ActivityAssessmentService::FAVORABLE, $result->status);
        $this->assertSame([], $result->reasons);
    }

    public function test_missing_essential_variable_yields_insufficient(): void
    {
        $result = $this->evaluate(
            values: ['precipitation_probability' => 10, 'wind_speed_10m' => 10, 'uv_index' => 2],
            missing: ['apparent_temperature'],
        );

        $this->assertSame(ActivityAssessmentService::INSUFFICIENT, $result->status);
        $this->assertNull($result->score);
    }

    public function test_low_coverage_yields_insufficient(): void
    {
        $result = $this->evaluate(
            values: ['precipitation_probability' => 10, 'apparent_temperature' => 25, 'wind_speed_10m' => 5, 'uv_index' => 1],
            coverage: 0.5,
        );

        $this->assertSame(ActivityAssessmentService::INSUFFICIENT, $result->status);
        $this->assertNull($result->score);
    }

    public function test_no_evaluable_values_yields_insufficient(): void
    {
        $result = $this->evaluate(values: []);

        $this->assertSame(ActivityAssessmentService::INSUFFICIENT, $result->status);
        $this->assertNull($result->score);
    }

    public function test_rule_version_is_reproducible_and_changes_with_rule_version(): void
    {
        $first = $this->evaluate(values: ['apparent_temperature' => 24]);
        $second = $this->evaluate(values: ['apparent_temperature' => 24]);

        $this->assertSame($first->ruleVersion, $second->ruleVersion);
        $this->assertNotNull($first->ruleVersion);

        $rules = $this->rules();
        $rules[0]['version'] = 2;

        $third = $this->service->evaluate($rules, ['values' => ['apparent_temperature' => 24], 'missing' => [], 'coverage' => 1.0]);

        $this->assertNotSame($first->ruleVersion, $third->ruleVersion);
    }

    public function test_message_placeholder_interpolation(): void
    {
        $result = $this->evaluate(
            values: ['precipitation_probability' => 70, 'apparent_temperature' => 24],
        );

        $rain = $this->finding($result, 'rain_high');

        $this->assertNotNull($rain);
        $this->assertSame('Lluvia 70% (≥60).', $rain->message);
        $this->assertSame('≥60', $rain->threshold);
    }

    private function evaluate(array $values, array $missing = [], float $coverage = 1.0): AssessmentResult
    {
        return $this->service->evaluate($this->rules(), [
            'values' => $values,
            'missing' => $missing,
            'coverage' => $coverage,
        ]);
    }

    private function rules(): array
    {
        return [
            ['code' => 'rain_high', 'variable' => 'precipitation_probability', 'operator' => 'gte', 'threshold_a' => 60, 'threshold_b' => null, 'severity' => 'warning', 'score_impact' => 20, 'essential' => false, 'message_es' => 'Lluvia {value}% ({threshold}).', 'version' => 1],
            ['code' => 'heat_extreme', 'variable' => 'apparent_temperature', 'operator' => 'gte', 'threshold_a' => 38, 'threshold_b' => null, 'severity' => 'critical', 'score_impact' => 30, 'essential' => true, 'message_es' => 'Calor {value} ({threshold}).', 'version' => 1],
            ['code' => 'cold_extreme', 'variable' => 'apparent_temperature', 'operator' => 'lte', 'threshold_a' => 0, 'threshold_b' => null, 'severity' => 'critical', 'score_impact' => 30, 'essential' => true, 'message_es' => 'Frío {value}.', 'version' => 1],
            ['code' => 'wind_strong', 'variable' => 'wind_speed_10m', 'operator' => 'gte', 'threshold_a' => 35, 'threshold_b' => null, 'severity' => 'warning', 'score_impact' => 15, 'essential' => false, 'message_es' => 'Viento {value}.', 'version' => 1],
            ['code' => 'uv_high', 'variable' => 'uv_index', 'operator' => 'gte', 'threshold_a' => 8, 'threshold_b' => null, 'severity' => 'info', 'score_impact' => 5, 'essential' => false, 'message_es' => 'UV {value}.', 'version' => 1],
        ];
    }

    private function codes(AssessmentResult $result): array
    {
        return array_map(fn ($finding) => $finding->code, $result->reasons);
    }

    private function finding(AssessmentResult $result, string $code): mixed
    {
        foreach ($result->reasons as $finding) {
            if ($finding->code === $code) {
                return $finding;
            }
        }

        return null;
    }
}
