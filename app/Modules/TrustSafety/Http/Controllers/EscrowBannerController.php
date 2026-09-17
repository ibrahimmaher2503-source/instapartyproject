<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Http\Controllers;

use App\Modules\Shared\Domain\Models\AppSetting;
use App\Modules\TrustSafety\Http\Resources\EscrowBannerResource;
use Illuminate\Http\JsonResponse;

/**
 * @group Trust & Safety
 */
class EscrowBannerController
{
    public function show(): JsonResponse
    {
        $enabled = AppSetting::where('key', 'escrow_banner_enabled')->value('value');
        $text = AppSetting::where('key', 'escrow_banner_text')->value('value');

        return (new EscrowBannerResource(['enabled' => (bool) $enabled, 'text' => $text]))->response();
    }
}
