<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Events\ServicePublished;
use App\Modules\Catalog\Domain\Events\ServiceRejected;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Application\Listeners\OnServicePublished;
use App\Modules\Communication\Application\Listeners\OnServiceRejected;
use App\Modules\Communication\Application\Services\ResolvedTemplate;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Communication\Filament\Resources\NotificationTemplateResource;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

it('renders bilingual notification content literally and rejects missing variables', function () {
    foreach (['Booking {{ booking_id }}', 'الحجز {{ booking_id }}'] as $body) {
        $template = new ResolvedTemplate(1, $body, '{{booking_id}}', []);
        $rendered = $template->render(['booking_id' => '01PUBLIC$1']);
        expect($rendered['body'])->toContain('01PUBLIC$1')->not->toContain('{{')
            ->and($rendered['subject'])->toBe('01PUBLIC$1');
        expect(fn () => $template->render([]))->toThrow(InvalidArgumentException::class);
        expect(fn () => $template->render(['booking_id' => []]))->toThrow(InvalidArgumentException::class);
    }
});

it('dispatches service published notifications to the owning vendor', function () {
    $vendor = VendorProfile::factory()->create();
    $service = Service::factory()->sale()->create(['vendor_profile_id' => $vendor->id]);
    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')
        ->twice()
        ->with(Mockery::on(fn (DispatchNotificationDTO $dto): bool => $dto->eventKey === 'service.published' && $dto->userId === $vendor->user_id
        ))
        ->andReturn(null);

    (new OnServicePublished($dispatcher))->handle(new ServicePublished($service));
});

it('dispatches service rejected notifications to the owning vendor', function () {
    $vendor = VendorProfile::factory()->create();
    $service = Service::factory()->sale()->create(['vendor_profile_id' => $vendor->id]);
    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')
        ->twice()
        ->with(Mockery::on(fn (DispatchNotificationDTO $dto): bool => $dto->eventKey === 'service.rejected' && $dto->userId === $vendor->user_id
        ))
        ->andReturn(null);

    (new OnServiceRejected($dispatcher))->handle(new ServiceRejected($service));
});

it('requires every placeholder to be documented before a template is saved', function () {
    $valid = [
        'body' => ['en' => 'Booking {{booking_id}}', 'ar' => 'الحجز {{booking_id}}'],
        'subject' => ['en' => 'Booking', 'ar' => 'الحجز'],
        'variables' => ['booking_id' => 'Public booking reference'],
    ];

    expect(NotificationTemplateResource::validateFormData($valid))->toBe($valid);

    $valid['variables'] = [];
    expect(fn () => NotificationTemplateResource::validateFormData($valid))
        ->toThrow(ValidationException::class);
});

it('queues rendered content and records a failed dispatch instead of sending unresolved placeholders', function () {
    Queue::fake();
    $user = User::factory()->create(['preferred_locale' => 'ar']);
    NotificationTemplate::factory()->create([
        'event_key' => 'booking.alternative',
        'body' => ['en' => 'Booking {{booking_id}}', 'ar' => 'الحجز {{booking_id}}'],
        'subject' => ['en' => '{{booking_id}}', 'ar' => '{{booking_id}}'],
    ]);
    $action = app(DispatchNotificationAction::class);
    $dto = fn (array $context) => new DispatchNotificationDTO(
        eventKey: 'booking.alternative', channel: NotificationChannel::Push,
        audience: NotificationAudience::Customer, eventCategory: EventCategory::Booking,
        userId: $user->id, context: $context,
    );
    $dispatch = $action->execute($dto(['booking_id' => '01PUBLIC']));
    expect($dispatch?->context['body'])->toBe('الحجز 01PUBLIC');
    expect($action->execute($dto([])))->toBeNull();
    $failed = NotificationDispatch::query()->where('status', DispatchStatus::Failed->value)->firstOrFail();
    expect($failed->error_message)->toContain('booking_id');
});
