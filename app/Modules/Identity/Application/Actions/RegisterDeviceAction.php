<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\UserDevice;
use Illuminate\Support\Facades\DB;

class RegisterDeviceAction
{
    /**
     * Idempotent by design: re-registering the same (user, fcm_token) pair
     * refreshes platform/device metadata instead of creating a duplicate —
     * backed by the UNIQUE(user_id, fcm_token) constraint.
     */
    public function execute(
        int $userId,
        string $platform,
        string $fcmToken,
        ?string $deviceId = null,
        ?string $deviceName = null,
        ?string $appVersion = null,
    ): UserDevice {
        return DB::transaction(fn (): UserDevice => UserDevice::query()->updateOrCreate(
            ['user_id' => $userId, 'fcm_token' => $fcmToken],
            [
                'platform' => $platform,
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'app_version' => $appVersion,
                'last_seen_at' => now(),
                'last_used_at' => now(),
                'is_active' => true,
            ],
        ));
    }
}
