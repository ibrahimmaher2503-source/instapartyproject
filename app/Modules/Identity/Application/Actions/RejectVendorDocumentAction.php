<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Events\VendorDocumentRejected;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RejectVendorDocumentAction
{
    public function execute(VendorDocument $document, array $reviewNotes, ?User $actor = null): VendorDocument
    {
        $actor ??= auth()->user();
        abort_unless($actor?->can('review_vendor_documents'), 403);

        $reviewNotes = array_filter($reviewNotes, fn ($value): bool => filled($value));
        if ($reviewNotes === []) {
            throw ValidationException::withMessages([
                'review_notes' => __('identity::identity.validation.rejection_reason_required'),
            ]);
        }

        return DB::transaction(function () use ($document, $reviewNotes, $actor): VendorDocument {
            $locked = VendorDocument::query()->lockForUpdate()->findOrFail($document->getKey());

            if ($locked->status === DocumentStatus::Rejected && $locked->review_notes === $reviewNotes) {
                return $locked;
            }

            if ($locked->status !== DocumentStatus::Pending) {
                throw new ConflictHttpException(__('identity::identity.validation.document_already_reviewed'));
            }

            $locked->update([
                'status' => DocumentStatus::Rejected,
                'reviewed_at' => now(),
                'reviewed_by' => $actor->getKey(),
                'review_notes' => $reviewNotes,
            ]);

            activity()
                ->on($locked)
                ->causedBy($actor)
                ->withProperties([
                    'old' => ['status' => 'pending'],
                    'new' => ['status' => 'rejected', 'review_notes' => $reviewNotes],
                ])
                ->log('rejected_vendor_document');

            DB::afterCommit(fn () => event(new VendorDocumentRejected(
                $locked,
                (int) $actor->getKey(),
                $reviewNotes,
            )));

            return $locked;
        }, 3);
    }
}
