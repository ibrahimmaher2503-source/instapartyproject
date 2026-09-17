<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class VendorDocumentUploaded
{
    use Dispatchable;

    public function __construct(
        public VendorDocument $document,
    ) {}
}
