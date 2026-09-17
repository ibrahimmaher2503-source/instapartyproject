<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Pages;

use App\Modules\Communication\Application\Actions\SendTestEmailAction;
use App\Modules\Communication\Application\Actions\SendTestPushAction;
use App\Modules\Communication\Application\Actions\SendTestSmsAction;
use App\Modules\Communication\Application\Actions\SendTestWhatsAppAction;
use App\Modules\Communication\Application\DTOs\TestEmailDTO;
use App\Modules\Communication\Application\DTOs\TestPushDTO;
use App\Modules\Communication\Application\DTOs\TestSmsDTO;
use App\Modules\Communication\Application\DTOs\TestWhatsAppDTO;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CommunicationProviderHealthPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?int $navigationSort = 70;

    protected static string $view = 'communication::filament.pages.provider-health';

    public static function getNavigationLabel(): string
    {
        return __('communication.provider_health.nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.operations');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_communication_provider_health') ?? false;
    }

    public function getTitle(): string
    {
        return __('communication.provider_health.page_title');
    }

    public function getViewData(): array
    {
        $channels = [
            NotificationChannel::Push,
            NotificationChannel::Sms,
            NotificationChannel::Whatsapp,
            NotificationChannel::Email,
        ];

        return [
            'cards' => collect($channels)
                ->map(fn (NotificationChannel $channel): array => $this->providerCard($channel))
                ->all(),
        ];
    }

    /**
     * Rendering this page must never contact an external provider.
     *
     * @return array<string, mixed>
     */
    private function providerCard(NotificationChannel $channel): array
    {
        $configured = $this->isConfigured($channel);

        try {
            $sentCount = NotificationDispatch::query()
                ->forChannel($channel)
                ->inLast24h()
                ->withStatus(DispatchStatus::Sent)
                ->count();
            $failedCount = NotificationDispatch::query()
                ->forChannel($channel)
                ->inLast24h()
                ->withStatus(DispatchStatus::Failed)
                ->count();
            $lastSuccess = NotificationDispatch::query()
                ->forChannel($channel)
                ->withStatus(DispatchStatus::Sent)
                ->latest('sent_at')
                ->value('sent_at');
            $lastFailure = NotificationDispatch::query()
                ->forChannel($channel)
                ->withStatus(DispatchStatus::Failed)
                ->latest('updated_at')
                ->value('updated_at');
        } catch (\Throwable) {
            $sentCount = 0;
            $failedCount = 0;
            $lastSuccess = null;
            $lastFailure = null;
        }

        $statusBadge = match (true) {
            ! $configured => 'not_configured',
            $failedCount > $sentCount => 'degraded',
            $lastSuccess === null => 'unknown',
            default => 'healthy',
        };

        return [
            'channel' => $channel,
            'channelLabel' => __('communication.provider_health.channels.'.$channel->value),
            'configured' => $configured,
            'isReachable' => $configured && $lastSuccess !== null,
            'latencyMs' => null,
            'note' => $lastFailure !== null
                ? __('communication.provider_health.card.failure_recorded')
                : null,
            'providerName' => __('communication.provider_health.providers.'.$channel->value),
            'sentLast24h' => $sentCount,
            'failedLast24h' => $failedCount,
            'lastSuccessAt' => $lastSuccess,
            'lastFailureAt' => $lastFailure,
            'statusBadge' => $statusBadge,
        ];
    }

    private function isConfigured(NotificationChannel $channel): bool
    {
        return match ($channel) {
            NotificationChannel::Push => filled(config('services.firebase.project_id'))
                && filled(config('services.firebase.credentials')),
            NotificationChannel::Sms => filled(config('services.sms_misr.username'))
                && filled(config('services.sms_misr.password'))
                && filled(config('services.sms_misr.sender')),
            NotificationChannel::Whatsapp => filled(config('services.whatsapp.access_token'))
                && filled(config('services.whatsapp.phone_number_id')),
            NotificationChannel::Email => ! in_array(config('mail.default'), ['array', 'log'], true),
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send_test_push')
                ->label(__('communication.provider_health.channels.push'))
                ->icon('heroicon-o-device-phone-mobile')
                ->color('info')
                ->form([
                    TextInput::make('device_token')
                        ->label(__('communication.provider_health.modal.device_token'))
                        ->required()
                        ->minLength(32)
                        ->maxLength(255),
                    TextInput::make('body_override')
                        ->label(__('communication.provider_health.modal.body_override'))
                        ->maxLength(500),
                ])
                ->action(function (array $data) {
                    $dto = new TestPushDTO(
                        deviceToken: $data['device_token'],
                        adminUserId: auth()->id() ?? 0,
                        bodyOverride: $data['body_override'] ?? null,
                    );
                    $dispatch = app(SendTestPushAction::class)->execute($dto);
                    Notification::make()
                        ->title(__('communication.provider_health.notifications.test_queued'))
                        ->body("dispatch public_id: {$dispatch->public_id}")
                        ->success()
                        ->send();
                })
                ->modalHeading(__('communication.provider_health.modal.send_test_push_heading'))
                ->visible(fn () => auth()->user()?->can('send_test_notification')),

            Action::make('send_test_sms')
                ->label(__('communication.provider_health.channels.sms'))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('info')
                ->form([
                    TextInput::make('phone_e164')
                        ->label(__('communication.provider_health.modal.phone_e164'))
                        ->required()
                        ->regex('/^\+[1-9]\d{6,14}$/'),
                    TextInput::make('body_override')
                        ->label(__('communication.provider_health.modal.body_override'))
                        ->maxLength(500),
                ])
                ->action(function (array $data) {
                    $dto = new TestSmsDTO(
                        phoneE164: $data['phone_e164'],
                        adminUserId: auth()->id() ?? 0,
                        bodyOverride: $data['body_override'] ?? null,
                    );
                    $dispatch = app(SendTestSmsAction::class)->execute($dto);
                    Notification::make()
                        ->title(__('communication.provider_health.notifications.test_queued'))
                        ->body("dispatch public_id: {$dispatch->public_id}")
                        ->success()
                        ->send();
                })
                ->modalHeading(__('communication.provider_health.modal.send_test_sms_heading'))
                ->visible(fn () => auth()->user()?->can('send_test_notification')),

            Action::make('send_test_whatsapp')
                ->label(__('communication.provider_health.channels.whatsapp'))
                ->icon('heroicon-o-chat-bubble-left')
                ->color('success')
                ->form([
                    TextInput::make('phone_e164')
                        ->label(__('communication.provider_health.modal.phone_e164'))
                        ->required()
                        ->regex('/^\+[1-9]\d{6,14}$/'),
                    TextInput::make('body_override')
                        ->label(__('communication.provider_health.modal.body_override'))
                        ->maxLength(500),
                ])
                ->action(function (array $data) {
                    $dto = new TestWhatsAppDTO(
                        phoneE164: $data['phone_e164'],
                        adminUserId: auth()->id() ?? 0,
                        bodyOverride: $data['body_override'] ?? null,
                    );
                    $dispatch = app(SendTestWhatsAppAction::class)->execute($dto);
                    Notification::make()
                        ->title(__('communication.provider_health.notifications.test_queued'))
                        ->body("dispatch public_id: {$dispatch->public_id}")
                        ->success()
                        ->send();
                })
                ->modalHeading(__('communication.provider_health.modal.send_test_whatsapp_heading'))
                ->visible(fn () => auth()->user()?->can('send_test_notification')),

            Action::make('send_test_email')
                ->label(__('communication.provider_health.channels.email'))
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->form([
                    TextInput::make('email_address')
                        ->label(__('communication.provider_health.modal.email_address'))
                        ->required()
                        ->email(),
                    TextInput::make('subject_override')
                        ->label(__('communication.provider_health.modal.subject_override'))
                        ->maxLength(160),
                    TextInput::make('body_override')
                        ->label(__('communication.provider_health.modal.body_override'))
                        ->maxLength(500),
                ])
                ->action(function (array $data) {
                    $dto = new TestEmailDTO(
                        emailAddress: $data['email_address'],
                        adminUserId: auth()->id() ?? 0,
                        subjectOverride: $data['subject_override'] ?? null,
                        bodyOverride: $data['body_override'] ?? null,
                    );
                    $dispatch = app(SendTestEmailAction::class)->execute($dto);
                    Notification::make()
                        ->title(__('communication.provider_health.notifications.test_queued'))
                        ->body("dispatch public_id: {$dispatch->public_id}")
                        ->success()
                        ->send();
                })
                ->modalHeading(__('communication.provider_health.modal.send_test_email_heading'))
                ->visible(fn () => auth()->user()?->can('send_test_notification')),
        ];
    }
}
