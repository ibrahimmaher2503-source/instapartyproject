<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Support;

final class MaskBankData
{
    /** @var list<string> */
    public const FIELDS = [
        'bank_name',
        'bank_account_holder',
        'bank_iban',
        'bank_swift_bic',
        'bank_branch',
    ];

    public static function forField(string $field, mixed $value): string
    {
        $value = is_string($value) ? $value : null;

        return $field === 'bank_iban' ? self::iban($value) : self::label($value);
    }

    public static function label(?string $value): string
    {
        return $value === null || $value === '' ? '—' : mb_substr($value, 0, 1).'***';
    }

    public static function iban(?string $value): string
    {
        $iban = preg_replace('/\s+/', '', (string) $value) ?? '';

        return strlen($iban) <= 7 ? ($iban === '' ? '—' : '***') : substr($iban, 0, 4).'••••'.substr($iban, -3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public static function snapshot(array $attributes): array
    {
        $masked = [];

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $masked[$field] = self::forField($field, $attributes[$field]);
            }
        }

        return $masked;
    }
}
