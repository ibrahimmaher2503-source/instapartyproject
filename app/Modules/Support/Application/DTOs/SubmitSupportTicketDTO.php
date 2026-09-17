<?php

declare(strict_types=1);

namespace App\Modules\Support\Application\DTOs;

final readonly class SubmitSupportTicketDTO
{
    public function __construct(
        public string $subject,
        public string $body,
        public ?string $bookingPublicId,
        public ?int $userId,
        public ?string $email,
    ) {}

    public function isGuest(): bool
    {
        return $this->userId === null;
    }
}
