<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\CreateBookingDraftDTO;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Events\BookingDraftCreated;
use App\Modules\Booking\Domain\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBookingDraftAction
{
    public function __construct(private readonly BookingRepository $repository) {}

    public function execute(CreateBookingDraftDTO $dto): Booking
    {
        if ($dto->guestCount !== null && ($dto->guestCount < 1 || $dto->guestCount > CreateBookingDraftDTO::MAX_GUEST_COUNT)) {
            throw ValidationException::withMessages([
                'guest_count' => __('booking.validation.guest_count_range'),
            ]);
        }

        return DB::transaction(function () use ($dto): Booking {
            $booking = $this->repository->create($dto);
            app(AppendCustomerBookingNoteAction::class)->execute($booking, $dto->customerId, $dto->notes, $dto->notesLocale);
            DB::afterCommit(fn () => event(new BookingDraftCreated($booking)));

            return $booking;
        });
    }
}
