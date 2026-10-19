<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\DTOs;

use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use Carbon\CarbonInterface;

final readonly class CustomerReviewData
{
    public function __construct(
        public ReviewType $type,
        public int $rating,
        public string $body,
        public ?string $targetName,
        public ModerationStatus $moderationStatus,
        public ?CarbonInterface $createdAt,
        public ?string $vendorResponse,
    ) {}
}
