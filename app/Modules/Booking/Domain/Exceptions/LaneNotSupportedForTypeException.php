<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Catalog\Domain\Enums\ProductType;
use RuntimeException;

class LaneNotSupportedForTypeException extends RuntimeException
{
    public const CODE = 'fulfillment.lane_not_supported_for_type';

    public function __construct(
        public readonly FulfillmentLane $lane,
        public readonly ProductType $productType,
    ) {
        parent::__construct(sprintf(
            'Fulfillment lane "%s" is not supported for product type "%s".',
            $lane->value,
            $productType->value,
        ));
    }
}
