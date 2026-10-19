<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Infrastructure\Readers;

use App\Modules\Reviews\Domain\Contracts\PublicServiceReviewReader;
use App\Modules\Reviews\Domain\Contracts\ServiceReviewRepository;
use App\Modules\Reviews\Http\Resources\PublicServiceReviewResource;
use Illuminate\Http\Request;

final class EloquentPublicServiceReviewReader implements PublicServiceReviewReader
{
    private const MAX_PAGE_SIZE = 10;

    public function __construct(private readonly ServiceReviewRepository $reviews) {}

    public function forService(int $serviceId, ?string $cursor, int $limit, string $locale): array
    {
        $page = $this->reviews->listApprovedForService($serviceId, [
            'cursor' => $cursor,
            'limit' => min(max($limit, 1), self::MAX_PAGE_SIZE),
        ]);
        $resourceRequest = Request::create('/', 'GET');
        $resourceRequest->headers->set('Accept-Language', $locale);
        $items = array_map(static function ($review) use ($resourceRequest): array {
            $data = (new PublicServiceReviewResource($review))->resolve($resourceRequest);
            if ($data['locale'] instanceof \BackedEnum) {
                $data['locale'] = $data['locale']->value;
            }

            return $data;
        }, $page['items']);

        return [
            'items' => $items,
            'summary' => $this->reviews->aggregateApprovedForService($serviceId),
            'next_cursor' => $page['next_cursor'],
        ];
    }
}
