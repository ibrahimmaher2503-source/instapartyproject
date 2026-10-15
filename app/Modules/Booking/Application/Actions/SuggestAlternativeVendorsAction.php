<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\SuggestedAlternativeVendorsDTO;
use App\Modules\Booking\Domain\Enums\CustomerConsentStatus;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\AlternativeVendorProposalCreated;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SuggestAlternativeVendorsAction
{
    public function __construct(private readonly BuildAlternativeVendorOfferAction $offerBuilder) {}

    public function execute(Booking $booking, SuggestedAlternativeVendorsDTO $dto): BookingAdminIntervention
    {
        $admin = User::query()->findOrFail($dto->adminId);
        abort_unless($admin->can('booking.intervene.suggest_alternative_vendors'), 403);

        $reason = trim($dto->reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'reason' => [__('booking::booking.intervention.validation.decision_reason')],
            ]);
        }

        abort_if((int) $booking->getKey() !== $dto->bookingId, 404);

        return DB::transaction(function () use ($dto, $admin, $reason): BookingAdminIntervention {
            $lockedBooking = Booking::query()->whereKey($dto->bookingId)->lockForUpdate()->firstOrFail();
            $cached = $this->cachedIntervention($lockedBooking, $dto, $reason);
            if ($cached !== null) {
                return $cached;
            }

            /** @var BookingVendor $sourceAllocation */
            $sourceAllocation = BookingVendor::query()
                ->where('booking_id', $lockedBooking->getKey())
                ->where('public_id', $dto->bookingVendorPublicId)
                ->lockForUpdate()
                ->firstOrFail();
            BookingItem::query()
                ->where('booking_vendor_id', $sourceAllocation->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (! in_array($lockedBooking->lifecycle_status->getValue(), [LifecycleStatus::Submitted->value, LifecycleStatus::VendorReview->value], true)
                || $sourceAllocation->sub_status !== VendorSubStatus::Pending) {
                throw new DomainException(__('booking::booking.intervention.error.vendor_not_pending'));
            }
            if ($sourceAllocation->response_deadline === null || $sourceAllocation->response_deadline->lessThanOrEqualTo(now('UTC'))) {
                throw new DomainException(__('booking::booking.intervention.error.vendor_deadline_expired'));
            }

            $activeProposal = BookingAdminIntervention::query()
                ->where('booking_id', $lockedBooking->getKey())
                ->where('intervention_type', InterventionType::VendorProposal->value)
                ->where('customer_consent_status', CustomerConsentStatus::Pending->value)
                ->lockForUpdate()
                ->get()
                ->first(fn (BookingAdminIntervention $intervention): bool => data_get($intervention->before_state, 'source.booking_vendor_public_id') === $sourceAllocation->public_id
                    && $intervention->consent_expires_at?->isFuture());
            if ($activeProposal !== null) {
                throw new DomainException(__('booking::booking.intervention.error.proposal_already_pending'));
            }

            $offer = $this->offerBuilder->execute($sourceAllocation, $dto->vendorProfileId);
            $before = ['source' => $this->offerBuilder->sourceSnapshot($sourceAllocation)];
            $after = [
                'offer' => $offer,
                'reason' => $reason,
                'customer_decision' => null,
            ];
            $intervention = BookingAdminIntervention::query()->create([
                'public_id' => Str::ulid()->toBase32(),
                'booking_id' => $lockedBooking->getKey(),
                'admin_id' => $admin->getKey(),
                'intervention_type' => InterventionType::VendorProposal,
                'reason' => $reason,
                'before_state' => $before,
                'after_state' => $after,
                'customer_consent_status' => CustomerConsentStatus::Pending,
                'proposed_vendor_id' => null,
                // The customer decision cannot outlive the original vendor response window.
                'consent_expires_at' => $sourceAllocation->response_deadline,
            ]);

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => BookingAdminIntervention::class,
                'auditable_id' => $intervention->getKey(),
                'user_id' => $admin->getKey(),
                'action' => 'booking.vendor_proposal_created',
                'changes' => json_encode([
                    'before' => $before,
                    'after' => $after,
                    'reason' => $reason,
                    'actor_admin_id' => $admin->getKey(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            if ($dto->idempotencyKey !== null) {
                $requestHash = $this->requestHash($lockedBooking, $dto, $reason);
                DB::table('idempotency_keys')->insertOrIgnore([
                    'key' => $dto->idempotencyKey,
                    'scope' => 'internal',
                    'scope_key' => 'booking_suggest_alternatives',
                    'ttl_seconds' => 86400,
                    'user_id' => $admin->getKey(),
                    'route' => 'booking.suggest-alternatives',
                    'request_hash' => $requestHash,
                    'payload_hash' => $requestHash,
                    'response_status' => 201,
                    'response_body' => json_encode(['intervention_id' => $intervention->getKey()], JSON_THROW_ON_ERROR),
                    'expires_at' => now()->addHours(24),
                    'created_at' => now(),
                ]);
            }

            DB::afterCommit(fn () => event(new AlternativeVendorProposalCreated($lockedBooking->getKey(), $intervention->getKey())));

            return $intervention;
        }, 3);
    }

    private function cachedIntervention(Booking $booking, SuggestedAlternativeVendorsDTO $dto, string $reason): ?BookingAdminIntervention
    {
        if ($dto->idempotencyKey === null) {
            return null;
        }

        $cached = DB::table('idempotency_keys')
            ->where('key', $dto->idempotencyKey)
            ->where('scope', 'internal')
            ->where('scope_key', 'booking_suggest_alternatives')
            ->where('route', 'booking.suggest-alternatives')
            ->where('user_id', $dto->adminId)
            ->where('expires_at', '>', now())
            ->first();

        if ($cached === null) {
            return null;
        }

        abort_if(
            ! hash_equals((string) $cached->request_hash, $this->requestHash($booking, $dto, $reason)),
            409,
            'Idempotency key conflict',
        );

        $body = json_decode((string) $cached->response_body, true) ?: [];

        return BookingAdminIntervention::query()->findOrFail((int) ($body['intervention_id'] ?? 0));
    }

    private function requestHash(Booking $booking, SuggestedAlternativeVendorsDTO $dto, string $reason): string
    {
        return hash('sha256', json_encode([
            'booking_public_id' => $booking->public_id,
            'source_allocation_public_id' => $dto->bookingVendorPublicId,
            'candidate_vendor_profile_id' => $dto->vendorProfileId,
            'reason' => $reason,
        ], JSON_THROW_ON_ERROR));
    }
}
