<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Application\DTOs\CreateBookingDraftDTO;
use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddServiceToEventAction
{
    public function __construct(
        private readonly CreateBookingDraftAction $createDraft,
        private readonly AddItemToBookingAction $addItem,
        private readonly CatalogServiceReader $catalogReader,
    ) {}

    public function execute(
        int $customerId,
        CreateBookingDraftDTO $draft,
        string $servicePublicId,
        int $quantity,
    ): Booking {
        // Retry the outer unit so nested item-inventory checks receive a fresh transaction snapshot.
        return DB::transaction(function () use ($customerId, $draft, $servicePublicId, $quantity): Booking {
            $serviceId = $this->catalogReader->resolvePublicId($servicePublicId);
            if ($serviceId === null || $this->catalogReader->findPublishedById($serviceId) === null) {
                throw ValidationException::withMessages(['service_id' => __('storefront.service.add_error')]);
            }

            $booking = Booking::query()
                ->where('customer_id', $customerId)
                ->whereState('lifecycle_status', DraftState::class)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                $booking = $this->createDraft->execute($draft);
            }

            try {
                $this->addItem->execute(new AddBookingItemDTO(
                    bookingId: $booking->id,
                    customerId: $customerId,
                    serviceId: $serviceId,
                    quantity: $quantity,
                    effectiveStartsAt: null,
                    effectiveEndsAt: null,
                    customizationData: null,
                ));
            } catch (HttpResponseException $exception) {
                /** @var array<string, mixed> $payload */
                $payload = json_decode($exception->getResponse()->getContent() ?: '{}', true) ?: [];
                $message = data_get($payload, 'errors.message')
                    ?? data_get($payload, 'errors')
                    ?? __('storefront.service.add_error');

                throw ValidationException::withMessages(['service_id' => (string) $message]);
            }

            app(AppendCustomerBookingNoteAction::class)->execute($booking, $customerId, $draft->notes, $draft->notesLocale);

            return $booking->load(['vendors.items', 'vendors.vendor', 'address', 'customerNotes']);
        }, 3);
    }
}
