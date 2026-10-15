<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Communication\Filament\Vendor\Pages\VendorNotificationPreferencesPage;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('shows mandatory system notifications enabled and preserves optional choices with save feedback', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $this->actingAs($vendor->user, 'vendor');
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $component = Livewire::test(VendorNotificationPreferencesPage::class);
    foreach (NotificationChannel::cases() as $channel) {
        $component->assertFormSet([$channel->value.'_'.EventCategory::System->value => true]);
    }
    $component->fillForm(['email_marketing' => false])->call('save')->assertNotified();
    expect(NotificationPreference::query()->where('user_id', $vendor->user_id)->where('channel', 'email')->where('event_category', 'marketing')->value('is_enabled'))->toBeFalse();
    expect(NotificationPreference::isEnabledFor($vendor->user_id, NotificationChannel::Email, EventCategory::System))->toBeTrue();
    Livewire::test(VendorNotificationPreferencesPage::class)->assertFormSet(['email_system' => true, 'email_marketing' => false]);
});
