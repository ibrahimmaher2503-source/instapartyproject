<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Modules\Catalog\Domain\Models\Service;
use DomainException;

/**
 * Raised by `PublishServiceAction` when a precondition for publishing is unmet.
 *
 * The Filament admin maps this to a notification with the bilingual message
 * from `shared::media.{code}`; the HTTP layer (if exposed) maps to 422.
 *
 * Per FR-EXT-MED-009 and spec 048-media-collections-phase1.
 */
final class ServiceCannotPublishException extends DomainException
{
    public string $errorCode;

    private function __construct(string $code, string $message)
    {
        parent::__construct($message);
        $this->errorCode = $code;
    }

    public static function missingGalleryImage(Service $service): self
    {
        return new self(
            'media.publish_requires_image',
            sprintf(
                'Service [%s] cannot be published: gallery is empty (min %d image required).',
                $service->public_id,
                1,
            ),
        );
    }
}
