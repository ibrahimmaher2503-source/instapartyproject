<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Shared\Application\Actions\UploadMediaAction;
use Illuminate\Http\UploadedFile;

/**
 * Service.gallery upload — wraps the shared UploadMediaAction.
 *
 * Cross-type per research.md §12: same shared collection is used by Rental,
 * Sale, and Digital service Resources; per-type "intent" is enforced via tests
 * + admin moderation, not collection branching.
 */
final class UploadServiceGalleryAction
{
    public function __construct(private readonly UploadMediaAction $upload) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array<string, mixed>>
     */
    public function execute(Service $service, array $files): array
    {
        return $this->upload->execute($service, 'gallery', $files);
    }
}
