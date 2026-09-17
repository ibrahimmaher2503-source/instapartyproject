<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

interface SmsProviderInterface
{
    /** @return array<string, mixed> */
    public function send(string $mobile, string $message, int $language = 1): array;

    /** @return array<string, mixed> */
    public function sendOtp(string $mobile, string $template, string $otp): array;

    /** @return array<string, mixed> */
    public function checkBalance(): array;
}
