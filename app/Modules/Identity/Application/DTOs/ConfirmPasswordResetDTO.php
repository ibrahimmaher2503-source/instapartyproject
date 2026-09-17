<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

class ConfirmPasswordResetDTO
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $token,
        public readonly string $password,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            identifier: trim((string) $data['identifier']),
            token: (string) $data['token'],
            password: (string) $data['password'],
        );
    }
}
