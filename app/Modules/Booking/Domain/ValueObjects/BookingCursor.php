<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class BookingCursor
{
    public function __construct(
        public Carbon $createdAt,
        public int $id,
    ) {}

    public static function encode(Carbon $createdAt, int $id): string
    {
        return base64_encode(json_encode([
            'created_at' => $createdAt->toIso8601String(),
            'id' => $id,
        ]));
    }

    /** @throws ValidationException */
    public static function decode(string $raw): self
    {
        $decoded = base64_decode($raw, strict: true);

        if ($decoded === false) {
            throw ValidationException::withMessages(['cursor' => ['Invalid cursor.']]);
        }

        $data = json_decode($decoded, true);

        if (! is_array($data) || ! isset($data['created_at'], $data['id'])) {
            throw ValidationException::withMessages(['cursor' => ['Invalid cursor.']]);
        }

        try {
            $createdAt = Carbon::parse($data['created_at']);
        } catch (Throwable) {
            throw ValidationException::withMessages(['cursor' => ['Invalid cursor.']]);
        }

        return new self($createdAt, (int) $data['id']);
    }
}
