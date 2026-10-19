<?php

use App\Modules\Communication\Application\Services\MailReadiness;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Infrastructure\Gateways\LaravelMailEmailAdapter;
use Illuminate\Support\Facades\Mail;

it('does not report log mail or a log fallback as delivery ready', function () {
    config(['mail.default' => 'log']);
    expect(MailReadiness::configured())->toBeFalse();
    config(['mail.default' => 'failover']);
    expect(MailReadiness::configured())->toBeFalse();
});

it('uses configured Laravel mail for notification dispatches and captures transport failures safely', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.port' => 587,
        'mail.from.address' => 'support@instaparty.test']);
    $dispatch = NotificationDispatch::factory()->create(['channel' => 'email', 'attempt_count' => 1,
        'context' => ['email' => 'recipient@example.test', 'subject' => 'Test', 'body' => 'Hello']]);
    Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('Sensitive provider response'));
    app(LaravelMailEmailAdapter::class)->send($dispatch);
    expect($dispatch->fresh()->status)->toBe(DispatchStatus::Failed)
        ->and($dispatch->fresh()->provider_error_message)->not->toContain('Sensitive provider response')
        ->and($dispatch->fresh()->next_retry_at)->not->toBeNull();
});
