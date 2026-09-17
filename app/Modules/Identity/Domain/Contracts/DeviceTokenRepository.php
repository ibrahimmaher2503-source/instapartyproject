<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Contracts;

interface DeviceTokenRepository
{
    /** @return list<string> */
    public function activeTokensForUser(int $userId): array;

    public function deactivateToken(string $token): void;
}
