<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final readonly class SetDocumentExpiryAction
{
    public function execute(
        VendorDocument $document,
        Carbon $expiresAt,
        bool $isCritical,
        ?User $actor = null,
    ): VendorDocument {
        $actor ??= auth()->user();
        abort_unless($actor !== null && $actor->can('review_vendor_documents'), 403);

        $timezone = (string) config('app.timezone', 'UTC');
        $expiryEnd = CarbonImmutable::parse($expiresAt->toDateString(), $timezone)->endOfDay();

        if ($expiryEnd->isBefore(CarbonImmutable::now($timezone))) {
            throw new InvalidArgumentException(__('identity::identity.expiry_date_must_be_future'));
        }

        return DB::transaction(function () use ($document, $expiresAt, $isCritical, $actor): VendorDocument {
            $locked = VendorDocument::query()->lockForUpdate()->findOrFail($document->getKey());

            if (! $locked instanceof VendorDocument) {
                throw new LogicException('The document query returned an unexpected model.');
            }

            $date = $expiresAt->toDateString();

            if ($locked->expires_at?->toDateString() === $date && $locked->is_critical === $isCritical) {
                return $locked;
            }

            $locked->update([
                'expires_at' => $date,
                'is_critical' => $isCritical,
            ]);

            activity()
                ->on($locked)
                ->causedBy($actor)
                ->withProperties(['expires_at' => $date, 'is_critical' => $isCritical])
                ->log('updated_vendor_document_expiry');

            return $locked;
        }, 3);
    }
}
