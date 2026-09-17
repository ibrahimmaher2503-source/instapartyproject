<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\ChatTokenMinter;
use Illuminate\Support\Facades\Log;

final class ChatTokenMinterStub implements ChatTokenMinter
{
    public function mint(int $userId, bool $isAdmin): string
    {
        Log::info('ChatTokenMinter::mint', ['user_id' => $userId, 'is_admin' => $isAdmin]);

        return 'stub-chat-token-'.$userId.($isAdmin ? '-admin' : '');
    }
}
