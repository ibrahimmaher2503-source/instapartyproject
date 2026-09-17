<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Contracts;

use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;

interface NotificationChannelAdapter
{
    /**
     * Send a single notification dispatch. Adapter MUST:
     *  - Write status, provider_name, provider_message_id, provider_status, sent_at on success
     *  - Write status, provider_name, provider_error_code, provider_error_message, next_retry_at on failure
     *  - Dual-write legacy columns (provider, provider_ref, error_message) during transition
     *  - Call $dispatch->save() before returning
     *  - MUST NOT throw — capture all failures into the dispatch row
     */
    public function send(NotificationDispatch $dispatch): void;

    /**
     * Machine-readable adapter name. Lowercase, underscore-separated, stable.
     */
    public function name(): string;

    /**
     * Lightweight reachability check. Synchronous, < 2s. MUST NOT consume provider quota.
     * MUST NOT include provider secrets in the returned result.
     */
    public function healthCheck(): ProviderHealthResult;
}
