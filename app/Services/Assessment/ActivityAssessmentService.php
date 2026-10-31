<?php

namespace App\Services\Assessment;

class ActivityAssessmentService
{
    public const FAVORABLE = 'favorable';

    public const FAVORABLE_WITH_PRECAUTIONS = 'favorable_with_precautions';

    public const UNFAVORABLE = 'unfavorable';

    public const HIGH_RISK = 'high_risk';

    public const INSUFFICIENT = 'insufficient';

    public function __construct(
        private float $coverageThreshold,
        private array $scoreBands,
    ) {}

    public function evaluate(iterable $rules, array $aggregated): AssessmentResult
    {
        $values = $aggregated['values'] ?? [];
        $missing = $aggregated['missing'] ?? [];
        $coverage = (float) ($aggregated['coverage'] ?? 0);

        $findings = [];
        $versionParts = [];
        $essentialMissing = false;
        $hasCriticalFail = false;
        $scoreImpact = 0;

        foreach ($rules as $rule) {
            $attributes = is_array($rule) ? $rule : $rule->getAttributes();

            $code = (string) $attributes['code'];
            $variable = (string) $attributes['variable'];
            $severity = (string) ($attributes['severity'] ?? 'warning');
            $scoreImpactForRule = (int) ($attributes['score_impact'] ?? 0);
            $essential = (bool) ($attributes['essential'] ?? false);

            $versionParts[] = $code.':'.(int) ($attributes['version'] ?? 1);

            if (! array_key_exists($variable, $values)) {
                if ($essential) {
                    $essentialMissing = true;
                    $findings[] = new Finding(
                        code: $code,
                        severity: 'critical',
                        variable: $variable,
                        value: null,
                        threshold: null,
                        message: "Información insuficiente: falta el dato de {$variable}.",
                        scoreImpact: 0,
                        missing: true,
                    );
                }

                continue;
            }

            $value = $values[$variable];
            $thresholdA = $attributes['threshold_a'] ?? null;
            $thresholdB = $attributes['threshold_b'] ?? null;

            if (! $this->fails((string) $attributes['operator'], $value, $thresholdA, $thresholdB)) {
                continue;
            }

            $findings[] = new Finding(
                code: $code,
                severity: $severity,
                variable: $variable,
                value: $value,
                threshold: $this->thresholdLabel((string) $attributes['operator'], $thresholdA, $thresholdB),
                message: $this->interpolate((string) ($attributes['message_es'] ?? ''), (string) $attributes['operator'], $value, $thresholdA, $thresholdB),
                scoreImpact: $scoreImpactForRule,
            );

            if ($severity === 'critical') {
                $hasCriticalFail = true;
            } else {
                $scoreImpact += $scoreImpactForRule;
            }
        }

        $ruleVersion = sha1(implode('|', $this->sortedParts($versionParts)));

        usort($findings, fn (Finding $a, Finding $b) => $this->severityWeight($b->severity) <=> $this->severityWeight($a->severity));

        if ($hasCriticalFail) {
            return new AssessmentResult(self::HIGH_RISK, null, $findings, $coverage, $ruleVersion, $missing);
        }

        if ($essentialMissing || $values === [] || $coverage < $this->coverageThreshold) {
            return new AssessmentResult(self::INSUFFICIENT, null, $findings, $coverage, $ruleVersion, $missing);
        }

        $score = max(0, min(100, 100 - $scoreImpact));

        return new AssessmentResult($this->statusForScore($score), $score, $findings, $coverage, $ruleVersion, $missing);
    }

    private function fails(string $operator, mixed $value, mixed $thresholdA, mixed $thresholdB): bool
    {
        return match ($operator) {
            'lt' => $thresholdA !== null && $value < $thresholdA,
            'lte' => $thresholdA !== null && $value <= $thresholdA,
            'gt' => $thresholdA !== null && $value > $thresholdA,
            'gte' => $thresholdA !== null && $value >= $thresholdA,
            'between' => $thresholdA !== null && $thresholdB !== null && $value >= $thresholdA && $value <= $thresholdB,
            'outside' => $thresholdA !== null && $thresholdB !== null && ($value < $thresholdA || $value > $thresholdB),
            default => false,
        };
    }

    private function statusForScore(int $score): string
    {
        foreach ($this->scoreBands as $status => [$min, $max]) {
            if ($score >= $min && $score <= $max) {
                return (string) $status;
            }
        }

        return self::UNFAVORABLE;
    }

    private function thresholdLabel(string $operator, mixed $thresholdA, mixed $thresholdB): string
    {
        if (in_array($operator, ['between', 'outside'], true)) {
            return $this->formatNumber($thresholdA).'–'.$this->formatNumber($thresholdB);
        }

        $symbol = match ($operator) {
            'lt' => '<',
            'lte' => '≤',
            'gt' => '>',
            'gte' => '≥',
            default => '',
        };

        return $symbol.$this->formatNumber($thresholdA);
    }

    private function interpolate(string $message, string $operator, mixed $value, mixed $thresholdA, mixed $thresholdB): string
    {
        return str_replace(
            ['{value}', '{threshold}'],
            [$this->formatNumber($value), $this->thresholdLabel($operator, $thresholdA, $thresholdB)],
            $message,
        );
    }

    private function formatNumber(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $float = (float) $value;

        if (floor($float) === $float) {
            return (string) (int) $float;
        }

        return rtrim(rtrim(number_format($float, 2, '.', ''), '0'), '.');
    }

    private function severityWeight(string $severity): int
    {
        return match ($severity) {
            'critical' => 3,
            'warning' => 2,
            default => 1,
        };
    }

    /**
     * @param  string[]  $parts
     * @return string[]
     */
    private function sortedParts(array $parts): array
    {
        $parts = array_values(array_unique($parts));
        sort($parts);

        return $parts;
    }
}
