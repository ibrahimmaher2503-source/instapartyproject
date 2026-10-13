<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Contracts;

interface EmailRecipientReader
{
    public function forUser(int $userId): ?string;
}
