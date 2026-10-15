<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Contracts;

interface PublicVendorPortfolioReader
{
    /**
     * Return only existing public portfolio image URLs for an approved vendor.
     *
     * @return list<array{url: string, thumb: string}>
     */
    public function forApprovedVendor(string $vendorPublicId): array;
}
