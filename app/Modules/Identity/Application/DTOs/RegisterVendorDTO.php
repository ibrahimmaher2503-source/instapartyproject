<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTOs;

use App\Modules\Identity\Application\Services\VendorRegistrationRules;

final readonly class RegisterVendorDTO
{
    /** @param array{en: string, ar: string} $businessName */
    public function __construct(
        public string $name,
        public string $phoneE164,
        public string $email,
        public string $password,
        public string $passwordConfirmation,
        public array $businessName,
        public string $businessType,
        public int $primaryGovernorateId,
        public int $primaryCityId,
        public string $preferredLocale,
    ) {}

    public static function fromArray(array $data): self
    {
        $data = VendorRegistrationRules::normalize($data);

        return new self(
            name: trim((string) $data['name']),
            phoneE164: (string) $data['phone_e164'],
            email: (string) $data['email'],
            password: (string) $data['password'],
            passwordConfirmation: (string) ($data['password_confirmation'] ?? $data['passwordConfirmation'] ?? ''),
            businessName: $data['business_name'],
            businessType: (string) $data['business_type'],
            primaryGovernorateId: (int) $data['primary_governorate_id'],
            primaryCityId: (int) $data['primary_city_id'],
            preferredLocale: (string) ($data['preferred_locale'] ?? 'ar'),
        );
    }

    public function validationData(): array
    {
        return [
            'name' => $this->name,
            'phone_e164' => $this->phoneE164,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
            'business_name' => $this->businessName,
            'business_type' => $this->businessType,
            'primary_governorate_id' => $this->primaryGovernorateId,
            'primary_city_id' => $this->primaryCityId,
            'preferred_locale' => $this->preferredLocale,
        ];
    }
}
