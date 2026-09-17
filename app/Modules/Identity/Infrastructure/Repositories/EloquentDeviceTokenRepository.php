<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Repositories;

use App\Modules\Identity\Domain\Contracts\DeviceTokenRepository;
use App\Modules\Identity\Domain\Models\UserDevice;

final class EloquentDeviceTokenRepository implements DeviceTokenRepository
{
    public function activeTokensForUser(int $userId): array
    {
        return UserDevice::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->filter()
            ->values()
            ->all();
    }

    public function deactivateToken(string $token): void
    {
        UserDevice::query()->where('fcm_token', $token)->update(['is_active' => false]);
    }
}
