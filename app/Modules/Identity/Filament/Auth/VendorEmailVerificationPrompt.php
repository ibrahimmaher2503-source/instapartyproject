<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Auth;

use App\Modules\Communication\Domain\Contracts\EmailTransportReadiness;
use App\Modules\Identity\Domain\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\EmailVerification\EmailVerificationPrompt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class VendorEmailVerificationPrompt extends EmailVerificationPrompt
{
    public function resendNotificationAction(): Action
    {
        return Action::make('resendNotification')->link()
            ->label(__('filament-panels::pages/auth/email-verification/email-verification-prompt.actions.resend_notification.label'))
            ->action(function (): void {
                try {
                    $this->rateLimit(2);
                } catch (TooManyRequestsException $exception) {
                    $this->getRateLimitedNotification($exception)?->send();

                    return;
                }
                $user = $this->getVerifiable();
                abort_unless($user instanceof User && $user->status === 'active' && $user->hasRole('vendor', 'web'), 403);
                $accepted = false;
                try {
                    if (app(EmailTransportReadiness::class)->isConfigured()) {
                        $this->sendEmailVerificationNotification($user);
                        $accepted = true;
                    }
                } catch (Throwable) {
                    $accepted = false;
                }
                DB::table('audit_logs')->insert([
                    'public_id' => (string) Str::ulid(), 'auditable_type' => $user::class,
                    'auditable_id' => $user->getAuthIdentifier(), 'user_id' => $user->getAuthIdentifier(),
                    'action' => 'vendor_self_verification_resend_requested',
                    'changes' => json_encode(['transport_accepted' => $accepted], JSON_THROW_ON_ERROR),
                    'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(),
                ]);
                $notification = Notification::make()->title($accepted
                    ? __('identity::account_management.verification_requested') : __('identity::account_management.delivery_failed'));
                $accepted ? $notification->success() : $notification->danger();
                $notification->send();
            });
    }
}
