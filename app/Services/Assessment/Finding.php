<?php

namespace App\Services\Assessment;

final readonly class Finding
{
    public function __construct(
        public string $code,
        public string $severity,
        public string $variable,
        public string|int|float|null $value,
        public ?string $threshold,
        public string $message,
        public int $scoreImpact,
        public bool $missing = false,
    ) {}

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'variable' => $this->variable,
            'value' => $this->value,
            'threshold' => $this->threshold,
            'message' => $this->message,
            'score_impact' => $this->scoreImpact,
            'missing' => $this->missing,
        ];
    }
}
