<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Listeners;

use App\Modules\Communication\Domain\Contracts\EmailTransportReadiness;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use Filament\Facades\Filament;
use Filament\Notifications\Auth\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class SendVendorRegistrationEmailVerification implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    public function __construct(private readonly EmailTransportReadiness $emailReadiness) {}

    public function handle(VendorRegistered $event): void
    {
        $user = $event->vendorProfile->user;

        if ($user !== null && ! $user->hasVerifiedEmail()) {
            $this->ensureEmailTransportReady();
            $notification = app(VerifyEmail::class);
            $notification->url = Filament::getPanel('vendor')->getVerifyEmailUrl($user);

            try {
                $user->notify($notification);
            } catch (Throwable $exception) {
                Log::warning('vendor-registration.email.failed', [
                    'error_class' => class_basename($exception),
                ]);

                throw new RuntimeException('EMAIL_DELIVERY_FAILED');
            }
        }
    }

    public function failed(VendorRegistered $event, Throwable $exception): void
    {
        $failureCode = in_array($exception->getMessage(), ['MAIL_NOT_CONFIGURED', 'EMAIL_DELIVERY_FAILED'], true)
            ? $exception->getMessage()
            : 'UNEXPECTED_FAILURE';

        Log::critical('vendor-registration.email.exhausted', [
            'failure_code' => $failureCode,
            'error_class' => class_basename($exception),
        ]);
    }

    private function ensureEmailTransportReady(): void
    {
        if (! app()->environment(['local', 'testing']) && ! $this->emailReadiness->isConfigured()) {
            Log::warning('vendor-registration.email.unavailable', [
                'failure_code' => 'MAIL_NOT_CONFIGURED',
            ]);

            throw new RuntimeException('MAIL_NOT_CONFIGURED');
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
