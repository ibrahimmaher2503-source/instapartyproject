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
        ?string $packagePublicId = null,
    ): Booking {
        // Retry the outer unit so nested item-inventory checks receive a fresh transaction snapshot.
        return DB::transaction(function () use ($customerId, $draft, $servicePublicId, $quantity, $packagePublicId): Booking {
            $serviceId = $this->catalogReader->resolvePublicId($servicePublicId);
            if ($serviceId === null || $this->catalogReader->findPublishedById($serviceId) === null) {
                throw ValidationException::withMessages(['service_id' => __('storefront.service.add_error')]);
            }

            if (! $this->catalogReader->isServiceEligibleForOccasion($serviceId, $draft->occasionId)) {
                throw ValidationException::withMessages([
                    'service_id' => __('storefront.wizard.validation.service_occasion_mismatch'),
                ]);
            }

            $themeSnapshot = null;
            $selectedThemePublicId = data_get($draft->theme, 'public_id');
            if ($selectedThemePublicId !== null) {
                $selectedTheme = is_string($selectedThemePublicId)
                    ? $this->catalogReader->findActiveThemeForService($servicePublicId, $selectedThemePublicId)
                    : null;
                if ($selectedTheme === null || $selectedTheme->snapshot() !== $draft->theme) {
                    throw ValidationException::withMessages([
                        'theme_public_id' => __('storefront.wizard.validation.theme_invalid'),
                    ]);
                }

                $themeSnapshot = $selectedTheme->snapshot();
            }

            $booking = Booking::query()
                ->where('customer_id', $customerId)
                ->whereState('lifecycle_status', DraftState::class)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($booking !== null && (int) $booking->occasion_id !== $draft->occasionId) {
                if ($booking->items()->exists()) {
                    throw ValidationException::withMessages([
                        'service_id' => __('storefront.wizard.validation.draft_occasion_mismatch'),
                    ]);
                }

                $booking = null;
            }

            if ($booking === null) {
                $booking = $this->createDraft->execute($draft);
            } elseif ($themeSnapshot !== null) {
                $booking->update(['theme' => $themeSnapshot]);
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
                    packagePublicId: $packagePublicId,
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
