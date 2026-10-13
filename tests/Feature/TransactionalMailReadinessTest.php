<?php

use App\Modules\Communication\Application\Services\MailReadiness;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Infrastructure\Gateways\LaravelMailEmailAdapter;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

it('does not report log mail or a log fallback as delivery ready', function () {
    config(['mail.default' => 'log']);
    expect(MailReadiness::configured())->toBeFalse();
    config(['mail.default' => 'failover']);
    expect(MailReadiness::configured())->toBeFalse();
});

it('records transport acceptance and resolves an active recipient without an email context override', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.port' => 587,
        'mail.from.address' => 'support@instaparty.test']);
    $recipient = User::factory()->create(['email' => 'owner@example.test']);
    $dispatch = NotificationDispatch::factory()->create(['user_id' => $recipient->id, 'channel' => 'email',
        'context' => ['subject' => 'Notice', 'body' => 'Hello']]);
    $email = (new Email)->from('support@instaparty.test')->to($recipient->email)->subject('Notice')->text('Hello');
    $sent = new SentMessage(new Symfony\Component\Mailer\SentMessage($email, Envelope::create($email)));
    Mail::shouldReceive('raw')->once()->with('Hello', Mockery::on(function ($callback) use ($email, $recipient) {
        $message = new Message($email);
        $callback($message);

        return $message->getSymfonyMessage()->getTo()[0]->getAddress() === $recipient->email;
    }))->andReturn($sent);
    app(LaravelMailEmailAdapter::class)->send($dispatch);
    expect($dispatch->fresh()->status)->toBe(DispatchStatus::Sent)
        ->and($dispatch->fresh()->provider_status)->toBe('transport_accepted')
        ->and($dispatch->fresh()->provider_message_id)->toBe($sent->getMessageId());
});

it('uses configured Laravel mail for notification dispatches and captures transport failures safely', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.port' => 587,
        'mail.from.address' => 'support@instaparty.test']);
    $dispatch = NotificationDispatch::factory()->create(['channel' => 'email', 'attempt_count' => 1,
        'is_test' => true, 'context' => ['email' => 'recipient@example.test', 'subject' => 'Test', 'body' => 'Hello']]);
    Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('Sensitive provider response'));
    app(LaravelMailEmailAdapter::class)->send($dispatch);
    expect($dispatch->fresh()->status)->toBe(DispatchStatus::Failed)
        ->and($dispatch->fresh()->provider_error_message)->not->toContain('Sensitive provider response')
        ->and($dispatch->fresh()->next_retry_at)->not->toBeNull();
});

it('does not let ordinary dispatch context redirect email away from its account owner', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.port' => 587,
        'mail.from.address' => 'support@instaparty.test']);
    $recipient = User::factory()->create(['email' => 'owner@example.test']);
    $dispatch = NotificationDispatch::factory()->create(['user_id' => $recipient->id, 'channel' => 'email',
        'context' => ['email' => 'other@example.test', 'subject' => 'Notice', 'body' => 'Hello']]);
    $email = (new Email)->from('support@instaparty.test')->subject('Notice')->text('Hello');
    $sent = new SentMessage(new Symfony\Component\Mailer\SentMessage(
        $email->to($recipient->email), Envelope::create($email),
    ));
    Mail::shouldReceive('raw')->once()->with('Hello', Mockery::on(function ($callback) use ($email) {
        $message = new Message($email);
        $callback($message);

        return $message->getSymfonyMessage()->getTo()[0]->getAddress() === 'owner@example.test';
    }))->andReturn($sent);

    app(LaravelMailEmailAdapter::class)->send($dispatch);
    expect($dispatch->fresh()->status)->toBe(DispatchStatus::Sent);
});

it('fails ordinary email for an inactive account despite a valid context override', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.port' => 587,
        'mail.from.address' => 'support@instaparty.test']);
    $recipient = User::factory()->create(['status' => 'suspended']);
    $dispatch = NotificationDispatch::factory()->create(['user_id' => $recipient->id, 'channel' => 'email',
        'context' => ['email' => 'other@example.test', 'body' => 'Hello']]);
    Mail::shouldReceive('raw')->never();

    app(LaravelMailEmailAdapter::class)->send($dispatch);
    expect($dispatch->fresh()->status)->toBe(DispatchStatus::Failed);
});
