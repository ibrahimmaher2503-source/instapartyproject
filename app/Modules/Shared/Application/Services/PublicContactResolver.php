<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

final class PublicContactResolver
{
    private const PLACEHOLDER_EMAIL_SUFFIX = '.local';

    private const PLACEHOLDER_PHONES = [
        '+201000000000',
        '+20000000000',
    ];

    public function email(?string $value): ?string
    {
        foreach ([$value, config('storefront.public_contact.email')] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== ''
                && filter_var($candidate, FILTER_VALIDATE_EMAIL)
                && ! str_ends_with(strtolower($candidate), self::PLACEHOLDER_EMAIL_SUFFIX)) {
                return $candidate;
            }
        }

        return null;
    }

    public function phone(?string $value): ?string
    {
        foreach ([$value, config('storefront.public_contact.phone')] as $candidate) {
            $candidate = preg_replace('/[^+0-9]/', '', (string) $candidate) ?? '';

            if ($candidate !== ''
                && preg_match('/^\+[1-9][0-9]{7,14}$/', $candidate) === 1
                && ! in_array($candidate, self::PLACEHOLDER_PHONES, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
