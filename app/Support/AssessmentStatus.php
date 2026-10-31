<?php

namespace App\Support;

class AssessmentStatus
{
    public const FAVORABLE = 'favorable';

    public const FAVORABLE_WITH_PRECAUTIONS = 'favorable_with_precautions';

    public const UNFAVORABLE = 'unfavorable';

    public const HIGH_RISK = 'high_risk';

    public const INSUFFICIENT = 'insufficient';

    private const COLORS = [
        self::FAVORABLE => '#16a34a',
        self::FAVORABLE_WITH_PRECAUTIONS => '#ca8a04',
        self::UNFAVORABLE => '#dc2626',
        self::HIGH_RISK => '#991b1b',
        self::INSUFFICIENT => '#6b7280',
    ];

    private const LABELS = [
        self::FAVORABLE => 'Favorable',
        self::FAVORABLE_WITH_PRECAUTIONS => 'Favorable con precauciones',
        self::UNFAVORABLE => 'Poco favorable',
        self::HIGH_RISK => 'Riesgo elevado',
        self::INSUFFICIENT => 'Información insuficiente',
    ];

    private const ICONS = [
        self::FAVORABLE => 'check-circle',
        self::FAVORABLE_WITH_PRECAUTIONS => 'exclamation-triangle',
        self::UNFAVORABLE => 'exclamation-circle',
        self::HIGH_RISK => 'shield-exclamation',
        self::INSUFFICIENT => 'question-mark-circle',
    ];

    public static function color(?string $status): string
    {
        return self::COLORS[$status] ?? self::COLORS[self::INSUFFICIENT];
    }

    public static function icon(?string $status): string
    {
        return self::ICONS[$status] ?? self::ICONS[self::INSUFFICIENT];
    }

    public static function label(?string $status): string
    {
        return self::LABELS[$status] ?? 'Sin evaluar';
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::LABELS;
    }
}
