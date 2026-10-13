<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingForceCancelled;
use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Application\Listeners\OnBookingForceCancelledNotifyListener;
use App\Modules\Communication\Application\Listeners\OnBookingSubmitted;
use App\Modules\Communication\Database\Seeders\VendorBookingInAppNotificationTemplateSeeder;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Communication\Infrastructure\Gateways\DatabaseNotificationAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('creates one persistent actionable vendor request notice with booking reference and deadline on replay', function (string $locale): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00:00', 'UTC'));
    Queue::fake();
    $this->seed(VendorBookingInAppNotificationTemplateSeeder::class);
    $row = BookingVendor::factory()->pending()->create(['response_deadline' => '2026-10-14 10:00:00']);
    $row->booking->update(['reference_no' => 'IP-2026-000028']);
    $row->vendor->user->update(['preferred_locale' => $locale]);
    $event = new BookingSubmittedToVendor($row, $row->booking);
    $listener = app(OnBookingSubmitted::class);
    $listener->handle($event);
    $listener->handle($event);

    $dispatches = NotificationDispatch::query()->where('user_id', $row->vendor->user_id)
        ->where('channel', NotificationChannel::InApp->value)->get();
    expect($dispatches)->toHaveCount(1);
    $dispatch = $dispatches->first();
    expect($dispatch->context['body'])->toContain('IP-2026-000028', '2026-10-14 10:00 UTC')
        ->and($dispatch->context['body'])->not->toContain('{{')
        ->and($dispatch->context['action_url'])->toContain($row->public_id)
        ->and($dispatch->context['notification_format'])->toBe('filament');

    app(DatabaseNotificationAdapter::class)->send($dispatch);
    $notice = DB::table('notifications')->where('notifiable_id', $row->vendor->user_id)->first();
    $data = json_decode($notice->data, true, flags: JSON_THROW_ON_ERROR);
    expect($notice->read_at)->toBeNull()
        ->and($data['format'])->toBe('filament')
        ->and($data['actions'][0]['url'])->toContain($row->public_id)
        ->and($data['actions'][0]['shouldMarkAsRead'])->toBeTrue()
        ->and($data['actions'][0]['label'])->not->toBeEmpty();
    DB::table('notifications')->where('id', $notice->id)->update(['read_at' => now()]);
    $listener->handle($event);
    expect(DB::table('notifications')->where('notifiable_id', $row->vendor->user_id)->count())->toBe(1);
})->with(['en', 'ar']);

it('does not create a new request notice after its vendor request has ended', function (): void {
    Queue::fake();
    $this->seed(VendorBookingInAppNotificationTemplateSeeder::class);
    $row = BookingVendor::factory()->create(['sub_status' => VendorSubStatus::Cancelled]);
    app(OnBookingSubmitted::class)->handle(new BookingSubmittedToVendor($row, $row->booking));
    expect(NotificationDispatch::query()->where('user_id', $row->vendor->user_id)
        ->where('channel', NotificationChannel::InApp->value)->count())->toBe(0);
});

it('reuses a failed vendor request dispatch on listener replay for the existing retry path', function (): void {
    Queue::fake();
    $this->seed(VendorBookingInAppNotificationTemplateSeeder::class);
    $row = BookingVendor::factory()->pending()->create();
    $event = new BookingSubmittedToVendor($row, $row->booking);
    $listener = app(OnBookingSubmitted::class);
    $listener->handle($event);
    $dispatch = NotificationDispatch::query()->where('user_id', $row->vendor->user_id)
        ->where('channel', NotificationChannel::InApp->value)->firstOrFail();
    $dispatch->update(['status' => 'failed', 'error_message' => 'Temporary adapter failure']);
    $listener->handle($event);
    expect(NotificationDispatch::query()->where('user_id', $row->vendor->user_id)
        ->where('channel', NotificationChannel::InApp->value)->pluck('id')->all())->toBe([$dispatch->id]);
});

it('notifies captured affected vendors once after force cancellation without asking for a response', function (string $locale): void {
    Queue::fake();
    $this->seed(VendorBookingInAppNotificationTemplateSeeder::class);
    $row = BookingVendor::factory()->create(['sub_status' => VendorSubStatus::Cancelled]);
    $row->booking->update(['reference_no' => 'IP-2026-000028']);
    $row->vendor->user->update(['preferred_locale' => $locale]);
    $intervention = new BookingAdminIntervention(['reason' => 'Admin cancellation']);
    $intervention->public_id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    $event = new BookingForceCancelled($row->booking, $intervention, [$row->vendor->user_id, $row->vendor->user_id]);
    $listener = app(OnBookingForceCancelledNotifyListener::class);
    $listener->handle($event);
    $listener->handle($event);
    $dispatches = NotificationDispatch::query()->where('user_id', $row->vendor->user_id)
        ->where('channel', NotificationChannel::InApp->value)->get();
    expect($dispatches)->toHaveCount(1)
        ->and($dispatches->first()->context['body'])->toContain('IP-2026-000028', $locale === 'ar' ? 'لا يلزم الرد' : 'No response is due')
        ->and($dispatches->first()->context['action_url'])->toContain($row->public_id);
    app(DatabaseNotificationAdapter::class)->send($dispatches->first());
    expect(DB::table('notifications')->where('notifiable_id', $row->vendor->user_id)->count())->toBe(1);

    $listener->handle(new BookingForceCancelled($row->booking, $intervention, []));
    expect(NotificationDispatch::query()->where('user_id', $row->vendor->user_id)
        ->where('channel', NotificationChannel::InApp->value)->count())->toBe(1);
})->with(['en', 'ar']);

it('adds missing vendor templates without replacing edited templates', function (): void {
    $this->seed(VendorBookingInAppNotificationTemplateSeeder::class);
    $template = NotificationTemplate::query()->where('event_key', 'booking.submitted')
        ->where('channel', 'in_app')->where('audience', 'vendor')->firstOrFail();
    $template->setTranslation('body', 'en', 'Edited vendor message')->save();
    $this->seed(VendorBookingInAppNotificationTemplateSeeder::class);
    expect($template->refresh()->getTranslation('body', 'en'))->toBe('Edited vendor message');
});

it('suppresses a queued request if it ends before delivery', function (string $ending): void {
    Queue::fake();
    $this->seed(VendorBookingInAppNotificationTemplateSeeder::class);
    $row = BookingVendor::factory()->pending()->create();
    $row->vendor->user->update(['preferred_locale' => 'en']);
    app(OnBookingSubmitted::class)->handle(new BookingSubmittedToVendor($row, $row->booking));
    $request = NotificationDispatch::query()->where('user_id', $row->vendor->user_id)
        ->where('channel', NotificationChannel::InApp->value)->firstOrFail();

    match ($ending) {
        'cancelled' => $row->update(['sub_status' => VendorSubStatus::Cancelled]),
        'timed_out' => $row->update(['sub_status' => VendorSubStatus::TimedOut]),
        'expired' => $row->update(['response_deadline' => now()->subMinute()]),
        'booking_cancelled' => $row->booking->update(['lifecycle_status' => CancelledState::class]),
        'booking_completed' => $row->booking->update(['lifecycle_status' => CompletedState::class]),
    };

    if ($ending === 'cancelled') {
        $intervention = new BookingAdminIntervention(['reason' => 'Admin cancellation']);
        $intervention->public_id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        app(OnBookingForceCancelledNotifyListener::class)->handle(new BookingForceCancelled(
            $row->booking, $intervention, [$row->vendor->user_id],
        ));
        $cancellation = NotificationDispatch::query()->where('user_id', $row->vendor->user_id)
            ->where('channel', NotificationChannel::InApp->value)->whereKeyNot($request->id)->firstOrFail();
        (new DispatchNotificationJob($cancellation->id))->handle(app());
    }

    (new DispatchNotificationJob($request->id))->handle(app());
    $request->refresh();
    expect($request->status)->toBe(DispatchStatus::Suppressed)
        ->and($request->provider_status)->toBe('suppressed_request_ended')
        ->and($request->sent_at)->toBeNull()
        ->and($request->delivered_at)->toBeNull();
    $notices = DB::table('notifications')->where('notifiable_id', $row->vendor->user_id)->get();
    expect($notices)->toHaveCount($ending === 'cancelled' ? 1 : 0);
    if ($ending === 'cancelled') {
        expect(json_decode($notices->first()->data, true, flags: JSON_THROW_ON_ERROR)['body'])
            ->toContain('No response is due');
    }
})->with(['cancelled', 'timed_out', 'expired', 'booking_cancelled', 'booking_completed']);
