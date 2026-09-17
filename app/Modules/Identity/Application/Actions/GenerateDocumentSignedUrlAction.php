<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class GenerateDocumentSignedUrlAction
{
    private const int TTL_SECONDS = 300;

    public function execute(VendorDocument $document, User $accessor): string
    {
        $ownsDocument = $accessor->vendorProfile?->getKey() === $document->vendor_profile_id;
        abort_unless($ownsDocument || $accessor->can('review_vendor_documents'), 403);

        $disk = config('filesystems.disks.s3-private.key')
            ? Storage::disk('s3-private')
            : Storage::disk(config('filesystems.default'));
        $path = $document->file_path;

        abort_if($path === '' || str_contains($path, '..') || str_contains($path, '\\') || Str::startsWith($path, '/'), 404);
        abort_unless($disk->exists($path), 404);

        $url = $disk->temporaryUrl($path, now()->addSeconds(self::TTL_SECONDS));

        DB::table('audit_logs')->insert([
            'public_id' => (string) Str::ulid(),
            'auditable_type' => VendorDocument::class,
            'auditable_id' => $document->id,
            'user_id' => $accessor->getKey(),
            'action' => 'signed_url_generated',
            'changes' => json_encode([
                'doc_type' => $document->doc_type->value,
                'ttl_seconds' => self::TTL_SECONDS,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        return $url;
    }
}
