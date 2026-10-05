<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Domain\Models\User;
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
