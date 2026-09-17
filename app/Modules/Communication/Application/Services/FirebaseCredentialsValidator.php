<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class FirebaseCredentialsValidator
{
    /** @param mixed $raw @return array<string,mixed> */
    public function validate(mixed $raw): array
    {
        $credentials = is_array($raw) ? $raw : json_decode((string) $raw, true);

        if (! is_array($credentials)) {
            throw ValidationException::withMessages(['data.firebase_credentials_json' => __('communication.provider_settings.firebase.invalid_json')]);
        }

        Validator::make($credentials, [
            'type' => ['required', 'string'],
            'project_id' => ['required', 'string'],
            'client_email' => ['required', 'email'],
            'private_key' => ['required', 'string'],
        ])->validate();

        return $credentials;
    }
}
