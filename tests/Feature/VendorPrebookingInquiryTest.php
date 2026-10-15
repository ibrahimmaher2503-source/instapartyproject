<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Communication\Application\Actions\CreateVendorInquiryAction;
use App\Modules\Communication\Application\Actions\SendVendorInquiryMessageAction;
use App\Modules\Communication\Domain\Models\VendorInquiry;
use App\Modules\Communication\Domain\Models\VendorInquiryMessage;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;

function inquiryService(): Service
{
    $service = Service::factory()->published()->create();
    $service->vendor->approvedTypes()->create([
        'product_type' => $service->product_type,
        'approved_at' => now(),
    ]);

    return $service;
}

function inquiryThreadFor(Service $service, User $customer): VendorInquiry
{
    app(CreateVendorInquiryAction::class)->execute(
        $service->public_id,
        $customer->id,
        (string) $customer->name,
        'Could you share what is included?',
    );

    return VendorInquiry::query()->where('customer_id', $customer->id)->latest('id')->firstOrFail();
}

it('requires a signed-in customer to start a pre-booking inquiry', function (): void {
    $service = inquiryService();

    $this->get(route('storefront.inquiries.create', [
        'locale' => 'en',
        'servicePublicId' => $service->public_id,
    ]))->assertRedirect();

    $this->actingAs(User::factory()->asVendor()->create())
        ->get(route('storefront.inquiries.create', [
            'locale' => 'en',
            'servicePublicId' => $service->public_id,
        ]))->assertForbidden();
});

it('creates a customer-owned thread tied to the published service and vendor without storing contact fields', function (): void {
    $service = inquiryService();
    $customer = User::factory()->asCustomer()->create();

    $this->actingAs($customer)->post(route('storefront.inquiries.store', [
        'locale' => 'en',
        'servicePublicId' => $service->public_id,
    ]), ['body' => 'Could you share what is included?'])->assertSessionHasNoErrors();

    $thread = VendorInquiry::query()->with('messages')->firstOrFail();
    expect($thread->public_id)->toHaveLength(26)
        ->and($thread->customer_id)->toBe($customer->id)
        ->and($thread->vendor_profile_id)->toBe($service->vendor_profile_id)
        ->and($thread->service_id)->toBe($service->id)
        ->and($thread->service_name_en)->toBe($service->getTranslation('name', 'en'))
        ->and($thread->messages)->toHaveCount(1)
        ->and($thread->messages->first()->author_role)->toBe('customer')
        ->and($thread->messages->first()->body)->toBe('Could you share what is included?');

    $this->get(route('storefront.inquiries.show', ['locale' => 'en', 'inquiryPublicId' => $thread->public_id]))
        ->assertOk()
        ->assertSee('Could you share what is included?')
        ->assertDontSee($customer->email)
        ->assertDontSee($customer->phone_e164);
    $this->get(route('storefront.services.show', ['locale' => 'en', 'servicePublicId' => $service->public_id]))
        ->assertOk()
        ->assertSee(__('communication::communication.vendor_inquiries.ask_vendor'))
        ->assertSee(route('storefront.inquiries.create', ['locale' => 'en', 'servicePublicId' => $service->public_id]), false);

    expect(Schema::hasColumn('vendor_inquiries', 'customer_email'))->toBeFalse()
        ->and(Schema::hasColumn('vendor_inquiries', 'customer_phone'))->toBeFalse();
});

it('does not open inquiries for unpublished or unknown services', function (): void {
    $draft = Service::factory()->create();
    $customer = User::factory()->asCustomer()->create();

    $this->actingAs($customer)->get(route('storefront.inquiries.create', [
        'locale' => 'en',
        'servicePublicId' => $draft->public_id,
    ]))->assertNotFound();

    $this->post(route('storefront.inquiries.store', [
        'locale' => 'en',
        'servicePublicId' => $draft->public_id,
    ]), ['body' => 'Please tell me more'])->assertNotFound();

    expect(VendorInquiry::query()->count())->toBe(0)
        ->and(VendorInquiryMessage::query()->count())->toBe(0);
});

it('does not create inquiries for services whose vendor is suspended or whose service type was revoked', function (string $ineligibility): void {
    $service = inquiryService();
    $vendor = $service->vendor;

    if ($ineligibility === 'suspended vendor') {
        $vendor->update([
            'approval_status' => 'suspended',
            'suspended_at' => now(),
        ]);
    } else {
        VendorApprovedProductType::query()
            ->where('vendor_profile_id', $vendor->id)
            ->where('product_type', $service->product_type->value)
            ->update(['revoked_at' => now()]);
    }

    $customer = User::factory()->asCustomer()->create();

    $this->actingAs($customer)
        ->post(route('storefront.inquiries.store', [
            'locale' => 'en',
            'servicePublicId' => $service->public_id,
        ]), ['body' => 'Is this service still available?'])
        ->assertNotFound();

    expect(VendorInquiry::query()->count())->toBe(0)
        ->and(VendorInquiryMessage::query()->count())->toBe(0);
})->with(['suspended vendor', 'revoked service type']);

it('keeps customer inquiry threads and replies private to their owner', function (): void {
    $service = inquiryService();
    $owner = User::factory()->asCustomer()->create();
    $otherCustomer = User::factory()->asCustomer()->create();
    $thread = inquiryThreadFor($service, $owner);

    $this->actingAs($owner)->post(route('storefront.inquiries.reply', [
        'locale' => 'en',
        'inquiryPublicId' => $thread->public_id,
    ]), ['body' => 'Could you confirm setup time?'])->assertRedirect();

    $this->actingAs($otherCustomer)
        ->get(route('storefront.inquiries.show', ['locale' => 'en', 'inquiryPublicId' => $thread->public_id]))
        ->assertNotFound();
    $this->post(route('storefront.inquiries.reply', ['locale' => 'en', 'inquiryPublicId' => $thread->public_id]), [
        'body' => 'I am not the owner.',
    ])->assertNotFound();

    expect($thread->messages()->count())->toBe(2)
        ->and($thread->fresh()->messages()->count())->toBe(2);
});

it('renders the pre-booking inquiry form in both storefront locales', function (string $locale): void {
    $service = inquiryService();
    $vendor = VendorProfile::query()->findOrFail($service->vendor_profile_id);
    $expectedVendorName = $vendor->getTranslation('business_name', $locale);

    $this->actingAs(User::factory()->asCustomer()->create())
        ->get(route('storefront.inquiries.create', [
            'locale' => $locale,
            'servicePublicId' => $service->public_id,
        ]))
        ->assertOk()
        ->assertSee($expectedVendorName)
        ->assertSee(__('communication::communication.vendor_inquiries.create_title', locale: $locale));
})->with([
    'english' => ['en'],
    'arabic' => ['ar'],
]);

it('preserves customer-authored plain text without reading contact fields from the account', function (string $body): void {
    $service = inquiryService();
    $customer = User::factory()->asCustomer()->create();

    $this->actingAs($customer)->post(route('storefront.inquiries.store', [
        'locale' => 'en',
        'servicePublicId' => $service->public_id,
    ]), ['body' => $body])->assertSessionHasNoErrors();

    $message = VendorInquiryMessage::query()->firstOrFail();
    expect(VendorInquiry::query()->count())->toBe(1)
        ->and($message->body)->toBe($body);
})->with([
    'email' => 'Write me at customer@example.test',
    'Egyptian phone' => 'Call me at 01012345678',
    'Arabic phone' => 'اتصل بي على ٠١٠١٢٣٤٥٦٧٨',
    'link' => 'Message me at https://outside.example.test',
]);

it('rejects an inquiry message longer than the supported text limit', function (): void {
    $service = inquiryService();
    $customer = User::factory()->asCustomer()->create();

    $this->actingAs($customer)->post(route('storefront.inquiries.store', [
        'locale' => 'en',
        'servicePublicId' => $service->public_id,
    ]), ['body' => str_repeat('x', 5001)])->assertSessionHasErrors('body');

    expect(VendorInquiry::query()->count())->toBe(0)
        ->and(VendorInquiryMessage::query()->count())->toBe(0);
});

it('allows only the owning vendor to append a vendor reply', function (): void {
    $service = inquiryService();
    $customer = User::factory()->asCustomer()->create();
    $thread = inquiryThreadFor($service, $customer);
    $ownVendor = VendorProfile::query()->findOrFail($service->vendor_profile_id);
    $otherVendor = VendorProfile::factory()->approved()->create();
    $action = app(SendVendorInquiryMessageAction::class);

    expect(fn () => $action->asVendor($thread->public_id, $otherVendor->user_id, $otherVendor->id, 'Try to reply.'))
        ->toThrow(ModelNotFoundException::class);
    $reply = $action->asVendor($thread->public_id, $ownVendor->user_id, $ownVendor->id, 'The details are listed below.');

    $this->actingAs($customer)->get(route('storefront.inquiries.show', [
        'locale' => 'en',
        'inquiryPublicId' => $thread->public_id,
    ]))->assertOk()->assertSee('The details are listed below.');

    expect($thread->messages()->count())->toBe(2)
        ->and(VendorInquiryMessage::query()->where('vendor_inquiry_id', $thread->id)->orderByDesc('id')->firstOrFail()->author_role)->toBe('vendor')
        ->and($reply->sender_user_id)->toBe($ownVendor->user_id);
});

it('scopes the vendor inbox and inquiry conversation to the signed-in vendor', function (): void {
    $ownedService = inquiryService();
    $otherService = inquiryService();
    $ownedCustomer = User::factory()->asCustomer()->create();
    $ownedInquiry = inquiryThreadFor($ownedService, $ownedCustomer);
    $otherInquiry = inquiryThreadFor($otherService, User::factory()->asCustomer()->create());
    $owner = VendorProfile::query()->findOrFail($ownedService->vendor_profile_id);

    $this->actingAs($owner->user, 'vendor')
        ->get(route('filament.vendor.pages.vendor-inquiries'))
        ->assertOk()
        ->assertSee($ownedInquiry->service_name_en)
        ->assertDontSee($otherInquiry->service_name_en);

    $this->get(route('filament.vendor.pages.vendor-inquiries.{inquiry}', ['inquiry' => $ownedInquiry->public_id]))
        ->assertOk()
        ->assertSee('Could you share what is included?')
        ->assertDontSee($ownedCustomer->email)
        ->assertDontSee($ownedCustomer->phone_e164);

    $this->get(route('filament.vendor.pages.vendor-inquiries.{inquiry}', ['inquiry' => $otherInquiry->public_id]))
        ->assertNotFound();
});
