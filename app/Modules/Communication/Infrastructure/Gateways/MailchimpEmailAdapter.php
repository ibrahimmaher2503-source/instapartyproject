<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;
use Illuminate\Support\Facades\Log;
use MailchimpMarketing\ApiClient;
use Throwable;

class MailchimpEmailAdapter implements NotificationChannelAdapter
{
    public function __construct(
        private readonly ApiClient $mailchimp,
    ) {}

    public function name(): string
    {
        return 'mailchimp_email';
    }

    public function healthCheck(): ProviderHealthResult
    {
        $start = hrtime(true);
        try {
            $response = $this->mailchimp->ping->get();
            $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);
            $health = $response->health_status ?? 'unknown';

            return ProviderHealthResult::reachable('mailchimp_email', $latencyMs, "Health: {$health}");
        } catch (Throwable $e) {
            return ProviderHealthResult::unreachable('mailchimp_email', class_basename($e));
        }
    }

    public function send(NotificationDispatch $dispatch): void
    {
        $context = $dispatch->context ?? [];
        $to = $context['email'] ?? null;
        $subject = $context['subject'] ?? '';
        $body = $context['email_body'] ?? $context['body'] ?? '';

        if (! $to) {
            $dispatch->status = DispatchStatus::Failed;
            $dispatch->provider_name = $this->name();
            $dispatch->provider_error_code = 'MISSING_EMAIL';
            $dispatch->provider_error_message = 'No email in context';
            $dispatch->error_message = 'No email in context';
            $dispatch->next_retry_at = null;
            $dispatch->save();

            return;
        }

        try {
            $response = $this->mailchimp->messages->send([
                'message' => [
                    'html' => $body,
                    'subject' => $subject,
                    'from_email' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                    'to' => [['email' => $to, 'type' => 'to']],
                ],
            ]);

            $result = $response[0] ?? null;
            $status = $result['status'] ?? 'error';

            if (in_array($status, ['sent', 'queued'], true)) {
                $dispatch->status = DispatchStatus::Sent;
                $dispatch->provider_name = $this->name();
                $dispatch->provider_message_id = substr((string) ($result['_id'] ?? ''), 0, 255) ?: null;
                $dispatch->provider_status = substr($status, 0, 60);
                $dispatch->sent_at = now();
                // Legacy dual-write
                $dispatch->provider = $this->name();
                $dispatch->provider_ref = $result['_id'] ?? null;
            } else {
                $errorMsg = 'Mailchimp status: '.$status.' — '.($result['reject_reason'] ?? '');
                $dispatch->status = DispatchStatus::Failed;
                $dispatch->provider_name = $this->name();
                $dispatch->provider_error_code = substr($result['reject_reason'] ?? $status, 0, 60);
                $dispatch->provider_error_message = $errorMsg;
                $dispatch->error_message = $errorMsg;
                $dispatch->next_retry_at = $dispatch->attempt_count < 5
                    ? now()->addMinutes(5 * (2 ** ($dispatch->attempt_count - 1)))
                    : null;
            }

            $dispatch->save();
        } catch (Throwable $e) {
            $dispatch->status = DispatchStatus::Failed;
            $dispatch->provider_name = $this->name();
            $dispatch->provider_error_code = substr(class_basename($e), 0, 60);
            $dispatch->provider_error_message = $e->getMessage();
            $dispatch->error_message = $e->getMessage();
            $dispatch->next_retry_at = $dispatch->attempt_count < 5
                ? now()->addMinutes(5 * (2 ** ($dispatch->attempt_count - 1)))
                : null;
            $dispatch->save();
            Log::error('MailchimpEmailAdapter error', ['dispatch_id' => $dispatch->id, 'error' => $e->getMessage()]);
        }
    }
}
