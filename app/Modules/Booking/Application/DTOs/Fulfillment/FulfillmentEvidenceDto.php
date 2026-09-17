<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs\Fulfillment;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

final readonly class FulfillmentEvidenceDto
{
    public function __construct(
        public ?string $completionNote = null,
        public ?UploadedFile $completionPhoto = null,
        public ?CarbonImmutable $completedAt = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }
}
