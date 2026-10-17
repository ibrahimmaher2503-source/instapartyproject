<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Booking\Domain\Contracts\BookingReferenceReader;
use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseNotificationAdapter implements NotificationChannelAdapter
{
    public function name(): string
    {
        return 'database';
    }

    public function healthCheck(): ProviderHealthResult
    {
        return ProviderHealthResult::reachable($this->name(), 0, 'Laravel database notifications are available.');
    }

    public function send(NotificationDispatch $dispatch): void
    {
        $context = (array) ($dispatch->context ?? []);

        if (($context['notification_format'] ?? null) === 'filament'
            && ($context['event_key'] ?? null) === 'booking.submitted'
            && ($context['audience'] ?? null) === 'vendor'
            && isset($context['booking_vendor_public_id'])
            && ! app(BookingReferenceReader::class)->isVendorRequestPending((string) $context['booking_vendor_public_id'], (int) $dispatch->user_id)) {
            // Queued requests can outlive cancellation or timeout; finish without creating a stale bell notice.
            $dispatch->update([
                'status' => DispatchStatus::Suppressed,
                'provider_name' => $this->name(),
                'provider_status' => 'suppressed_request_ended',
                'provider' => $this->name(),
                'sent_at' => null,
                'delivered_at' => null,
            ]);

            return;
        }

        $data = [
            'title' => $context['subject'] ?? null,
            'body' => $context['body'] ?? null,
            'title_translations' => $context['title_translations'] ?? [],
            'body_translations' => $context['body_translations'] ?? [],
            'action_url' => $context['action_url'] ?? null,
            'data' => $context['data'] ?? [],
            'reference_type' => $dispatch->reference_type,
            'reference_id' => $dispatch->reference_id,
        ];
        if (($context['notification_format'] ?? null) === 'filament') {
            $notification = Notification::make()->title((string) ($context['subject'] ?? ''))
                ->body((string) ($context['body'] ?? ''))->danger();
            if (filled($context['action_url'] ?? null)) {
                $notification->actions([Action::make('view')->label((string) ($context['action_label'] ?? ''))
                    ->url($context['action_url'])->markAsRead()]);
            }
            $data = array_merge($data, $notification->getDatabaseMessage());
        }

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => $context['notification_type'] ?? $dispatch->channel->value,
            'notifiable_type' => 'App\\Modules\\Identity\\Domain\\Models\\User',
            'notifiable_id' => $dispatch->user_id,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dispatch->update([
            'status' => DispatchStatus::Sent,
            'provider_name' => $this->name(),
            'provider_status' => 'ok',
            'sent_at' => now(),
            'provider' => $this->name(),
        ]);
    }
}
