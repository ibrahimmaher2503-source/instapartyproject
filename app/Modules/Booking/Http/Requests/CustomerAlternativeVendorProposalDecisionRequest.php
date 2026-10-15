<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use Illuminate\Foundation\Http\FormRequest;

final class CustomerAlternativeVendorProposalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = Booking::query()
            ->where('public_id', $this->route('bookingPublicId'))
            ->where('customer_id', $this->user()?->getAuthIdentifier())
            ->first();

        abort_if($booking === null, 404);

        $proposalExists = BookingAdminIntervention::query()
            ->where('booking_id', $booking->getKey())
            ->where('public_id', $this->route('interventionPublicId'))
            ->where('intervention_type', InterventionType::VendorProposal->value)
            ->exists();

        abort_if(! $proposalExists, 404);

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:accepted,rejected'],
            'offer_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
