<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Reviews\Domain\ValueObjects\ReviewCursor;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Foundation\Http\FormRequest;

class ServiceEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'rental_duration_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'event_starts_at' => ['nullable', 'required_with:event_ends_at', 'date', 'after:now'],
            'event_ends_at' => ['nullable', 'date', 'after:event_starts_at'],
            'timezone' => ['required', 'timezone'],
            'review_cursor' => [
                'nullable',
                'string',
                'max:512',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && ReviewCursor::fromEncoded($value) === null) {
                        $fail(__('storefront.service.review_cursor_invalid'));
                    }
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $timezone = $this->input('timezone', 'Africa/Cairo');
        $this->merge(['timezone' => $timezone]);
        if (! is_string($timezone) || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }
        foreach (['event_starts_at', 'event_ends_at'] as $field) {
            if ($this->filled($field) && is_string($this->input($field))) {
                try {
                    $this->merge([$field => Carbon::parse($this->input($field), $timezone)->utc()->toIso8601String()]);
                } catch (InvalidFormatException) {
                    // Keep invalid input for the date validation rule.
                }
            }
        }
    }
}
