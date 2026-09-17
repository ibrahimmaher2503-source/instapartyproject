<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Application\Services\ProviderSettingsResolver;
use App\Modules\Communication\Domain\Contracts\SmsProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class SmsMisrProvider implements SmsProviderInterface
{
    private const SMS_ENDPOINT = 'https://smsmisr.com/api/SMS/';

    private const OTP_ENDPOINT = 'https://smsmisr.com/api/OTP/';

    private const BALANCE_ENDPOINT = 'https://smsmisr.com/api/Balance/';

    public function __construct(private readonly ProviderSettingsResolver $settings) {}

    public function send(string $mobile, string $message, int $language = 1): array
    {
        return $this->post(self::SMS_ENDPOINT, [
            'mobile' => $mobile,
            'language' => (string) $language,
            'message' => $message,
        ]);
    }

    public function sendOtp(string $mobile, string $template, string $otp): array
    {
        return $this->post(self::OTP_ENDPOINT, [
            'mobile' => $mobile,
            'template' => $template,
            'otp' => $otp,
        ]);
    }

    public function checkBalance(): array
    {
        return $this->post(self::BALANCE_ENDPOINT);
    }

    /** @param array<string, string> $payload */
    private function post(string $endpoint, array $payload = []): array
    {
        $resolved = $this->settings->smsMisr();

        if (! $resolved['enabled']) {
            throw new RuntimeException('SMS Misr is disabled.');
        }

        if (blank($resolved['username']) || blank($resolved['password']) || blank($resolved['sender'])) {
            throw new RuntimeException('SMS Misr is not configured.');
        }

        $response = $this->request()->asForm()->post($endpoint, array_merge([
            'environment' => (string) $resolved['environment'],
            'username' => (string) $resolved['username'],
            'password' => (string) $resolved['password'],
            'sender' => (string) $resolved['sender'],
        ], $payload));

        $response->throw();
        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('SMS Misr returned an invalid response.');
        }

        return $data;
    }

    private function request(): PendingRequest
    {
        return Http::timeout((int) config('services.sms_misr.timeout', 30))
            ->retry(2, 250, throw: false)
            ->acceptJson();
    }
}
