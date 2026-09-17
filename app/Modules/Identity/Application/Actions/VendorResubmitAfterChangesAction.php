<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Events\VendorProfileResubmitted;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ChangesRequestedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use App\Modules\Shared\Domain\Models\ChangeRequestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class VendorResubmitAfterChangesAction
{
    public function execute(
        VendorProfile $profile,
        array $addressedItemIds,
        array $waivedItemIds,
        ?string $notes,
        string $idempotencyKey,
    ): ChangeRequest {
        $actor = auth()->user();
        abort_unless($actor !== null && $actor->getKey() === $profile->user_id, 403);

        if (blank($idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => [__('identity::identity.validation.idempotency_key_required')],
            ]);
        }

        $addressedItemIds = array_values(array_unique($addressedItemIds));
        $waivedItemIds = array_values(array_unique($waivedItemIds));
        sort($addressedItemIds);
        sort($waivedItemIds);
        $payloadHash = hash('sha256', json_encode([
            'profile' => $profile->public_id,
            'addressed' => $addressedItemIds,
            'waived' => $waivedItemIds,
            'notes' => $notes,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($profile, $addressedItemIds, $waivedItemIds, $notes, $idempotencyKey, $payloadHash, $actor): ChangeRequest {
            $lockedProfile = VendorProfile::query()->lockForUpdate()->findOrFail($profile->getKey());
            $existing = DB::table('idempotency_keys')->where('key', $idempotencyKey)->first();

            if ($existing !== null && now()->greaterThanOrEqualTo($existing->expires_at)) {
                DB::table('idempotency_keys')->where('id', $existing->id)->delete();
                $existing = null;
            }

            if ($existing !== null) {
                if ((int) $existing->user_id !== (int) $actor->getKey()
                    || ! hash_equals((string) $existing->request_hash, $payloadHash)) {
                    throw new ConflictHttpException(__('identity::identity.validation.idempotency_key_conflict'));
                }

                $body = json_decode((string) $existing->response_body, true, flags: JSON_THROW_ON_ERROR);

                return ChangeRequest::query()->findOrFail($body['change_request_id']);
            }

            abort_unless($lockedProfile->approval_status instanceof ChangesRequestedState, 422, __('identity::identity.validation.changes_not_requested'));

            $changeRequest = ChangeRequest::query()
                ->where('subject_type', 'vendor_profile')
                ->where('subject_id', $lockedProfile->id)
                ->where('status', ChangeRequestStatus::Open->value)
                ->latest('cycle_number')
                ->lockForUpdate()
                ->firstOrFail();

            $itemQuery = ChangeRequestItem::query()->where('change_request_id', $changeRequest->id);
            $validIds = $itemQuery->pluck('public_id')->all();
            $submittedIds = array_values(array_unique([...$addressedItemIds, ...$waivedItemIds]));

            if (array_diff($submittedIds, $validIds) !== []) {
                throw ValidationException::withMessages([
                    'addressed_item_ids' => [__('identity::identity.validation.invalid_change_request_items')],
                ]);
            }

            (clone $itemQuery)->whereIn('public_id', $addressedItemIds)->update(['item_status' => 'addressed']);
            (clone $itemQuery)->whereIn('public_id', $waivedItemIds)->update(['item_status' => 'waived']);

            if ((clone $itemQuery)->where('item_status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'addressed_item_ids' => [__('identity::identity.validation.unresolved_change_request_items')],
                ]);
            }

            $changeRequest->update(['status' => ChangeRequestStatus::Resubmitted]);

            activity()
                ->on($lockedProfile)
                ->causedBy($actor)
                ->withProperties([
                    'change_request_public_id' => $changeRequest->public_id,
                    'resubmit_notes' => $notes,
                    'addressed_item_public_ids' => $addressedItemIds,
                    'waived_item_public_ids' => $waivedItemIds,
                ])
                ->log('vendor_profile_resubmitted');

            $lockedProfile->approval_status->transitionTo(PendingState::class, (int) $actor->getKey());

            DB::table('idempotency_keys')->insert([
                'key' => $idempotencyKey,
                'user_id' => $actor->getKey(),
                'route' => request()->route()?->getName() ?? 'identity.vendor.resubmit',
                'request_hash' => $payloadHash,
                'response_status' => 200,
                'response_body' => json_encode(['change_request_id' => $changeRequest->id], JSON_THROW_ON_ERROR),
                'expires_at' => now()->addDay(),
                'created_at' => now(),
                'scope' => 'http',
                'ttl_seconds' => 86400,
                'payload_hash' => $payloadHash,
            ]);

            DB::afterCommit(fn () => event(new VendorProfileResubmitted($lockedProfile, $changeRequest)));

            return $changeRequest;
        }, 3);
    }
}
