<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class ProviderHealthResult
{
    public function __construct(
        public string $providerName,
        public bool $isReachable,
        public ?int $latencyMs,
        public ?string $note,
        public CarbonImmutable $checkedAt,
    ) {}

    public static function reachable(string $providerName, int $latencyMs, ?string $note = null): self
    {
        return new self($providerName, true, $latencyMs, $note, CarbonImmutable::now());
    }

    public static function unreachable(string $providerName, ?string $note = null): self
    {
        return new self($providerName, false, null, $note, CarbonImmutable::now());
    }

    public function toArray(): array
    {
        return [
            'provider_name' => $this->providerName,
            'is_reachable' => $this->isReachable,
            'latency_ms' => $this->latencyMs,
            'note' => $this->note,
            'checked_at' => $this->checkedAt->toIso8601String(),
        ];
    }
}
