<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Timeline\DTOs;

use App\Modules\Shared\Application\Timeline\Exceptions\TimelineCursorInvalidException;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class TimelineCursor
{
    public function __construct(
        public CarbonImmutable $afterOccurredAt,
        public string $afterSourceTable,
        public int $afterSourceId,
    ) {}

    public static function fromString(string $opaque): self
    {
        $decoded = base64_decode(strtr($opaque, '-_', '+/'), strict: true);

        if ($decoded === false) {
            throw new TimelineCursorInvalidException('Invalid cursor encoding');
        }

        $parts = json_decode($decoded, associative: true);

        if (! is_array($parts) || count($parts) !== 3) {
            throw new TimelineCursorInvalidException('Malformed cursor payload');
        }

        try {
            return new self(
                afterOccurredAt: CarbonImmutable::parse($parts[0]),
                afterSourceTable: (string) $parts[1],
                afterSourceId: (int) $parts[2],
            );
        } catch (Throwable $e) {
            throw new TimelineCursorInvalidException('Cannot parse cursor: '.$e->getMessage(), previous: $e);
        }
    }

    public function toString(): string
    {
        $json = json_encode([$this->afterOccurredAt->toIso8601String(), $this->afterSourceTable, $this->afterSourceId]);

        return strtr(base64_encode($json), '+/', '-_');
    }
}
