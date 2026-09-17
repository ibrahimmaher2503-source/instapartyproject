<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Modules\Communication\Domain\Enums\ChatFlagType;

final class MaskModerationPattern
{
    public static function run(?string $value, ChatFlagType|string|null $type = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $kind = $type instanceof ChatFlagType ? $type->value : (string) $type;

        return match ($kind) {
            'phone' => self::maskPhone($value),
            'email' => self::maskEmail($value),
            'external_link' => '[redacted external link]',
            default => mb_substr($value, 0, 1).'***',
        };
    }

    private static function maskPhone(string $value): string
    {
        $digits = preg_replace('/\D+/u', '', $value) ?? '';

        if (strlen($digits) < 4) {
            return '***';
        }

        return '+'.str_repeat('*', max(0, strlen($digits) - 2)).substr($digits, -2);
    }

    private static function maskEmail(string $value): string
    {
        $at = strrpos($value, '@');

        if ($at === false) {
            return mb_substr($value, 0, 1).'***';
        }

        return mb_substr($value, 0, 1).'***'.substr($value, $at);
    }
}
