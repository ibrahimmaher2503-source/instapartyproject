<?php

declare(strict_types=1);

use App\Modules\Discovery\Domain\Models\SavedSearch;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use Laravel\Sanctum\Sanctum;

it('removes the planning account link while keeping the public wizard and legacy plans route', function (string $locale): void {
    $user = User::factory()->asCustomer()->create();
    $html = $this->actingAs($user)->get("/{$locale}/account/profile")->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//aside[contains(@class,"sf-account-nav")]//a[contains(@href,"/wizard")]')->length)->toBe(0);
    $this->get("/{$locale}/wizard")->assertOk();
    $this->get("/{$locale}/account/plans")->assertOk();
})->with(['en', 'ar']);

it('shows only the signed-in customers existing addresses searches and loyalty balance', function (string $locale): void {
    $customer = User::factory()->asCustomer()->create();
    $other = User::factory()->asCustomer()->create();
    $city = City::factory()->create();
    CustomerAddress::factory()->forUser($customer)->create([
        'city_id' => $city->id,
        'label' => 'Owned address marker',
        'address_line' => '12 Customer Street',
    ]);
    $foreignAddress = CustomerAddress::factory()->forUser($other)->create(['label' => 'Foreign address marker']);
    SavedSearch::factory()->create(['user_id' => $customer->id, 'label' => 'Owned search marker']);
    $foreignSearch = SavedSearch::factory()->create(['user_id' => $other->id, 'label' => 'Foreign search marker']);
    $program = LoyaltyProgram::factory()->create();
    $ownedEntry = LoyaltyLedgerEntry::factory()->create([
        'user_id' => $customer->id,
        'vendor_profile_id' => $program->vendor_profile_id,
        'loyalty_program_id' => $program->id,
        'points' => 42,
        'balance_after' => 42,
        'reason' => ['en' => 'Owned reward marker', 'ar' => 'مكافأة الحساب'],
    ]);
    $foreignEntry = LoyaltyLedgerEntry::factory()->create([
        'user_id' => $other->id,
        'reason' => ['en' => 'Foreign reward marker', 'ar' => 'مكافأة أجنبية'],
        'balance_after' => 999,
    ]);

    $this->actingAs($customer, 'web');
    $this->get("/{$locale}/account/addresses")
        ->assertOk()->assertSeeText('12 Customer Street')->assertDontSeeText('Foreign address marker');
    $this->get("/{$locale}/account/saved-searches")
        ->assertOk()->assertSeeText('Owned search marker')->assertDontSeeText($foreignSearch->label);
    $loyaltyResponse = $this->get("/{$locale}/account/loyalty")->assertOk();
    $loyaltyDocument = new DOMDocument;
    @$loyaltyDocument->loadHTML('<?xml encoding="UTF-8">'.$loyaltyResponse->getContent());
    $loyaltyContent = (new DOMXPath($loyaltyDocument))
        ->query('//section[contains(@class,"sf-account-content")]')
        ->item(0)?->textContent;
    expect($loyaltyContent)->toContain('42')->not->toContain('999');

    expect($foreignAddress->user_id)->toBe($other->id)
        ->and($ownedEntry->user_id)->toBe($customer->id)
        ->and($foreignEntry->user_id)->toBe($other->id);
})->with(['en', 'ar']);

it('saves the two allowed gender values on web and API and rejects new legacy values', function (string $locale): void {
    $user = User::factory()->asCustomer()->create();
    $this->actingAs($user);
    foreach (['male', 'female'] as $gender) {
        $this->put("/{$locale}/account/profile", ['gender' => $gender])->assertRedirect();
        expect($user->customerProfile()->sole()->gender)->toBe($gender);
    }
    $this->put("/{$locale}/account/profile", ['gender' => 'prefer_not_to_say'])
        ->assertSessionHasErrors('gender');
    expect($user->customerProfile()->sole()->gender)->toBe('female');

    Sanctum::actingAs($user);
    foreach (['male', 'female'] as $gender) {
        $this->withHeader('Accept-Language', $locale)->putJson('/api/v1/customer/profile', ['gender' => $gender])->assertOk();
        expect($user->customerProfile()->sole()->gender)->toBe($gender);
    }
    $this->putJson('/api/v1/customer/profile', ['gender' => 'prefer_not_to_say'])
        ->assertUnprocessable()->assertJsonValidationErrors('gender');
})->with(['en', 'ar']);

it('keeps a legacy gender readable and unchanged when saving unrelated profile fields', function (string $locale): void {
    $user = User::factory()->asCustomer()->create();
    CustomerProfile::query()->updateOrCreate(['user_id' => $user->id], ['gender' => 'prefer_not_to_say']);
    $this->actingAs($user)->get("/{$locale}/account/profile")
        ->assertOk()->assertDontSee('value="prefer_not_to_say"', false);
    $this->put("/{$locale}/account/profile", ['name' => 'Changed Name', 'gender' => null])->assertRedirect();
    expect($user->refresh()->name)->toBe('Changed Name')
        ->and($user->customerProfile()->sole()->gender)->toBe('prefer_not_to_say');
})->with(['en', 'ar']);

it('isolates profile phone numbers and dates from RTL ordering', function (string $locale): void {
    $user = User::factory()->asCustomer()->create(['phone_e164' => '+201012345678']);

    $this->actingAs($user)->get("/{$locale}/account/profile")
        ->assertOk()
        ->assertSee('<input class="sf-field" type="date" name="date_of_birth"', false)
        ->assertSee('dir="ltr"', false)
        ->assertSee('<bdi dir="ltr">+201012345678</bdi>', false);
})->with(['en', 'ar']);
