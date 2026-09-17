<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Services;

use App\Modules\Communication\Domain\Enums\ProviderName;
use App\Modules\Communication\Domain\Models\CommunicationProviderSetting;
use Illuminate\Support\Facades\Schema;

final class ProviderSettingsResolver
{
    /** @return array{enabled:bool,project_id:string|null,credentials:array<string,mixed>|null} */
    public function firebase(): array
    {
        $setting = $this->setting(ProviderName::Firebase);

        if ($setting !== null) {
            return [
                'enabled' => (bool) $setting->enabled,
                'project_id' => $setting->project_id,
                'credentials' => $setting->credentials,
            ];
        }

        $credentials = null;

        if ($credentials === null && filled(config('services.firebase.credentials'))) {
            $raw = (string) config('services.firebase.credentials');
            $credentials = str_starts_with(trim($raw), '{') ? json_decode($raw, true) : ['file' => $raw];
        }

        return [
            'enabled' => $setting?->enabled ?? ($credentials !== null),
            'project_id' => $setting?->project_id ?? config('services.firebase.project_id'),
            'credentials' => is_array($credentials) ? $credentials : null,
        ];
    }

    /** @return array{enabled:bool,username:string|null,password:string|null,sender:string|null,environment:int} */
    public function smsMisr(): array
    {
        $setting = $this->setting(ProviderName::SmsMisr);

        if ($setting !== null) {
            return [
                'enabled' => (bool) $setting->enabled,
                'username' => $setting->username,
                'password' => $setting->password,
                'sender' => $setting->sender_id,
                'environment' => (int) ($setting->environment ?? 2),
            ];
        }

        return [
            'enabled' => $setting?->enabled ?? filled(config('services.sms_misr.username')),
            'username' => $setting?->username ?? config('services.sms_misr.username'),
            'password' => $setting?->password ?? config('services.sms_misr.password'),
            'sender' => $setting?->sender_id ?? config('services.sms_misr.sender'),
            'environment' => $setting?->environment ?? (int) config('services.sms_misr.environment', 2),
        ];
    }

    public function mask(?string $value): string
    {
        return filled($value) ? '••••••••••' : '';
    }

    private function setting(ProviderName $provider): ?CommunicationProviderSetting
    {
        if (! Schema::hasTable('communication_provider_settings')) {
            return null;
        }

        return CommunicationProviderSetting::query()->where('provider', $provider->value)->first();
    }
}
