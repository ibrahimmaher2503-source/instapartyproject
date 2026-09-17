<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Requests\StorefrontBookingRequest;

it('normalizes browser-local booking times to UTC', function (): void {
    $request = new class extends StorefrontBookingRequest
    {
        public function normalize(): void
        {
            $this->prepareForValidation();
        }
    };

    $request->replace([
        'timezone' => 'Africa/Cairo',
        'event_starts_at' => '2026-10-01T20:30',
        'event_ends_at' => '2026-10-01T22:30',
    ]);
    $request->normalize();

    expect($request->input('event_starts_at'))->toBe('2026-10-01T17:30:00+00:00')
        ->and($request->input('event_ends_at'))->toBe('2026-10-01T19:30:00+00:00');
});
