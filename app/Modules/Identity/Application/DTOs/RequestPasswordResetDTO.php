<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

class RequestPasswordResetDTO
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $channel,
        public readonly string $locale,
    ) {}

    public static function fromArray(array $data): self
    {
        $identifier = trim((string) ($data['identifier'] ?? ''));
        $channel = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'sms';

        return new self(
            identifier: $identifier,
            channel: $channel,
            locale: $data['locale'] ?? 'en',
        );
    }
}
