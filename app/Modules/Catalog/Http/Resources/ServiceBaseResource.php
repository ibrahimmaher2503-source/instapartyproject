<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Http\Resources\Concerns\BuildsServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vendor-facing service resource. Emits the canonical service summary plus
 * vendor-only operational fields (status, featured flag, timestamps) and the
 * customer-facing per-type block. Per-type resources (Rental/Sale/Digital)
 * extend the summary with their full write-detail blocks.
 *
 * @mixin Service
 */
class ServiceBaseResource extends JsonResource
{
    use BuildsServiceContract;

    /**
     * Canonical summary + vendor-only operational fields. Shared head for the
     * vendor list and the per-type create/update responses.
     *
     * @return array<string, mixed>
     */
    protected function summaryWithVendorFields(): array
    {
        return array_merge($this->serviceSummary($this->resource, app()->getLocale()), [
            'status' => $this->status->getValue(),
            'is_featured' => (bool) $this->is_featured,
            'created_at' => $this->created_at?->toIso8601String(),
        ]);
    }

    /**
     * Raw {en, ar} maps for the vendor edit form (live audit 2026-06-06 §4.2).
     * The summary resolves translatables per Accept-Language, which made it
     * impossible to prefill both languages — a vendor editing one language
     * silently overwrote the other on PATCH. Detail + write echoes only;
     * the list keeps the lean resolved shape.
     *
     * @return array<string, mixed>
     */
    protected function translationsBlock(): array
    {
        return [
            'translations' => [
                'name' => $this->getTranslations('name'),
                'short_description' => $this->getTranslations('short_description'),
                'long_description' => $this->getTranslations('long_description'),
            ],
        ];
    }

    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return array_merge($this->summaryWithVendorFields(), $this->serviceTypeBlock($this->resource));
    }
}
