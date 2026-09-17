<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

final class StorefrontText
{
    public function translation(mixed $model, string $field, ?string $locale = null): string
    {
        if (! is_object($model) || ! method_exists($model, 'getTranslation')) {
            return '';
        }

        $locale ??= app()->getLocale();
        $value = (string) $model->getTranslation($field, $locale, useFallbackLocale: false);

        if ($this->isUnavailable($value) && $locale !== 'en') {
            $value = (string) $model->getTranslation($field, 'en', useFallbackLocale: false);
        }

        return $this->isUnavailable($value) ? '' : $value;
    }

    private function isUnavailable(string $value): bool
    {
        return trim($value) === '' || preg_match('/[\p{L}\p{N}]/u', $value) !== 1;
    }
}
