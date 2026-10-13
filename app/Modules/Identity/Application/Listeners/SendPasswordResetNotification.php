<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Listeners;

use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Events\PasswordResetRequested;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPasswordResetNotification implements ShouldBeEncrypted, ShouldQueue
{
    public string $queue = 'notifications';

    public bool $afterCommit = true;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(private readonly OtpGatewayInterface $smsGateway) {}

    public function handle(PasswordResetRequested $event): void
    {
        if ($event->channel === 'email') {
            $this->sendEmail($event->identifier, $event->tokenPlain, $event->locale);

            return;
        }

        $this->smsGateway->send($event->identifier, $event->tokenPlain);
    }

    private function sendEmail(string $email, string $token, string $locale): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $resetUrl = "{$appUrl}/{$locale}/auth/reset?token={$token}&identifier=".urlencode($email);

        try {
            Mail::raw(
                __('auth.password_reset.email_body', ['url' => $resetUrl], $locale),
                function ($message) use ($email, $locale): void {
                    $message->to($email)
                        ->subject(__('auth.password_reset.email_subject', [], $locale));
                },
            );
        } catch (Throwable $e) {
            Log::warning('password-reset.email.failed', [
                'error_class' => class_basename($e),
            ]);
            throw $e;
        }
    }
}
