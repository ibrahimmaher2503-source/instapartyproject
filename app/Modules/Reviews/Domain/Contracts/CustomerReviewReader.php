<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

use App\Modules\Reviews\Application\DTOs\CustomerReviewData;

interface CustomerReviewReader
{
    /** @return list<CustomerReviewData> */
    public function listForCustomer(int $customerId): array;
}
