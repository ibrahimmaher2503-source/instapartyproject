<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Repositories;

use App\Modules\Identity\Domain\Contracts\EmailRecipientReader;
use App\Modules\Identity\Domain\Models\User;

final class EloquentEmailRecipientReader implements EmailRecipientReader
{
    public function forUser(int $userId): ?string
    {
        $email = User::query()->whereKey($userId)->where('status', 'active')->value('email');

        return is_string($email) ? $email : null;
    }
}
