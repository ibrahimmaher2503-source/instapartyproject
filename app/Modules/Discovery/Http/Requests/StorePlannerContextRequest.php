<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Requests;

use App\Modules\Geography\Domain\Contracts\GeographyRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePlannerContextRequest extends FormRequest
{
    public function attributes(): array
    {
        return [
            'guest_count' => __('storefront.wizard.step3.guests_label'),
            'notes' => __('storefront.wizard.step3.notes_label'),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'occasion' => ['required', 'string', 'exists:occasions,code'],
            'governorate_public_id' => ['required', 'string', 'exists:governorates,public_id'],
            'city_public_id' => ['required', 'string', 'exists:cities,public_id'],
            'event_starts_at' => ['required', 'date'],
            'event_ends_at' => ['nullable', 'date', 'after:event_starts_at'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'vendor' => ['nullable', 'string', 'max:26'],
            'timezone' => ['nullable', 'timezone'],
        ];
    }

    public function messages(): array
    {
        return [
            'guest_count.integer' => __('storefront.wizard.validation.guest_count_range'),
            'guest_count.min' => __('storefront.wizard.validation.guest_count_range'),
            'guest_count.max' => __('storefront.wizard.validation.guest_count_range'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $timezone = $this->string('timezone')->toString() ?: (string) config('app.timezone', 'UTC');
            $startsAt = CarbonImmutable::parse($this->input('event_starts_at'), $timezone)->utc();

            if (! $startsAt->isAfter(CarbonImmutable::now('UTC'))) {
                $validator->errors()->add('event_starts_at', __('storefront.wizard.validation.times_invalid'));
            }

            if ($this->filled('event_ends_at') && ! CarbonImmutable::parse($this->input('event_ends_at'), $timezone)->utc()->isAfter($startsAt)) {
                $validator->errors()->add('event_ends_at', __('storefront.wizard.validation.times_invalid'));
            }

            $geography = app(GeographyRepository::class);
            $governorate = $geography->findGovernorateByPublicId((string) $this->input('governorate_public_id'));
            $city = $geography->findCityByPublicId((string) $this->input('city_public_id'));
            if ($governorate === null || $city === null || $city->governorate_id !== $governorate->id) {
                $validator->errors()->add('city_public_id', __('storefront.wizard.validation.city_mismatch'));
            }
        }];
    }
}
