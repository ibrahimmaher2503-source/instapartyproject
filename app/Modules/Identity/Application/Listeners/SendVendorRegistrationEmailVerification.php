<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Listeners;

use App\Modules\Identity\Domain\Events\VendorRegistered;
use Filament\Facades\Filament;
use Filament\Notifications\Auth\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendVendorRegistrationEmailVerification implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    public function handle(VendorRegistered $event): void
    {
        $user = $event->vendorProfile->user;

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $notification = app(VerifyEmail::class);
            $notification->url = Filament::getPanel('vendor')->getVerifyEmailUrl($user);
            $user->notify($notification);
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }
}
