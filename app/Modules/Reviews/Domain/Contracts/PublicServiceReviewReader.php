<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

interface PublicServiceReviewReader
{
    /**
     * @return array{
     *     items: list<array{public_id: string, rating: int, body: ?string, locale: string, reviewer_first_name: string, created_at: ?string, vendor_response: ?string}>,
     *     summary: array{average: float, count: int},
     *     next_cursor: ?string
     * }
     */
    public function forService(int $serviceId, ?string $cursor, int $limit, string $locale): array;
}
