<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Domain\Enums\BusinessType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class VendorRegistrationRules
{
    public const PHONE_E164_REGEX = '/^\+[1-9]\d{7,14}$/';

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $data['email'] = isset($data['email']) ? mb_strtolower(trim((string) $data['email'])) : null;
        $data['phone_e164'] = is_string($data['phone_e164'] ?? null)
            ? preg_replace('/[\s\-()]/', '', $data['phone_e164'])
            : ($data['phone_e164'] ?? null);

        foreach (['facebook_url', 'website_url', 'instagram_url'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $value = trim($data[$field]);
                $data[$field] = $value === '' ? null : $value;
            }
        }

        return $data;
    }

    /** @return array<string, array<int, mixed>> */
    public static function rules(?int $governorateId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_e164' => ['required', 'string', 'regex:'.self::PHONE_E164_REGEX, Rule::unique('users', 'phone_e164')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'business_name' => ['required', 'array'],
            'business_name.en' => ['required', 'string', 'max:255'],
            'business_name.ar' => ['required', 'string', 'max:255'],
            'business_type' => ['required', 'string', Rule::in(array_column(BusinessType::cases(), 'value'))],
            'primary_governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'primary_city_id' => [
                'nullable',
                'integer',
                Rule::exists('cities', 'id')->where(
                    fn ($query) => $query->where('governorate_id', $governorateId)
                ),
            ],
            'facebook_url' => ['nullable', 'url', 'max:2048'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'instagram_url' => ['nullable', 'url', 'max:2048'],
            'preferred_locale' => ['nullable', 'string', 'in:en,ar'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'phone_e164.required' => __('identity::identity.validation.phone_required'),
            'phone_e164.string' => __('identity::identity.validation.phone_e164'),
            'phone_e164.regex' => __('identity::identity.validation.phone_e164'),
            'phone_e164.unique' => __('identity::identity.validation.duplicate_phone'),
            'email.unique' => __('identity::identity.validation.duplicate_email'),
            'primary_city_id.exists' => __('identity::identity.validation.city_governorate_mismatch'),
            'password.confirmed' => __('identity::identity.validation.password_confirmation'),
            'facebook_url.url' => __('vendor-portal.profile.invalid_url'),
            'website_url.url' => __('vendor-portal.profile.invalid_url'),
            'instagram_url.url' => __('vendor-portal.profile.invalid_url'),
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'name' => __('identity::identity.fields.name'),
            'email' => __('identity::identity.fields.email'),
            'phone_e164' => __('identity::identity.fields.phone'),
            'password' => __('identity::identity.fields.password'),
            'password_confirmation' => __('identity::identity.fields.password_confirmation'),
            'business_name.en' => __('identity::identity.forms.business_name_en'),
            'business_name.ar' => __('identity::identity.forms.business_name_ar'),
            'business_type' => __('identity::identity.fields.business_type'),
            'primary_governorate_id' => __('identity::identity.fields.governorate_id'),
            'primary_city_id' => __('identity::identity.fields.city_id'),
            'facebook_url' => __('identity::identity.fields.facebook_url'),
            'website_url' => __('identity::identity.fields.website_url'),
            'instagram_url' => __('identity::identity.fields.instagram_url'),
        ];
    }
}
