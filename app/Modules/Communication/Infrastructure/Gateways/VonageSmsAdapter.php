<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vonage\Client;
use Vonage\SMS\Message\SMS;

class VonageSmsAdapter implements NotificationChannelAdapter
{
    public function __construct(
        private readonly Client $vonageClient,
    ) {}

    public function name(): string
    {
        return 'vonage_sms';
    }

    public function healthCheck(): ProviderHealthResult
    {
        $start = hrtime(true);
        try {
            $balance = $this->vonageClient->account()->getBalance();
            $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

            return ProviderHealthResult::reachable(
                'vonage_sms',
                $latencyMs,
                'Balance: EUR '.number_format((float) $balance->getBalance(), 2)
            );
        } catch (Throwable $e) {
            return ProviderHealthResult::unreachable('vonage_sms', class_basename($e).': '.$e->getCode());
        }
    }

    public function send(NotificationDispatch $dispatch): void
    {
        $context = $dispatch->context ?? [];
        $to = $context['phone_e164'] ?? null;
        $body = $context['sms_body'] ?? $context['body'] ?? '';

        if (! $to) {
            $dispatch->status = DispatchStatus::Failed;
            $dispatch->provider_name = $this->name();
            $dispatch->provider_error_code = 'MISSING_PHONE';
            $dispatch->provider_error_message = 'No phone_e164 in context';
            $dispatch->error_message = 'No phone_e164 in context';
            $dispatch->next_retry_at = null;
            $dispatch->save();

            return;
        }

        try {
            $message = new SMS($to, config('services.vonage.sms_from', 'InstaParty'), $body);
            $response = $this->vonageClient->sms()->send($message);
            $sent = $response->current();

            if ($sent->getStatus() === 0) {
                $dispatch->status = DispatchStatus::Sent;
                $dispatch->provider_name = $this->name();
                $dispatch->provider_message_id = substr($sent->getMessageId(), 0, 255);
                $dispatch->provider_status = '0';
                $dispatch->sent_at = now();
                // Legacy dual-write
                $dispatch->provider = $this->name();
                $dispatch->provider_ref = $sent->getMessageId();
            } else {
                $dispatch->status = DispatchStatus::Failed;
                $dispatch->provider_name = $this->name();
                $dispatch->provider_error_code = substr((string) $sent->getStatus(), 0, 60);
                $dispatch->provider_error_message = 'Vonage status: '.$sent->getStatus();
                $dispatch->error_message = 'Vonage status: '.$sent->getStatus();
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
            Log::error('VonageSmsAdapter error', ['dispatch_id' => $dispatch->id, 'error' => $e->getMessage()]);
        }
    }
}
