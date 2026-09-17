<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Domain\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserDevice */
class DeviceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'platform' => $this->platform,
            'device_id' => $this->device_id,
            'device_name' => $this->device_name,
            'app_version' => $this->app_version,
            'is_active' => $this->is_active,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
