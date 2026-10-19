<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\ValueObjects;

use App\Modules\Reviews\Domain\Models\ServiceReview;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class ReviewCursor
{
    private function __construct(public string $createdAt, public int $id) {}

    public static function fromEncoded(mixed $value): ?self
    {
        if (! is_string($value) || $value === '' || strlen($value) > 512) {
            return null;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return null;
        }

        try {
            $cursor = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($cursor) || ! is_string($cursor['created_at'] ?? null)) {
            return null;
        }

        $id = filter_var($cursor['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            return null;
        }

        try {
            $createdAt = CarbonImmutable::parse($cursor['created_at'])
                ->utc()
                ->format((new ServiceReview)->getDateFormat());
        } catch (Throwable) {
            return null;
        }

        return new self($createdAt, $id);
    }
}
