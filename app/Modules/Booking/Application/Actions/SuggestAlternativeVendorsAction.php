<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\SuggestedAlternativeVendorsDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Events\AdminSuggestedAlternativeVendors;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Discovery\Domain\Contracts\AlternativeVendorFinder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SuggestAlternativeVendorsAction
{
    public function __construct(
        private readonly AlternativeVendorFinder $finder,
    ) {}

    public function execute(Booking $booking, SuggestedAlternativeVendorsDTO $dto): BookingAdminIntervention
    {
        $max = config('booking.intervention.suggest_max_candidates', 5);

        if (empty($dto->vendorProfileIds)) {
            throw new InvalidArgumentException('At least one vendor must be suggested.');
        }

        if (count($dto->vendorProfileIds) > $max) {
            throw new InvalidArgumentException("Cannot suggest more than {$max} vendors.");
        }

        // Validate all candidates against the filter (throws InvalidArgumentException on failure)
        $this->finder->validateCandidates($booking, $dto->vendorProfileIds);

        $idempotencyKey = $dto->idempotencyKey;
        if ($idempotencyKey !== null) {
            $cached = DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->where('user_id', $dto->adminId)
                ->where('expires_at', '>', now())
                ->first();

            if ($cached !== null) {
                abort_if(
                    ! hash_equals((string) $cached->request_hash, $this->requestHash($booking, $dto)),
                    409,
                    'Idempotency key conflict',
                );

                $body = json_decode((string) $cached->response_body, true) ?: [];

                return BookingAdminIntervention::findOrFail((int) ($body['intervention_id'] ?? 0));
            }
        }

        return DB::transaction(function () use ($booking, $dto): BookingAdminIntervention {
            $intervention = BookingAdminIntervention::create([
                'public_id' => Str::ulid()->toBase32(),
                'booking_id' => $booking->id,
                'admin_id' => $dto->adminId,
                'intervention_type' => InterventionType::VendorProposal,
                'reason' => $dto->reason,
                'proposed_vendor_id' => null, // MUST always be null — FR-EXT-012
                'before_state' => [],
                'after_state' => [
                    'suggested_vendor_ids' => $dto->vendorProfileIds,
                    'reason' => $dto->reason,
                ],
            ]);

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => Booking::class,
                'auditable_id' => $booking->id,
                'user_id' => $dto->adminId,
                'action' => 'booking.suggest_alternatives',
                'changes' => json_encode([
                    'suggested_vendor_ids' => $dto->vendorProfileIds,
                    'reason' => $dto->reason,
                ]),
                'created_at' => now(),
            ]);

            if ($dto->idempotencyKey !== null) {
                $requestHash = $this->requestHash($booking, $dto);
                DB::table('idempotency_keys')->insertOrIgnore([
                    'key' => $dto->idempotencyKey,
                    'scope' => 'internal',
                    'ttl_seconds' => 86400,
                    'user_id' => $dto->adminId,
                    'route' => 'booking.suggest-alternatives',
                    'request_hash' => $requestHash,
                    'payload_hash' => $requestHash,
                    'response_status' => 201,
                    'response_body' => json_encode(['intervention_id' => $intervention->id], JSON_THROW_ON_ERROR),
                    'expires_at' => now()->addHours(24),
                    'created_at' => now(),
                ]);
            }

            DB::afterCommit(fn () => event(new AdminSuggestedAlternativeVendors(
                $booking->id,
                $dto->adminId,
                $dto->vendorProfileIds,
                $dto->reason,
            )));

            return $intervention;
        });
    }

    private function requestHash(Booking $booking, SuggestedAlternativeVendorsDTO $dto): string
    {
        return hash('sha256', json_encode([
            'booking_public_id' => $booking->public_id,
            'vendor_profile_ids' => array_values($dto->vendorProfileIds),
            'reason' => $dto->reason,
        ], JSON_THROW_ON_ERROR));
    }
}
