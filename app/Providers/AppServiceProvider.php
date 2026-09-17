<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Policies\ActivityPolicy;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Filament\Forms\Components\TextInput;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);

        Relation::morphMap([
            'service' => Service::class,
            'vendor' => VendorProfile::class,
        ]);

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch->locales(['en', 'ar']);
        });

        RateLimiter::for('api-login', function (Request $request): Limit {
            $identity = $request->input('email') ?? $request->input('phone') ?? '';

            return Limit::perMinute(6)->by($identity.'|'.$request->ip());
        });

        // Feature 054 (B1) — Forgot-password request throttle.
        // FR-EXT-205: 3 requests per identifier per 10 minutes, 10 per IP per hour.
        RateLimiter::for('password-reset', function (Request $request): array {
            $identifier = (string) ($request->input('identifier') ?? '');

            return [
                Limit::perMinutes(10, 3)->by('pr:id:'.$identifier),
                Limit::perHour(10)->by('pr:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('loyalty-redemption', function (Request $request): Limit {
            $maxAttempts = (int) config('loyalty.redemption_throttle.max_attempts', 20);
            $decayMinutes = (int) config('loyalty.redemption_throttle.decay_minutes', 1);
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinutes($decayMinutes, $maxAttempts)->by((string) $key);
        });

        TextInput::macro('dir', function (string $direction = 'ltr') {
            return $this->extraInputAttributes([
                'dir' => $direction,
                'style' => $direction === 'rtl'
                    ? 'text-align: right;'
                    : 'text-align: left;',
            ]);
        });
    }
}
