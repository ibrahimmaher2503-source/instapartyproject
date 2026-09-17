<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Listeners;

use App\Modules\Identity\Application\Actions\SendOtpAction;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendVendorRegistrationVerification implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    public function __construct(private readonly SendOtpAction $sendOtp) {}

    public function handle(VendorRegistered $event): void
    {
        if (config('services.otp.send_on_registration')) {
            $this->sendOtp->execute($event->vendorProfile->user->phone_e164);
        }
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }
}
