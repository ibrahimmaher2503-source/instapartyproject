<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Application\Services\TemplateResolver;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Communication\Filament\Resources\NotificationTemplateResource;
use App\Modules\Communication\Filament\Resources\NotificationTemplateResource\Pages\CreateNotificationTemplate;
use App\Modules\Communication\Filament\Resources\NotificationTemplateResource\Pages\EditNotificationTemplate;
use App\Modules\Identity\Domain\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function qaVerifyTemplateAdmin(): User
{
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(
        Permission::findOrCreate('view_any_notification::template', 'web'),
        Permission::findOrCreate('view_notification::template', 'web'),
        Permission::findOrCreate('create_notification::template', 'web'),
        Permission::findOrCreate('update_notification::template', 'web'),
    );

    return $admin;
}

it('shows required-field errors when a notification template is submitted empty', function (): void {
    app()->setLocale('en');
    $this->actingAs(qaVerifyTemplateAdmin());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $before = NotificationTemplate::query()->count();

    $form = Livewire::test(CreateNotificationTemplate::class);
    $fields = collect($form->instance()->form->getFlatComponents())
        ->filter(fn ($field): bool => method_exists($field, 'getName'))
        ->keyBy(fn ($field): string => $field->getName());

    foreach (['event_key', 'channel', 'audience', 'body'] as $name) {
        expect($fields[$name]->isRequired())->toBeFalse()
            ->and($fields[$name]->isMarkedAsRequired())->toBeTrue();
    }

    $form
        ->call('create')
        ->assertHasFormErrors([
            'event_key' => 'required',
            'channel' => 'required',
            'audience' => 'required',
            'body' => 'required',
        ])
        ->assertSeeText('The event Key field is required.')
        ->assertSeeText('The delivery Channel field is required.')
        ->assertSeeText('The target Audience field is required.')
        ->assertSeeText('The message Body field is required.');

    expect(NotificationTemplate::query()->count())->toBe($before);
});

it('edits bilingual template content and renders a local test dispatch from the saved event template', function (): void {
    Queue::fake();
    $template = NotificationTemplate::factory()->create([
        'event_key' => 'booking.content_qa',
        'channel' => NotificationChannel::InApp,
        'audience' => NotificationAudience::Customer,
        'subject' => ['en' => 'Old subject', 'ar' => 'عنوان قديم'],
        'body' => ['en' => 'Old body', 'ar' => 'محتوى قديم'],
        'variables' => ['recipient_name' => 'Recipient name'],
    ]);

    $this->actingAs(qaVerifyTemplateAdmin());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $edit = Livewire::test(EditNotificationTemplate::class, ['record' => $template->getRouteKey()])
        ->assertSuccessful()
        ->fillForm([
            'subject' => 'Booking update for {{recipient_name}}',
            'body' => 'Booking {{recipient_name}} is confirmed.',
            'variables' => ['recipient_name' => 'Customer display name'],
        ]);
    $edit->set('activeLocale', 'ar');
    $edit->fillForm([
        'subject' => 'تحديث الحجز لـ {{recipient_name}}',
        'body' => 'تم تأكيد حجز {{recipient_name}}.',
        'variables' => ['recipient_name' => 'اسم العميل المعروض'],
    ])->call('save')->assertHasNoFormErrors();

    $template->refresh();
    expect($template->getTranslations('subject'))->toBe([
        'en' => 'Booking update for {{recipient_name}}',
        'ar' => 'تحديث الحجز لـ {{recipient_name}}',
    ])
        ->and($template->getTranslations('body'))->toBe([
            'en' => 'Booking {{recipient_name}} is confirmed.',
            'ar' => 'تم تأكيد حجز {{recipient_name}}.',
        ])
        ->and($template->variables)->toBe(['recipient_name' => 'اسم العميل المعروض']);

    $user = User::factory()->create(['preferred_locale' => 'ar']);
    $dispatch = app(DispatchNotificationAction::class)->execute(new DispatchNotificationDTO(
        eventKey: 'booking.content_qa',
        channel: NotificationChannel::InApp,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Booking,
        userId: $user->id,
        context: ['recipient_name' => '<QA name>'],
    ));

    expect($dispatch)->toBeInstanceOf(NotificationDispatch::class)
        ->and($dispatch->notification_template_id)->toBe($template->id)
        ->and($dispatch->user_id)->toBe($user->id)
        ->and($dispatch->channel)->toBe(NotificationChannel::InApp)
        ->and($dispatch->status)->toBe(DispatchStatus::Queued)
        ->and($dispatch->context['body'])->toBe('تم تأكيد حجز <QA name>.')
        ->and($dispatch->context['subject'])->toBe('تحديث الحجز لـ <QA name>');

    $resolvedEnglish = app(TemplateResolver::class)->resolve(
        'booking.content_qa', NotificationChannel::InApp, NotificationAudience::Customer, 'en',
    );
    expect($resolvedEnglish->render(['recipient_name' => 'English QA'])['body'])
        ->toBe('Booking English QA is confirmed.');

    $template->setTranslation('body', 'ar', '');
    $template->setTranslation('subject', 'ar', '');
    $template->save();
    $resolvedFallback = app(TemplateResolver::class)->resolve(
        'booking.content_qa', NotificationChannel::InApp, NotificationAudience::Customer, 'ar',
    );
    expect($resolvedFallback->render(['recipient_name' => 'QA fallback'])['body'])
        ->toBe('Booking QA fallback is confirmed.');

    Queue::assertPushed(DispatchNotificationJob::class, fn (DispatchNotificationJob $job): bool => $job->dispatchId === $dispatch->id);
});

it('rejects edited template bodies with undocumented placeholders', function (): void {
    expect(fn () => NotificationTemplateResource::validateFormData([
        'subject' => ['en' => '{{booking_id}}', 'ar' => 'رقم الحجز {{booking_id}}'],
        'body' => ['en' => 'Booking {{booking_id}}', 'ar' => 'الحجز {{booking_id}}'],
        'variables' => [],
    ]))->toThrow(ValidationException::class);

    expect(fn () => NotificationTemplateResource::validateFormData([
        'subject' => ['en' => 'Booking', 'ar' => 'حجز'],
        'body' => ['en' => 'Booking', 'ar' => ''],
        'variables' => [],
    ]))->toThrow(ValidationException::class);
});
