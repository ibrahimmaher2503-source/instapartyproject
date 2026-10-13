<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Communication\Domain\Contracts\EmailTransportReadiness;
use App\Modules\Identity\Domain\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Auth\VerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

final class AdminResendVendorVerificationAction
{
    public function execute(User $actor, User $target): bool
    {
        $actor = User::findOrFail($actor->id);
        $target = User::findOrFail($target->id);
        abort_unless((int) Auth::guard('web')->id() === (int) $actor->id && $actor->status === 'active'
            && $actor->hasRole('super_admin', 'web') && $target->status === 'active'
            && $target->hasRole('vendor', 'web') && $target->vendorProfile()->exists()
            && filled($target->email) && ! $target->hasVerifiedEmail(), 403);
        $key = 'admin-vendor-verification:'.$target->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return false;
        }
        RateLimiter::hit($key, 600);
        $accepted = false;
        try {
            if (app(EmailTransportReadiness::class)->isConfigured()) {
                $notification = app(VerifyEmail::class);
                $notification->url = Filament::getPanel('vendor')->getVerifyEmailUrl($target);
                $target->notifyNow($notification);
                $accepted = true;
            }
        } catch (Throwable) {
            $accepted = false;
        }
        DB::table('audit_logs')->insert([
            'public_id' => (string) Str::ulid(), 'auditable_type' => User::class,
            'auditable_id' => $target->id, 'user_id' => $actor->id, 'action' => 'vendor_verification_resend_requested',
            'changes' => json_encode(['transport_accepted' => $accepted], JSON_THROW_ON_ERROR),
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(),
        ]);

        return $accepted;
    }
}
