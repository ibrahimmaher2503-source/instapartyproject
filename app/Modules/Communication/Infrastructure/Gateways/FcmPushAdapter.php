<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Application\Services\ProviderSettingsResolver;
use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\ValueObjects\ProviderHealthResult;
use App\Modules\Identity\Domain\Contracts\DeviceTokenRepository;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\FirebaseProjectManager;
use Throwable;

class FcmPushAdapter implements NotificationChannelAdapter
{
    public function __construct(
        private readonly FirebaseProjectManager $firebase,
        private readonly ProviderSettingsResolver $settings,
        private readonly DeviceTokenRepository $devices,
    ) {}

    public function name(): string
    {
        return 'fcm';
    }

    public function healthCheck(): ProviderHealthResult
    {
        $start = hrtime(true);
        try {
            $appInstance = $this->messaging()->getAppInstance()->getName();
            $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);

            return ProviderHealthResult::reachable('fcm', $latencyMs, "App instance: {$appInstance}");
        } catch (Throwable $e) {
            return ProviderHealthResult::unreachable('fcm', 'SDK bootstrap failed: '.class_basename($e));
        }
    }

    public function send(NotificationDispatch $dispatch): void
    {
        $context = $dispatch->context ?? [];
        $tokens = isset($context['device_token'])
            ? [(string) $context['device_token']]
            : $this->devices->activeTokensForUser((int) ($context['recipient_user_id'] ?? 0));

        if ($tokens === []) {
            $dispatch->status = DispatchStatus::Failed;
            $dispatch->provider_name = $this->name();
            $dispatch->provider_error_code = 'MISSING_TOKEN';
            $dispatch->provider_error_message = 'No device token in context';
            $dispatch->error_message = 'No device token in context';
            $dispatch->next_retry_at = null;
            $dispatch->save();

            return;
        }

        try {
            $messaging = $this->messaging();
            $resultIds = [];
            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::new()
                        ->withToken($token)
                        ->withNotification(Notification::create($context['title'] ?? '', $context['body'] ?? ''))
                        ->withData($context['data'] ?? []);
                    $resultIds[] = (string) $messaging->send($message);
                } catch (Throwable $e) {
                    if ($this->isInvalidToken($e)) {
                        $this->devices->deactivateToken($token);
                    }
                }
            }

            if ($resultIds === []) {
                throw new \RuntimeException('FCM delivery failed for all device tokens.');
            }

            $dispatch->status = DispatchStatus::Sent;
            $dispatch->provider_name = $this->name();
            $dispatch->provider_message_id = substr(implode(',', $resultIds), 0, 255);
            $dispatch->provider_status = 'ok';
            $dispatch->sent_at = now();
            // Legacy dual-write
            $dispatch->provider = $this->name();
            $dispatch->provider_ref = substr(implode(',', $resultIds), 0, 255);
            $dispatch->save();
        } catch (Throwable $e) {
            $dispatch->status = DispatchStatus::Failed;
            $dispatch->provider_name = $this->name();
            $dispatch->provider_error_code = substr(class_basename($e), 0, 60);
            $dispatch->provider_error_message = $e->getMessage();
            $dispatch->error_message = $e->getMessage();
            $dispatch->next_retry_at = $dispatch->attempt_count < 5
                ? now()->addMinutes(5 * (2 ** ($dispatch->attempt_count - 1)))
                : null;
            $dispatch->save();
        }
    }

    private function isInvalidToken(Throwable $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'registration-token-not-registered')
            || str_contains(strtolower($exception->getMessage()), 'invalid-registration-token');
    }

    private function messaging(): Messaging
    {
        $settings = $this->settings->firebase();

        if (! $settings['enabled'] || $settings['credentials'] === null) {
            throw new \RuntimeException('Firebase is disabled or not configured.');
        }

        $projectName = 'admin_settings_'.md5(json_encode($settings['credentials'], JSON_THROW_ON_ERROR));
        config()->set("firebase.projects.{$projectName}", array_replace_recursive(
            (array) config('firebase.projects.app', []),
            [
                'credentials' => $settings['credentials'],
            ],
        ));

        return $this->firebase->project($projectName)->messaging();
    }
}
