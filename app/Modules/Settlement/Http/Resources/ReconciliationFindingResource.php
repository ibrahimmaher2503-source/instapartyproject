<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Resources;

use App\Modules\Settlement\Application\Support\FinanceIdentityPresenter;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReconciliationFinding
 *
 * @response {
 *   "data": {
 *     "public_id": "01JVXXXXXXXXXXXXXXXXXXXXXX",
 *     "finding_type": "wallet_cache_drift",
 *     "severity": "warning",
 *     "resource": {"type": "Wallet", "name": null, "public_id": "01JVXXXXXXXXXXXXXXXXXXXXXX"},
 *     "resolution": "auto_repaired",
 *     "detected_at": "2026-05-16T04:00:45Z"
 *   }
 * }
 */
class ReconciliationFindingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'finding_type' => $this->finding_type->value,
            'severity' => $this->severity->value,
            'resource' => FinanceIdentityPresenter::data($this->resource_type, $this->resource_id)
                ?? [
                    'type' => FinanceIdentityPresenter::typeLabel($this->resource_type),
                    'name' => null,
                    'public_id' => null,
                ],
            'expected' => $this->expected,
            'actual' => $this->actual,
            'delta' => $this->delta,
            'resolution' => $this->resolution,
            'detected_at' => $this->created_at?->toISOString(),
        ];
    }
}
