<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Shared\Application\Services\SafeAuditChanges;
use App\Modules\Shared\Domain\Models\AppSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateAppSettingAction
{
    public function execute(AppSetting $setting, array $data): AppSetting
    {
        Gate::authorize('update', $setting);

        return DB::transaction(function () use ($setting, $data): AppSetting {
            $setting = AppSetting::query()->whereKey($setting->getKey())->lockForUpdate()->firstOrFail();
            $before = ['value' => $setting->value, 'description' => $setting->description];
            $setting->update([...Arr::only($data, ['value', 'description']), 'updated_by' => auth()->id()]);
            $after = ['value' => $setting->value, 'description' => $setting->description];
            if ($before !== $after) {
                activity()->on($setting)->causedBy(auth()->user())->event('updated')
                    ->withProperties(['key' => $setting->key, 'old' => SafeAuditChanges::redact($before, $setting->key), 'attributes' => SafeAuditChanges::redact($after, $setting->key)])
                    ->log('settings_updated');
            }

            return $setting;
        });
    }
}
