<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Application\Services\MailReadiness;
use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

final class LaravelMailEmailAdapter implements NotificationChannelAdapter
{
    public function name(): string { return 'laravel_mail'; }

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
            $recipient = $context['email'] ?? null;
            if (! is_string($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('MISSING_EMAIL');
            }
            $subject = (string) ($context['subject'] ?? config('app.name'));
            $body = (string) ($context['email_body'] ?? $context['body'] ?? '');
            $sent = Mail::raw($body, static fn (Message $message) => $message->to($recipient)->subject($subject));
            if ($sent === null) {
                throw new RuntimeException('MAIL_CANCELLED');
            }
            $dispatch->forceFill([
                'status' => DispatchStatus::Sent, 'provider_name' => $this->name(), 'provider' => $this->name(),
                'provider_message_id' => $sent->getMessageId(), 'provider_ref' => $sent->getMessageId(),
                'provider_status' => 'transport_accepted', 'sent_at' => now(),
                'provider_error_code' => null, 'provider_error_message' => null, 'error_message' => null, 'next_retry_at' => null,
            ])->save();
        } catch (Throwable $exception) {
            // Transport errors may contain credentials or recipient data; retain only the error class.
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
