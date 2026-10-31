<?php

namespace App\Services\Assessment;

final readonly class AssessmentResult
{
    /**
     * @param  Finding[]  $reasons
     * @param  string[]  $missing
     */
    public function __construct(
        public string $status,
        public ?int $score,
        public array $reasons,
        public float $coverage,
        public ?string $ruleVersion,
        public array $missing = [],
        public ?string $confidence = null,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'score' => $this->score,
            'reasons' => array_map(fn (Finding $finding) => $finding->toArray(), $this->reasons),
            'coverage' => $this->coverage,
            'rule_version' => $this->ruleVersion,
            'missing' => $this->missing,
            'confidence' => $this->confidence,
        ];
    }
}
