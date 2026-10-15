<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\ActivityLog;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class ActivityLogPresenter
{
    /** @var array<int, string> */
    private const SAFE_FIELDS = [
        'name', 'business_name', 'email', 'phone_e164', 'preferred_locale', 'timezone', 'status',
        'approval_status', 'product_type', 'lifecycle_status', 'payment_status', 'fulfillment_status',
        'reference_no', 'total_minor', 'total_currency', 'amount_minor', 'amount_currency',
        'event_starts_at', 'event_ends_at', 'guest_count', 'subject', 'description', 'enabled', 'rollout_pct',
        'website_url', 'facebook_url', 'instagram_url', 'slug', 'locale', 'timezone_name',
    ];

    public static function recordLabel(Activity $activity): string
    {
        $subject = $activity->subject;
        if (! $subject instanceof Model) {
            return __('shared::admin-feedback.legacy_unknown');
        }

        foreach (['reference_no', 'public_id', 'name', 'business_name', 'subject'] as $attribute) {
            $value = $subject->getAttribute($attribute);
            if (is_array($value)) {
                $value = $value[app()->getLocale()] ?? $value['en'] ?? reset($value);
            }
            if (is_string($value) && trim($value) !== '') {
                return class_basename($subject).' · '.trim($value);
            }
        }

        return class_basename($subject).' · '.__('shared::admin-feedback.record_unavailable');
    }

    public static function recordUrl(Activity $activity): ?string
    {
        $subject = $activity->subject;
        if (! $subject instanceof Model || ! filled($subject->getAttribute('public_id'))) {
            return null;
        }

        $panel = Filament::getCurrentPanel();
        if ($panel === null) {
            return null;
        }

        foreach ($panel->getResources() as $resource) {
            if ($resource::getModel() !== $subject::class || ! array_key_exists('view', $resource::getPages())) {
                continue;
            }

            try {
                if (Gate::allows('view', $subject) && $resource::canView($subject)) {
                    return $resource::getUrl('view', ['record' => $subject]);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /** @return array{before: array<string, string>, after: array<string, string>} */
    public static function safeChanges(Activity $activity): array
    {
        $properties = $activity->properties?->toArray() ?? [];
        $before = is_array($properties['old'] ?? null) ? $properties['old'] : [];
        $after = is_array($properties['attributes'] ?? null)
            ? $properties['attributes']
            : (is_array($properties['new'] ?? null) ? $properties['new'] : []);

        if ($before === [] && $after === []) {
            $after = $properties;
        }

        $fields = array_intersect(self::SAFE_FIELDS, array_unique(array_merge(array_keys($before), array_keys($after))));
        $safeBefore = [];
        $safeAfter = [];

        foreach ($fields as $field) {
            if (self::isSensitiveField((string) $field)) {
                continue;
            }

            if (array_key_exists($field, $before)) {
                $safeBefore[$field] = self::displayValue($before[$field]);
            }
            if (array_key_exists($field, $after)) {
                $safeAfter[$field] = self::displayValue($after[$field]);
            }
        }

        return ['before' => $safeBefore, 'after' => $safeAfter];
    }

    public static function safeChangesSummary(Activity $activity): string
    {
        $changes = self::safeChanges($activity);
        $fields = array_unique(array_merge(array_keys($changes['before']), array_keys($changes['after'])));
        if ($fields === []) {
            return __('shared::admin-feedback.no_safe_changes');
        }

        return collect($fields)
            ->map(function (string $field) use ($changes): string {
                $before = $changes['before'][$field] ?? __('shared::admin-feedback.not_recorded');
                $after = $changes['after'][$field] ?? __('shared::admin-feedback.not_recorded');

                return __('shared::admin-feedback.change_pair', [
                    'field' => __("shared::admin-feedback.audit_fields.{$field}"),
                    'before' => $before,
                    'after' => $after,
                ]);
            })
            ->implode("\n");
    }

    private static function isSensitiveField(string $field): bool
    {
        return preg_match('/password|secret|token|credential|bank|iban|swift|tax_id|national_id|otp|private/i', $field) === 1;
    }

    private static function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? __('shared::admin-feedback.yes') : __('shared::admin-feedback.no');
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '—';
        }

        if (is_scalar($value) || $value === null) {
            return (string) ($value ?? '—');
        }

        return __('shared::admin-feedback.value_unavailable');
    }
}
