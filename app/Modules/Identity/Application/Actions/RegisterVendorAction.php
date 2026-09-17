<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTOs\RegisterVendorDTO;
use App\Modules\Identity\Application\Services\VendorRegistrationRules;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RegisterVendorAction
{
    public function execute(RegisterVendorDTO $dto): VendorProfile
    {
        Validator::make(
            $dto->validationData(),
            VendorRegistrationRules::rules($dto->primaryGovernorateId),
            VendorRegistrationRules::messages(),
        )->setAttributeNames(VendorRegistrationRules::attributes())->validate();

        try {
            return DB::transaction(function () use ($dto): VendorProfile {
                $user = User::query()->create([
                    'name' => $dto->name,
                    'phone_e164' => $dto->phoneE164,
                    'email' => $dto->email,
                    'password' => Hash::make($dto->password),
                    'preferred_locale' => $dto->preferredLocale,
                    'status' => 'active',
                    'timezone' => 'Africa/Cairo',
                    'numeral_system' => 'western',
                ]);

                $user->assignRole('vendor');

                $vendorProfile = VendorProfile::query()->create([
                    'user_id' => $user->id,
                    'business_name' => $dto->businessName,
                    'slug' => $this->uniqueSlug($dto->businessName['en']),
                    'business_type' => $dto->businessType,
                    'primary_governorate_id' => $dto->primaryGovernorateId,
                    'primary_city_id' => $dto->primaryCityId,
                    'approval_status' => ApprovalStatus::Pending->value,
                ]);

                DB::afterCommit(function () use ($vendorProfile): void {
                    try {
                        event(new VendorRegistered($vendorProfile));
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });

                return $vendorProfile->load(['user', 'documents', 'approvedTypes']);
            }, 3);
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw $exception;
            }

            $message = mb_strtolower($exception->getMessage());
            $errors = [];

            if (str_contains($message, 'email')) {
                $errors['email'] = __('identity::identity.validation.duplicate_email');
            }

            if (str_contains($message, 'phone_e164') || str_contains($message, 'phone')) {
                $errors['phone_e164'] = __('identity::identity.validation.duplicate_phone');
            }

            if ($errors === []) {
                throw $exception;
            }

            throw ValidationException::withMessages($errors);
        }
    }

    private function uniqueSlug(string $businessNameEn): string
    {
        $base = Str::slug($businessNameEn) ?: 'vendor';

        return $base.'-'.Str::lower(substr((string) Str::ulid(), -10));
    }
}
