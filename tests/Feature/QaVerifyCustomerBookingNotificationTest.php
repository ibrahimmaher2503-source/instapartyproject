<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Communication\Application\Actions\RetryFailedDispatchAction;
use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Application\Listeners\OnBookingSubmitted;
use App\Modules\Communication\Database\Seeders\NotificationTemplateSeeder;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Queue;

it('sends one customer submission notification per channel with reference, submitted status, and owner link', function (string $locale): void {
    Queue::fake();
    $this->seed(NotificationTemplateSeeder::class);
    $customer = User::factory()->asCustomer()->create(['preferred_locale' => $locale]);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => VendorReviewState::class,
        'reference_no' => 'IP-2026-000731',
    ]);
    $vendors = collect([1, 2])->map(fn (): BookingVendor => BookingVendor::factory()->pending()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Pending,
    ]));
    $listener = app(OnBookingSubmitted::class);

    foreach ($vendors as $vendor) {
        $event = new BookingSubmittedToVendor($vendor, $booking);
        $listener->handle($event);
        $listener->handle($event);
    }

    $dispatches = NotificationDispatch::query()->where('user_id', $customer->id)->get();
    expect($dispatches)->toHaveCount(3)
        ->and($dispatches->pluck('channel')->map(fn ($channel) => $channel->value)->sort()->values()->all())
        ->toBe(collect([NotificationChannel::Email->value, NotificationChannel::InApp->value, NotificationChannel::Push->value])->sort()->values()->all());

    foreach ($dispatches as $dispatch) {
        expect($dispatch->context['body'])->toContain('IP-2026-000731')
            ->and($dispatch->context['body'])->toContain($locale === 'ar' ? 'قيد مراجعة المورد' : 'Pending Vendor Review')
            ->and($dispatch->context['action_url'])->toContain('/'.$locale.'/account/bookings/'.$booking->public_id)
            ->and($dispatch->context['event_identity'])->toBe(implode(':', [
                'booking_submitted', 'customer', (string) $customer->id, $booking->public_id, $dispatch->channel->value,
            ]));
    }
})->with(['en', 'ar']);

it('keeps a failed customer channel retryable without duplicating successful channels', function (): void {
    Queue::fake();
    $this->seed(NotificationTemplateSeeder::class);
    $customer = User::factory()->asCustomer()->create(['preferred_locale' => 'en']);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => VendorReviewState::class,
        'reference_no' => 'IP-2026-000732',
    ]);
    $bookingVendor = BookingVendor::factory()->pending()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Pending,
    ]);
    $event = new BookingSubmittedToVendor($bookingVendor, $booking);
    $listener = app(OnBookingSubmitted::class);
    $listener->handle($event);

    $pushDispatch = NotificationDispatch::query()
        ->where('user_id', $customer->id)
        ->where('channel', NotificationChannel::Push)
        ->firstOrFail();
    $this->app->instance('push_adapter', new class
    {
        public function send(NotificationDispatch $dispatch): void
        {
            throw new RuntimeException('provider unavailable');
        }
    });

    expect(fn () => (new DispatchNotificationJob($pushDispatch->id))->handle(app()))
        ->toThrow(RuntimeException::class);

    $pushDispatch->refresh();
    expect($pushDispatch->status)->toBe(DispatchStatus::Failed)
        ->and($pushDispatch->attempt_count)->toBe(1)
        ->and($pushDispatch->context['body'])->toContain('Pending Vendor Review');

    $listener->handle($event);
    $dispatches = NotificationDispatch::query()->where('user_id', $customer->id)->get();
    expect($dispatches)->toHaveCount(3)
        ->and($dispatches->where('status', DispatchStatus::Failed)->pluck('channel')->map(fn ($channel) => $channel->value)->all())
        ->toBe([NotificationChannel::Push->value]);

    $retried = app(RetryFailedDispatchAction::class)->execute($pushDispatch->fresh(), auditAction: 'test.booking_notification.retry');
    expect($retried->fresh()->status)->toBe(DispatchStatus::Queued);
    expect(Queue::pushed(DispatchNotificationJob::class)
        ->filter(fn (DispatchNotificationJob $job): bool => $job->dispatchId === $pushDispatch->id))
        ->toHaveCount(2);

    $this->app->instance('push_adapter', new class
    {
        public function send(NotificationDispatch $dispatch): void
        {
            $dispatch->update(['status' => DispatchStatus::Sent]);
        }
    });
    (new DispatchNotificationJob($pushDispatch->id))->handle(app());

    expect($pushDispatch->fresh()->status)->toBe(DispatchStatus::Sent)
        ->and(NotificationDispatch::query()->where('user_id', $customer->id)->count())->toBe(3);
});
