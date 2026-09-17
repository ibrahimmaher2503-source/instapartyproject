<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Contracts\SmsProviderInterface;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;
use Throwable;

final class SmsMisrAdapter implements NotificationChannelAdapter
{
    public function __construct(private readonly SmsProviderInterface $provider) {}

    public function name(): string
    {
        return 'sms_misr';
    }

    public function healthCheck(): ProviderHealthResult
    {
        try {
            $this->provider->checkBalance();

            return ProviderHealthResult::reachable($this->name(), 0, 'SMS Misr balance endpoint responded.');
        } catch (Throwable $e) {
            return ProviderHealthResult::unreachable($this->name(), class_basename($e));
        }
    }

    public function send(NotificationDispatch $dispatch): void
    {
        $context = (array) ($dispatch->context ?? []);
        $mobile = $context['phone_e164'] ?? null;
        $message = $context['sms_body'] ?? $context['body'] ?? null;

        if (blank($mobile) || blank($message)) {
            $this->fail($dispatch, 'MISSING_SMS_DATA', 'SMS mobile or message is missing.');

            return;
        }

        try {
            $result = $this->provider->send((string) $mobile, (string) $message, $this->language($dispatch->locale));
            $code = (string) ($result['code'] ?? '');

            if ($code !== '' && ! in_array($code, ['1901', '1902'], true)) {
                $this->fail($dispatch, $code, 'SMS Misr rejected the message.');

                return;
            }

            $dispatch->update([
                'status' => DispatchStatus::Sent,
                'provider_name' => $this->name(),
                'provider_message_id' => isset($result['SMSID']) ? substr((string) $result['SMSID'], 0, 255) : null,
                'provider_status' => $code ?: 'ok',
                'sent_at' => now(),
                'provider' => $this->name(),
            ]);
        } catch (Throwable $e) {
            $this->fail($dispatch, class_basename($e), $e->getMessage());
        }
    }

    private function language(string $locale): int
    {
        return $locale === 'ar' ? 2 : 1;
    }

    private function fail(NotificationDispatch $dispatch, string $code, string $message): void
    {
        $dispatch->update([
            'status' => DispatchStatus::Failed,
            'provider_name' => $this->name(),
            'provider_error_code' => substr($code, 0, 60),
            'provider_error_message' => substr($message, 0, 1000),
            'error_message' => substr($message, 0, 1000),
            'next_retry_at' => null,
        ]);
    }
}
