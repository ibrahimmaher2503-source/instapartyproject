<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ChangesRequestedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use App\Modules\Shared\Domain\Events\ChangeRequestCreated;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use App\Modules\Shared\Domain\Models\ChangeRequestItem;
use App\Modules\Shared\Domain\Policies\ChangeRequestPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RequestVendorChangesAction
{
    public function execute(VendorProfile $profile, array $items, User $admin, string $idempotencyKey): ChangeRequest
    {
        abort_unless($admin->can('manage_vendor_profile'), 403);

        if (blank($idempotencyKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => [__('identity::identity.validation.idempotency_key_required')],
            ]);
        }

        Validator::make(['items' => $items], [
            'items' => ['required', 'array', 'min:1'],
            'items.*.field_path' => ['required', 'string', 'max:255'],
            'items.*.requested_change_en' => ['required', 'string', 'min:5', 'max:1000'],
            'items.*.requested_change_ar' => ['required', 'string', 'min:5', 'max:1000'],
        ])->validate();

        $payloadHash = hash('sha256', json_encode([
            'profile' => $profile->public_id,
            'items' => $items,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($profile, $items, $admin, $idempotencyKey, $payloadHash): ChangeRequest {
            $lockedProfile = VendorProfile::query()->lockForUpdate()->findOrFail($profile->getKey());
            $existingKey = DB::table('idempotency_keys')->where('key', $idempotencyKey)->first();

            if ($existingKey !== null && now()->greaterThanOrEqualTo($existingKey->expires_at)) {
                DB::table('idempotency_keys')->where('id', $existingKey->id)->delete();
                $existingKey = null;
            }

            if ($existingKey !== null) {
                if ((int) $existingKey->user_id !== (int) $admin->getKey()
                    || ! hash_equals((string) $existingKey->request_hash, $payloadHash)) {
                    throw new ConflictHttpException(__('identity::identity.validation.idempotency_key_conflict'));
                }

                $body = json_decode((string) $existingKey->response_body, true, flags: JSON_THROW_ON_ERROR);

                return ChangeRequest::query()->findOrFail($body['change_request_id']);
            }

            if (! ($lockedProfile->approval_status instanceof PendingState)) {
                throw new ConflictHttpException(__('identity::identity.approval_eligibility.pending_only'));
            }

            $openRequestExists = ChangeRequest::query()
                ->where('subject_type', 'vendor_profile')
                ->where('subject_id', $lockedProfile->getKey())
                ->whereIn('status', [ChangeRequestStatus::Open->value, ChangeRequestStatus::Resubmitted->value])
                ->exists();

            if ($openRequestExists) {
                throw new ConflictHttpException(__('identity::identity.validation.open_change_request_exists'));
            }

            $resolvedCount = ChangeRequest::query()
                ->where('subject_type', 'vendor_profile')
                ->where('subject_id', $lockedProfile->getKey())
                ->where('status', ChangeRequestStatus::Resolved->value)
                ->count();

            if ($resolvedCount >= ChangeRequestPolicy::MAX_CYCLES) {
                throw ValidationException::withMessages([
                    'items' => [__('identity::identity.validation.change_request_cycle_limit')],
                ]);
            }

            $nextCycleNumber = ((int) ChangeRequest::query()
                ->where('subject_type', 'vendor_profile')
                ->where('subject_id', $lockedProfile->getKey())
                ->max('cycle_number')) + 1;

            $changeRequest = ChangeRequest::query()->create([
                'public_id' => (string) Str::ulid(),
                'subject_type' => 'vendor_profile',
                'subject_id' => $lockedProfile->getKey(),
                'requested_by_admin_id' => $admin->getKey(),
                'status' => ChangeRequestStatus::Open,
                'cycle_number' => $nextCycleNumber,
            ]);

            foreach ($items as $item) {
                ChangeRequestItem::query()->create([
                    'public_id' => (string) Str::ulid(),
                    'change_request_id' => $changeRequest->getKey(),
                    'field_path' => $item['field_path'],
                    'current_value_snapshot' => null,
                    'requested_change_en' => $item['requested_change_en'],
                    'requested_change_ar' => $item['requested_change_ar'],
                    'item_status' => 'pending',
                ]);
            }

            $lockedProfile->approval_status->transitionTo(ChangesRequestedState::class, (int) $admin->getKey());

            DB::table('idempotency_keys')->insert([
                'key' => $idempotencyKey,
                'user_id' => $admin->getKey(),
                'route' => request()->route()?->getName() ?? 'identity.vendor.change-request',
                'request_hash' => $payloadHash,
                'response_status' => 201,
                'response_body' => json_encode(['change_request_id' => $changeRequest->getKey()], JSON_THROW_ON_ERROR),
                'expires_at' => now()->addDay(),
                'created_at' => now(),
                'scope' => 'http',
                'ttl_seconds' => 86400,
                'payload_hash' => $payloadHash,
            ]);

            DB::afterCommit(fn () => event(new ChangeRequestCreated($changeRequest)));

            return $changeRequest;
        }, 3);
    }
}
