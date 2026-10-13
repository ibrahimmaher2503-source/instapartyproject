<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PanelRequestPasswordReset extends RequestPasswordReset
{
    public function validate($rules = null, $messages = [], $attributes = []): array
    {
        return parent::validate($this->form->getValidationRules(), $this->form->getValidationMessages(), $this->form->getValidationAttributes());
    }

    public function validateOnly($field, $rules = null, $messages = [], $attributes = [], $dataOverrides = [])
    {
        return parent::validateOnly($field, $this->form->getValidationRules(), $this->form->getValidationMessages(), $this->form->getValidationAttributes());
    }

    public function request(): void
    {
        if (in_array(config('mail.default'), ['log', 'array', 'failover'], true)) {
            try {
                $this->rateLimit(2);
            } catch (TooManyRequestsException $exception) {
                $this->getRateLimitedNotification($exception)?->send();

                return;
            }
            $this->form->getState();
            Notification::make()->title(__('identity::account_management.delivery_failed'))->danger()->send();

            return;
        }
        try {
            parent::request();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('panel-password-reset.delivery.failed', ['error_class' => class_basename($exception)]);
            $this->getSentNotification('')->send();
        }
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        return $this->getSentNotification($status);
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return Notification::make()->title(__('identity::account_management.reset_request_neutral'))
            ->body(__('identity::account_management.reset_recovery_hint'))->info();
    }
}
