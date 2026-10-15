<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

final class SafeAuditChanges
{
    /** @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    public static function redact(array $changes, ?string $settingKey = null): array
    {
        // Deleted subjects can retain their setting key in only one before/after sibling.
        foreach ([$changes, $changes['old'] ?? null, $changes['attributes'] ?? null, $changes['new'] ?? null] as $context) {
            $context = is_string($context) ? json_decode($context, true) : $context;
            $key = is_array($context) ? ($context['key'] ?? null) : null;
            if (! is_string($key)) {
                continue;
            }
            if (self::isSensitive($key)) {
                $settingKey = $key;
                break;
            }
            $settingKey ??= $key;
        }
        $sensitiveSetting = $settingKey !== null && self::isSensitive($settingKey);
        foreach ($changes as $key => $value) {
            if (self::isSensitive((string) $key) || ($key === 'value' && $sensitiveSetting)) {
                $changes[$key] = __('shared::operations.redacted');

                continue;
            }
            if (is_string($value) && in_array(substr(ltrim($value), 0, 1), ['{', '['], true)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }
            $changes[$key] = is_array($value) ? self::redact($value, $settingKey) : $value;
        }

        return $changes;
    }

    private static function isSensitive(string $key): bool
    {
        return preg_match('/password|secret|token|api.?key|private.?key|authorization|credential|bank|iban|national.?id|tax.?id/i', $key) === 1;
    }
}
