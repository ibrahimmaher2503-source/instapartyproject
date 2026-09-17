<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Foundation\Events\Dispatchable;

class VendorDocumentRejected
{
    use Dispatchable;

    public function __construct(
        public readonly VendorDocument $document,
        public readonly ?int $rejectedBy = null,
        public readonly array $reviewNotes = [],
    ) {}
}
