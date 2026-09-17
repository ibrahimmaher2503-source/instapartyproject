<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Reviews\Database\Factories\ServiceReviewFactory;
use App\Modules\Reviews\Domain\Enums\ReviewLocale;
use App\Modules\Settlement\Filament\Pages\DisputeOversightPage;
use Filament\Facades\Filament;

it('localizes review moderation values in Arabic', function (): void {
    $admin = User::factory()->state(['preferred_locale' => 'ar'])->superAdmin()->create();
    ServiceReviewFactory::new()->pending()->create(['locale' => ReviewLocale::Mixed]);

    app()->setLocale('ar');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $response = $this->get('/admin/review-moderation-page?lang=ar');
    $html = (string) $response->getContent();

    $response->assertOk()
        ->assertSeeText('إشراف التقييمات')
        ->assertSeeText('تقييم خدمة')
        ->assertSeeText('مختلطة')
        ->assertSeeText('قيد المراجعة');

    expect($html)
        ->not->toMatch('/>\s*service\s*</i')
        ->not->toMatch('/>\s*mixed\s*</i')
        ->not->toMatch('/>\s*pending\s*</i');
});

it('localizes dispute statuses and reason codes without raw financial values', function (): void {
    $admin = User::factory()->state(['preferred_locale' => 'ar'])->superAdmin()->create();
    Refund::factory()->create([
        'amount_minor' => 65000,
        'amount_currency' => 'EGP',
        'reason_code' => RefundReasonCode::CustomerRequest,
        'reason_notes' => ['en' => 'Test refund request', 'ar' => 'طلب استرداد تجريبي'],
        'status' => RefundStatus::Pending,
    ]);

    app()->setLocale('ar');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $response = $this->get('/admin/dispute-oversight-page?lang=ar');
    $html = (string) $response->getContent();
    $expectedAmount = (new DisputeOversightPage)->formatMoney(65000, 'EGP');

    $response->assertOk()
        ->assertSeeText('مراقبة النزاعات')
        ->assertSeeText('طلب العميل')
        ->assertSeeText('طلب استرداد تجريبي')
        ->assertSeeText('قيد الانتظار');

    expect($html)
        ->toContain($expectedAmount)
        ->not->toMatch('/>\s*customer_request\s*</i')
        ->not->toMatch('/>\s*pending\s*</i');
});
