<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;
use Illuminate\Support\Facades\Log;

/**
 * Phase 1 stub — no external API calls.
 * Replace with real WhatsApp Cloud API adapter in Phase 1.5 (ADR-0010 §6.2).
 */
class WhatsAppStubAdapter implements NotificationChannelAdapter
{
    public function name(): string
    {
        return 'whatsapp_stub';
    }

    public function healthCheck(): ProviderHealthResult
    {
        return ProviderHealthResult::reachable('whatsapp_stub', 0, 'Stub adapter — Phase 1 placeholder');
    }

    public function send(NotificationDispatch $dispatch): void
    {
        Log::debug('WhatsAppStubAdapter: stub dispatch (no API call)', [
            'dispatch_id' => $dispatch->id,
            'context' => $dispatch->context,
        ]);

        $dispatch->status = DispatchStatus::Sent;
        $dispatch->provider_name = $this->name();
        $dispatch->provider_message_id = null;
        $dispatch->provider_status = 'stub_ok';
        $dispatch->sent_at = now();
        // Legacy dual-write
        $dispatch->provider = $this->name();
        $dispatch->save();
    }
}
