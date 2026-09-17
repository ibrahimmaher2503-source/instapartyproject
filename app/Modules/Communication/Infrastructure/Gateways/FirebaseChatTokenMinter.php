<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\ChatTokenMinter;
use Kreait\Firebase\Contract\Auth;

final class FirebaseChatTokenMinter implements ChatTokenMinter
{
    public function __construct(
        private readonly Auth $auth,
    ) {}

    public function mint(int $userId, bool $isAdmin): string
    {
        $claims = $isAdmin ? ['role' => 'admin'] : [];

        return $this->auth
            ->createCustomToken((string) $userId, $claims)
            ->toString();
    }
}
