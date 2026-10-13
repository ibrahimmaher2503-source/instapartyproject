<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Application\Services\MailReadiness;
use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;
use App\Modules\Identity\Domain\Contracts\EmailRecipientReader;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

final class LaravelMailEmailAdapter implements NotificationChannelAdapter
{
    public function __construct(private readonly EmailRecipientReader $recipients) {}

    public function name(): string
    {
        return 'laravel_mail';
    }

    public function healthCheck(): ProviderHealthResult
    {
        return ProviderHealthResult::unreachable($this->name(), MailReadiness::configured()
            ? 'Settings present; delivery requires a recipient test.' : 'Mail transport is not configured for delivery.');
    }

    public function send(NotificationDispatch $dispatch): void
    {
        try {
            if (! MailReadiness::configured()) {
                throw new RuntimeException('MAIL_NOT_CONFIGURED');
            }
            $context = $dispatch->context ?? [];
            $recipient = $dispatch->is_test
                ? ($context['email'] ?? null)
                : $this->recipients->forUser((int) $dispatch->user_id);
            if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('MISSING_EMAIL');
            }
            $subject = (string) ($context['subject'] ?? config('app.name'));
            $body = (string) ($context['email_body'] ?? $context['body'] ?? '');
            $sent = Mail::raw($body, static fn (Message $message) => $message->to($recipient)->subject($subject));
            if ($sent === null) {
                throw new RuntimeException('MAIL_CANCELLED');
            }
            $reference = substr($sent->getMessageId(), 0, 255);
            $dispatch->forceFill([
                'status' => DispatchStatus::Sent, 'provider_name' => $this->name(), 'provider' => $this->name(),
                'provider_message_id' => $reference, 'provider_ref' => $reference,
                'provider_status' => 'transport_accepted', 'sent_at' => now(),
                'provider_error_code' => null, 'provider_error_message' => null, 'error_message' => null, 'next_retry_at' => null,
            ])->save();
        } catch (Throwable $exception) {
            // Transport errors may contain credentials or recipient data; retain only the error class.
            Log::warning('communication.email.delivery_failed', [
                'dispatch_public_id' => $dispatch->public_id, 'error_class' => class_basename($exception),
            ]);
            $dispatch->forceFill([
                'status' => DispatchStatus::Failed, 'provider_name' => $this->name(), 'provider' => $this->name(),
                'provider_status' => 'failed', 'provider_error_code' => class_basename($exception),
                'provider_error_message' => 'Email transport failed. Check protected server diagnostics.',
                'error_message' => 'Email transport failed.',
                'next_retry_at' => $dispatch->attempt_count < 5 ? now()->addMinutes(5 * (2 ** max(0, $dispatch->attempt_count - 1))) : null,
            ])->save();
        }
    }
}
