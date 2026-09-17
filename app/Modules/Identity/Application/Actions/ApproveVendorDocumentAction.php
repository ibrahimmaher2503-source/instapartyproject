<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Events\VendorDocumentApproved;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ApproveVendorDocumentAction
{
    public function execute(VendorDocument $document, ?User $actor = null): VendorDocument
    {
        $actor ??= auth()->user();
        abort_unless($actor?->can('review_vendor_documents'), 403);

        return DB::transaction(function () use ($document, $actor): VendorDocument {
            $locked = VendorDocument::query()->lockForUpdate()->findOrFail($document->getKey());

            if ($locked->status === DocumentStatus::Approved) {
                return $locked;
            }

            if ($locked->status !== DocumentStatus::Pending) {
                throw new ConflictHttpException(__('identity::identity.validation.document_already_reviewed'));
            }

            $locked->update([
                'status' => DocumentStatus::Approved,
                'reviewed_at' => now(),
                'reviewed_by' => $actor->getKey(),
                'review_notes' => null,
            ]);

            activity()
                ->on($locked)
                ->causedBy($actor)
                ->withProperties(['old' => ['status' => 'pending'], 'new' => ['status' => 'approved']])
                ->log('approved_vendor_document');

            DB::afterCommit(fn () => event(new VendorDocumentApproved($locked, (int) $actor->getKey())));

            return $locked;
        }, 3);
    }
}
