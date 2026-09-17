<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Modules\Communication\Domain\Enums\ProviderName;
use App\Modules\Communication\Domain\Models\CommunicationProviderSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class SaveProviderSettingsAction
{
    /** @param array<string,mixed> $payload */
    public function execute(ProviderName $provider, array $payload, ?int $actorId): CommunicationProviderSetting
    {
        $validated = $this->validate($provider, $payload);

        return DB::transaction(function () use ($provider, $validated, $actorId): CommunicationProviderSetting {
            $setting = CommunicationProviderSetting::query()->firstOrNew(['provider' => $provider->value]);
            $changed = array_keys($validated);

            $setting->fill(array_merge($validated, ['updated_by' => $actorId]));
            $setting->save();

            $activity = activity()->withProperties([
                'provider' => $provider->value,
                'settings_changed' => $changed,
            ]);

            if ($actorId !== null && auth()->id() === $actorId && auth()->user() !== null) {
                $activity->causedBy(auth()->user());
            }

            $activity->log('notification_provider.settings_updated');

            return $setting->fresh();
        });
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function validate(ProviderName $provider, array $payload): array
    {
        $rules = $provider === ProviderName::Firebase
            ? ['enabled' => ['required', 'boolean'], 'project_id' => ['nullable', 'string', 'max:190'], 'credentials' => ['nullable', 'array']]
            : ['enabled' => ['required', 'boolean'], 'username' => ['nullable', 'string', 'max:190'], 'password' => ['nullable', 'string', 'max:1000'], 'sender_id' => ['nullable', 'string', 'max:120'], 'environment' => ['required', 'integer', 'in:1,2']];

        return Validator::make($payload, $rules)->validate();
    }
}
