<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Application\DTOs\CreateBookingDraftDTO;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StorefrontBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'string', 'exists:services,public_id'],
            'occasion' => ['required', 'string', 'exists:occasions,code'],
            'event_starts_at' => ['required', 'date', 'after:now'],
            'event_ends_at' => ['required', 'date', 'after:event_starts_at'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'celebrant_name' => ['nullable', 'string', 'max:120'],
            'address' => ['required', 'array'],
            'address.city_id' => ['required', 'string', 'exists:cities,public_id'],
            'address.address_line' => ['required', 'string', 'max:255'],
            'address.building' => ['nullable', 'string', 'max:80'],
            'address.floor' => ['nullable', 'string', 'max:20'],
            'address.apartment' => ['nullable', 'string', 'max:20'],
            'address.landmark' => ['nullable', 'string', 'max:255'],
            'address.recipient_name' => ['required', 'string', 'max:120'],
            'address.recipient_phone_e164' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'timezone' => ['nullable', 'timezone'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('timezone')) {
            return;
        }

        $timezone = $this->string('timezone')->toString();

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }

        $dates = [];

        foreach (['event_starts_at', 'event_ends_at'] as $field) {
            if ($this->filled($field)) {
                $dates[$field] = Carbon::parse($this->input($field), $timezone)->utc()->toIso8601String();
            }
        }

        $this->merge($dates);
    }

    public function toDraftDTO(): CreateBookingDraftDTO
    {
        $user = $this->user();
        abort_unless($user !== null, 401);

        return new CreateBookingDraftDTO(
            customerId: (int) $user->id,
            occasionId: (int) DB::table('occasions')->where('code', $this->string('occasion')->toString())->value('id'),
            eventStartsAt: Carbon::parse($this->input('event_starts_at')),
            eventEndsAt: Carbon::parse($this->input('event_ends_at')),
            guestCount: $this->integer('guest_count') ?: null,
            theme: null,
            celebrantName: $this->input('celebrant_name'),
            celebrantDob: null,
            celebrantGender: null,
            addressCityId: (int) DB::table('cities')->where('public_id', $this->input('address.city_id'))->value('id'),
            addressLine: (string) $this->input('address.address_line'),
            addressBuilding: $this->input('address.building'),
            addressFloor: $this->input('address.floor'),
            addressApartment: $this->input('address.apartment'),
            addressLandmark: $this->input('address.landmark'),
            addressLatitude: $this->filled('address.latitude') ? (float) $this->input('address.latitude') : null,
            addressLongitude: $this->filled('address.longitude') ? (float) $this->input('address.longitude') : null,
            recipientName: (string) $this->input('address.recipient_name'),
            recipientPhoneE164: (string) $this->input('address.recipient_phone_e164'),
        );
    }

    public function servicePublicId(): string
    {
        return (string) $this->input('service_id');
    }

    public function quantity(): int
    {
        return max(1, (int) ($this->input('quantity') ?: 1));
    }
}
