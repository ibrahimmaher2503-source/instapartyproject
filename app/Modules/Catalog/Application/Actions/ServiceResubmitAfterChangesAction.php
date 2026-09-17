<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ResubmitAfterChangesTransition;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Events\ChangeRequestResubmitted;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use App\Modules\Shared\Domain\Models\ChangeRequestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceResubmitAfterChangesAction
{
    /** @var list<string> */
    private const SAFE_SERVICE_FIELDS = [
        'name',
        'short_description',
        'long_description',
        'category_id',
        'base_price_minor',
    ];

    /**
     * @param  array<string, mixed>  $changedFields
     */
    public function execute(Service $service, array $changedFields, User $vendor, string $idempotencyKey): ChangeRequest
    {
        abort_unless(
            $service->vendor_profile_id === $vendor->vendorProfile?->getKey(),
            404,
        );

        $routeName = request()->route()?->getName() ?? 'vendor.services.resubmit';

        abort_if(
            DB::table('idempotency_keys')
                ->where('user_id', $vendor->id)
                ->where('route', $routeName)
                ->where('key', $idempotencyKey)
                ->exists(),
            409,
        );

        $changeRequest = ChangeRequest::query()
            ->where('subject_type', 'service')
            ->where('subject_id', $service->id)
            ->where('status', 'open')
            ->latest()
            ->first();
        abort_if(! $changeRequest, 422);

        $requestedFields = ChangeRequestItem::query()
            ->where('change_request_id', $changeRequest->id)
            ->pluck('field_path')
            ->all();
        $invalidFields = array_diff(
            array_keys($changedFields),
            array_intersect(self::SAFE_SERVICE_FIELDS, $requestedFields),
        );
        if ($invalidFields !== []) {
            throw ValidationException::withMessages([
                'changed_fields' => [__('catalog.errors.resubmit_fields_not_allowed')],
            ]);
        }

        return DB::transaction(function () use ($service, $changeRequest, $changedFields, $vendor, $idempotencyKey, $routeName): ChangeRequest {
            foreach ($changedFields as $fieldPath => $newValue) {
                $item = ChangeRequestItem::query()
                    ->where('change_request_id', $changeRequest->id)
                    ->where('field_path', $fieldPath)
                    ->first();

                if ($item) {
                    $item->update([
                        'item_status' => 'addressed',
                    ]);
                }
            }

            $service->update($changedFields);
            $service->status->transition(new ResubmitAfterChangesTransition($service, $vendor->id));

            $changeRequest->update(['status' => 'resubmitted']);

            DB::table('idempotency_keys')->insert([
                'user_id' => $vendor->id,
                'route' => $routeName,
                'key' => $idempotencyKey,
                'request_hash' => hash('sha256', request()->getContent() ?: ''),
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
            ]);

            DB::afterCommit(fn () => event(new ChangeRequestResubmitted($changeRequest, $service)));

            return $changeRequest;
        });
    }
}
