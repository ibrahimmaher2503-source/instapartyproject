<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs\Fulfillment;

use App\Modules\Booking\Domain\Enums\FulfillmentIssueReason;
use Illuminate\Http\UploadedFile;

final readonly class FulfillmentIssueDto
{
    /**
     * @param  list<UploadedFile>  $evidencePhotos
     */
    public function __construct(
        public FulfillmentIssueReason $reasonCode,
        public string $note,
        public array $evidencePhotos = [],
    ) {}
}
