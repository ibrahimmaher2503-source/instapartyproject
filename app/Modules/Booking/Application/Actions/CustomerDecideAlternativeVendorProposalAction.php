<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\CustomerAlternativeVendorProposalDecisionDTO;
use App\Modules\Booking\Domain\Enums\CustomerConsentStatus;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\AlternativeVendorProposalDecided;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Shared\Domain\Models\StateTransition;
use Brick\Money\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomerDecideAlternativeVendorProposalAction
{
    public function __construct(private readonly BuildAlternativeVendorOfferAction $offerBuilder) {}

    public function execute(CustomerAlternativeVendorProposalDecisionDTO $dto): BookingAdminIntervention
    {
        if (! in_array($dto->decision, ['accepted', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => [__('booking::booking.intervention.validation.proposal_decision')]]);
        }
        if (! preg_match('/^[a-f0-9]{64}$/', $dto->offerHash)) {
            throw ValidationException::withMessages(['offer_hash' => [__('booking::booking.intervention.validation.proposal_offer_hash')]]);
        }

        $initial = BookingAdminIntervention::query()
            ->where('public_id', $dto->interventionPublicId)
            ->where('intervention_type', InterventionType::VendorProposal->value)
            ->firstOrFail();

        return DB::transaction(function () use ($dto, $initial): BookingAdminIntervention {
            /** @var Booking $booking */
            $booking = Booking::query()->where('public_id', $dto->bookingPublicId)->lockForUpdate()->firstOrFail();
            abort_if((int) $booking->customer_id !== $dto->customerId, 404);

            /** @var BookingAdminIntervention $intervention */
            $intervention = BookingAdminIntervention::query()
                ->whereKey($initial->getKey())
                ->where('booking_id', $booking->getKey())
                ->where('intervention_type', InterventionType::VendorProposal->value)
                ->lockForUpdate()
                ->firstOrFail();

            if ($intervention->customer_consent_status !== CustomerConsentStatus::Pending) {
                throw new DomainException(__('booking::booking.intervention.error.proposal_not_pending'));
            }

            /** @var BookingVendor $sourceAllocation */
            $sourceAllocation = BookingVendor::query()
                ->where('booking_id', $booking->getKey())
                ->where('public_id', data_get($intervention->before_state, 'source.booking_vendor_public_id'))
                ->lockForUpdate()
                ->firstOrFail();
            /** @var array<string, mixed> $storedOffer */
            $storedOffer = (array) data_get($intervention->after_state, 'offer', []);
            $selectedServicePublicIds = [];
            $serviceIds = [];
            foreach ((array) ($storedOffer['items'] ?? []) as $offeredItem) {
                $itemPublicId = (string) ($offeredItem['booking_item_public_id'] ?? '');
                $replacementService = (array) ($offeredItem['replacement_service'] ?? []);
                $selectedServicePublicIds[$itemPublicId] = (string) ($replacementService['public_id'] ?? '');
                if (isset($replacementService['id'])) {
                    $serviceIds[] = (int) $replacementService['id'];
                }
            }

            $lockedItems = BookingItem::query()
                ->where('booking_vendor_id', $sourceAllocation->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $allServiceIds = $lockedItems->pluck('service_id')->map(static fn ($id): int => (int) $id)
                ->merge($serviceIds)
                ->unique()
                ->sort()
                ->values()
                ->all();
            DB::table('services')->whereIn('id', $allServiceIds)->orderBy('id')->lockForUpdate()->get(['id']);
            DB::table('service_inventory_reservations')
                ->whereIn('service_id', $allServiceIds)
                ->orderBy('service_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($sourceAllocation->sub_status !== VendorSubStatus::Pending
                || $sourceAllocation->response_deadline === null
                || $sourceAllocation->response_deadline->lessThanOrEqualTo(now('UTC'))
                || ! in_array($booking->lifecycle_status->getValue(), [LifecycleStatus::Submitted->value, LifecycleStatus::VendorReview->value], true)) {
                throw new DomainException(__('booking::booking.intervention.error.proposal_stale'));
            }

            if ($intervention->consent_expires_at === null || $intervention->consent_expires_at->lessThanOrEqualTo(now('UTC'))) {
                $intervention->update(['customer_consent_status' => CustomerConsentStatus::ConsentExpired]);

                return $intervention->fresh();
            }

            $beforeSnapshot = $this->offerBuilder->sourceSnapshot($sourceAllocation);
            if ($beforeSnapshot !== data_get($intervention->before_state, 'source')) {
                throw new DomainException(__('booking::booking.intervention.error.proposal_stale'));
            }

            if (! hash_equals((string) ($storedOffer['offer_hash'] ?? ''), $dto->offerHash)) {
                throw new DomainException(__('booking::booking.intervention.error.proposal_offer_changed'));
            }

            $candidatePublicId = data_get($storedOffer, 'candidate_vendor.public_id');
            $candidateVendorProfileId = (int) DB::table('vendor_profiles')->where('public_id', $candidatePublicId)->value('id');
            if ($candidateVendorProfileId < 1) {
                throw new DomainException(__('booking::booking.intervention.error.proposal_stale'));
            }

            $currentOffer = $this->offerBuilder->execute($sourceAllocation, $candidateVendorProfileId, $selectedServicePublicIds);
            if (! hash_equals((string) ($storedOffer['offer_hash'] ?? ''), (string) $currentOffer['offer_hash'])) {
                throw new DomainException(__('booking::booking.intervention.error.proposal_stale'));
            }

            $before = [
                'consent_status' => $intervention->customer_consent_status->value,
                'source' => $beforeSnapshot,
                'offer' => $storedOffer,
            ];
            $afterState = $intervention->after_state;
            $now = now('UTC');
            $afterState['customer_decision'] = [
                'decision' => $dto->decision,
                'decided_at' => $now->toIso8601String(),
                'customer_id' => $dto->customerId,
                'reason' => $dto->reason,
            ];

            if ($dto->decision === 'accepted') {
                $this->applyAcceptedOffer($booking, $sourceAllocation, $lockedItems->keyBy('public_id')->all(), $currentOffer);
                $consentStatus = CustomerConsentStatus::Accepted;
                $intervention->proposed_vendor_id = $candidateVendorProfileId;
            } else {
                $consentStatus = CustomerConsentStatus::Rejected;
            }
            $intervention->customer_consent_status = $consentStatus;
            $intervention->after_state = $afterState;
            $intervention->save();

            $after = [
                'consent_status' => $consentStatus->value,
                'source' => $this->offerBuilder->sourceSnapshot($sourceAllocation),
                'offer' => $storedOffer,
                'customer_decision' => $afterState['customer_decision'],
            ];
            if ($dto->decision === 'accepted') {
                $after['source']['vendor_profile_id'] = $candidateVendorProfileId;
                $after['source']['vendor_public_id'] = $storedOffer['candidate_vendor']['public_id'];
                $after['source']['sub_status'] = VendorSubStatus::Pending->value;
                $after['source']['response_deadline'] = $storedOffer['response_deadline'];
            }

            StateTransition::query()->create([
                'transitionable_type' => BookingAdminIntervention::class,
                'transitionable_id' => $intervention->getKey(),
                'from_state' => CustomerConsentStatus::Pending->value,
                'to_state' => $consentStatus->value,
                'trigger_kind' => 'customer',
                'triggered_by' => $dto->customerId,
                'reason' => $dto->decision === 'accepted'
                    ? __('booking::booking.intervention.audit.proposal_accepted')
                    : __('booking::booking.intervention.audit.proposal_rejected'),
                'context' => [
                    'intervention_public_id' => $intervention->public_id,
                    'previous_vendor_public_id' => data_get($beforeSnapshot, 'vendor_public_id'),
                    'new_vendor_public_id' => data_get($storedOffer, 'candidate_vendor.public_id'),
                    'decision' => $dto->decision,
                ],
            ]);

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => BookingAdminIntervention::class,
                'auditable_id' => $intervention->getKey(),
                'user_id' => $dto->customerId,
                'action' => 'booking.vendor_proposal_decision',
                'changes' => json_encode([
                    'before' => $before,
                    'after' => $after,
                    'reason' => $intervention->reason,
                    'actor_customer_id' => $dto->customerId,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            $newVendorId = $dto->decision === 'accepted' ? $candidateVendorProfileId : null;
            DB::afterCommit(fn () => event(new AlternativeVendorProposalDecided(
                (int) $booking->getKey(),
                (int) $intervention->getKey(),
                $dto->decision,
                (int) data_get($beforeSnapshot, 'vendor_profile_id'),
                $newVendorId,
            )));

            return $intervention->fresh();
        }, 3);
    }

    /** @param array<string, BookingItem> $itemsByPublicId
     * @param  array<string, mixed>  $offer
     */
    private function applyAcceptedOffer(Booking $booking, BookingVendor $sourceAllocation, array $itemsByPublicId, array $offer): void
    {
        $replacementVendorId = (int) DB::table('vendor_profiles')->where('public_id', data_get($offer, 'candidate_vendor.public_id'))->value('id');
        $currency = (string) $sourceAllocation->subtotal_currency;
        $commissionTotal = Money::ofMinor(0, $currency);

        foreach ((array) $offer['items'] as $offeredItem) {
            $item = $itemsByPublicId[(string) $offeredItem['booking_item_public_id']] ?? null;
            abort_if($item === null, 409, __('booking::booking.intervention.error.proposal_stale'));

            $replacement = (array) $offeredItem['replacement_service'];
            $typeSnapshot = (array) ($item->type_snapshot ?? []);
            $typeSnapshot['service_id'] = (int) $replacement['id'];
            $typeSnapshot['service_public_id'] = (string) $replacement['public_id'];
            $typeSnapshot['product_type'] = (string) $offeredItem['product_type'];
            $typeSnapshot['unit_price_minor'] = (int) $item->unit_price_minor;
            $typeSnapshot['unit_price_currency'] = (string) $item->unit_price_currency;
            if (is_array($replacement['package'] ?? null)) {
                $typeSnapshot['service_package'] = $replacement['package'];
            } else {
                unset($typeSnapshot['service_package']);
            }

            $item->update([
                'service_id' => (int) $replacement['id'],
                'name_snapshot' => (array) $replacement['name'],
                'type_snapshot' => $typeSnapshot,
                'commission_bps' => (int) $offeredItem['commission_bps'],
                'commission_minor' => (int) $offeredItem['commission_minor'],
                'commission_currency' => (string) $item->line_total_currency,
            ]);
            $commissionTotal = $commissionTotal->plus(Money::ofMinor((int) $offeredItem['commission_minor'], (string) $item->line_total_currency));

            if (in_array($item->product_type, [ProductType::Rental, ProductType::Sale], true)) {
                $this->transferInventoryReservation($booking, $item, (int) $replacement['id'], (array) $offeredItem);
            }
        }

        $vendorPayout = Money::ofMinor((int) $sourceAllocation->subtotal_minor, $currency)
            ->minus($commissionTotal)
            ->plus(Money::ofMinor((int) $sourceAllocation->delivery_fee_minor, (string) $sourceAllocation->delivery_fee_currency));
        $sourceAllocation->update([
            'vendor_profile_id' => $replacementVendorId,
            'commission_minor' => $commissionTotal->getMinorAmount()->toInt(),
            'commission_currency' => $sourceAllocation->subtotal_currency,
            'vendor_payout_minor' => $vendorPayout->getMinorAmount()->toInt(),
            'vendor_payout_currency' => $sourceAllocation->subtotal_currency,
        ]);
    }

    /** @param array<string, mixed> $offeredItem */
    private function transferInventoryReservation(Booking $booking, BookingItem $item, int $replacementServiceId, array $offeredItem): void
    {
        $reservation = DB::table('service_inventory_reservations')
            ->where('booking_item_id', $item->getKey())
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($reservation !== null && $reservation->status === 'confirmed') {
            throw new DomainException(__('booking::booking.intervention.error.proposal_stale'));
        }

        $startsAt = $offeredItem['starts_at'] ?? null;
        $endsAt = $offeredItem['ends_at'] ?? null;
        $expiresAt = $reservation !== null && $reservation->status === 'held' && Carbon::parse($reservation->expires_at)->isFuture()
            ? $reservation->expires_at
            : now()->addMinutes(15);
        $values = [
            'service_id' => $replacementServiceId,
            'product_type' => $item->product_type->value,
            'status' => 'held',
            'reserved_starts_at' => $startsAt === null ? null : Carbon::parse($startsAt),
            'reserved_ends_at' => $endsAt === null ? null : Carbon::parse($endsAt),
            'quantity' => $item->product_type === ProductType::Rental ? 1 : (int) $item->quantity,
            'expires_at' => $expiresAt,
            'updated_at' => now(),
        ];

        if ($reservation !== null && in_array($reservation->status, ['held', 'expired'], true)) {
            DB::table('service_inventory_reservations')->where('id', $reservation->id)->update($values);

            return;
        }

        DB::table('service_inventory_reservations')->insert([
            'public_id' => Str::ulid()->toBase32(),
            'service_id' => $replacementServiceId,
            'user_id' => $booking->customer_id,
            'product_type' => $item->product_type->value,
            'hold_type' => 'cart',
            'status' => 'held',
            'reserved_starts_at' => $values['reserved_starts_at'],
            'reserved_ends_at' => $values['reserved_ends_at'],
            'quantity' => $values['quantity'],
            'expires_at' => $expiresAt,
            'booking_item_id' => $item->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
