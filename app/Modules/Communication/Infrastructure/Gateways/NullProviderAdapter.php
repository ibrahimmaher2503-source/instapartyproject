<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Application\Exceptions\NullAdapterInProductionException;
use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;
use Illuminate\Support\Str;

class NullProviderAdapter implements NotificationChannelAdapter
{
    public function name(): string
    {
        return 'null';
    }

    public function healthCheck(): ProviderHealthResult
    {
        return ProviderHealthResult::reachable('null', 0, 'Null adapter active in '.app()->environment());
    }

    public function send(NotificationDispatch $dispatch): void
    {
        if (app()->environment('production')) {
            throw new NullAdapterInProductionException;
        }

        $dispatch->update([
            'status' => DispatchStatus::Sent,
            'provider_name' => 'null',
            'provider' => 'null',
            'provider_message_id' => 'null-'.Str::ulid()->toBase32(),
            'provider_ref' => null,
            'provider_status' => 'ok',
            'provider_error_code' => null,
            'provider_error_message' => null,
            'error_message' => null,
            'sent_at' => now(),
        ]);
    }
}
