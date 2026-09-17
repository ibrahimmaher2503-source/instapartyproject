<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingVendorDeadlineExtended;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Domain\Contracts\AdminInboxWriter;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Shared\Domain\Models\StateTransition;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ExtendVendorResponseDeadlineAction
{
    public function __construct(
        private readonly AdminInboxWriter $inboxWriter,
    ) {}

    public function execute(BookingVendor $bookingVendor, AdminInterventionDTO $dto, int $hours = 24): BookingAdminIntervention
    {
        return DB::transaction(function () use ($bookingVendor, $dto, $hours): BookingAdminIntervention {
            $vendor = BookingVendor::query()->whereKey($bookingVendor->id)->lockForUpdate()->firstOrFail();

            if (! in_array($vendor->sub_status, [VendorSubStatus::Pending, VendorSubStatus::TimedOut], true)) {
                throw new DomainException(__('booking::booking.intervention.error.vendor_cannot_extend_deadline'));
            }

            $beforeSubStatus = $vendor->sub_status->value;
            $beforeDeadline = $vendor->response_deadline?->toDateTimeString();

            $vendor->update([
                'sub_status' => VendorSubStatus::Pending,
                'response_deadline' => now()->addHours($hours),
            ]);

            $intervention = BookingAdminIntervention::create([
                'public_id' => Str::ulid()->toBase32(),
                'booking_id' => $dto->bookingId,
                'admin_id' => $dto->adminId,
                'intervention_type' => InterventionType::DeadlineExtended,
                'reason' => $dto->reason,
                'before_state' => ['sub_status' => $beforeSubStatus, 'response_deadline' => $beforeDeadline],
                'after_state' => ['sub_status' => VendorSubStatus::Pending->value, 'response_deadline' => $vendor->response_deadline->toDateTimeString()],
            ]);

            StateTransition::create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id' => $vendor->id,
                'from_state' => $beforeSubStatus,
                'to_state' => VendorSubStatus::Pending->value,
                'trigger_kind' => 'admin',
                'triggered_by' => $dto->adminId,
                'context' => [
                    'intervention_id' => $intervention->id,
                    'reason' => $dto->reason,
                    'extension_hours' => $hours,
                ],
            ]);

            $this->inboxWriter->create(
                'booking_admin_intervention',
                $intervention->id,
                AdminInboxSeverity::Warning,
                ['en' => "Response deadline extended for booking #{$dto->bookingId}", 'ar' => "تم تمديد موعد الاستجابة للحجز #{$dto->bookingId}"],
                ['en' => $dto->reason, 'ar' => $dto->reason],
                $dto->adminId,
            );

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => BookingVendor::class,
                'auditable_id' => $vendor->id,
                'user_id' => $dto->adminId,
                'action' => 'booking.vendor_deadline_extended',
                'changes' => json_encode([
                    'before' => ['sub_status' => $beforeSubStatus, 'deadline' => $beforeDeadline],
                    'after' => ['sub_status' => VendorSubStatus::Pending->value, 'extension_hours' => $hours],
                    'reason' => $dto->reason,
                    'booking_id' => $dto->bookingId,
                ]),
                'created_at' => now(),
            ]);

            DB::afterCommit(fn () => event(new BookingVendorDeadlineExtended(
                $vendor->id,
                $dto->bookingId,
                $dto->adminId,
                $hours,
                $dto->reason,
            )));

            return $intervention;
        });
    }
}
