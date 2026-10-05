<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;

it('renders the homepage builder in the required dependent-field order without category', function (): void {
    $governorate = Governorate::factory()->create([
        'name' => ['en' => 'Homepage Governorate', 'ar' => 'محافظة الصفحة الرئيسية'],
        'is_active' => true,
    ]);
    City::factory()->create([
        'governorate_id' => $governorate->id,
        'is_active' => true,
    ]);

    $response = $this->get('/en');
    $response->assertOk();

    $html = $response->getContent();
    $formStart = strpos($html, '<form class="sf-party-builder__form"');
    $form = substr($html, $formStart, strpos($html, '</form>', $formStart) - $formStart);

    expect($form)
        ->toContain('name="occasion"')
        ->toContain('name="governorate_public_id"')
        ->toContain('name="city_public_id"')
        ->toContain('name="event_starts_at"')
        ->toContain('type="datetime-local"')
        ->toContain('Homepage Governorate')
        ->not->toContain('name="category"');
    expect(strpos($form, 'name="occasion"'))
        ->toBeLessThan(strpos($form, 'name="governorate_public_id"'));
    expect(strpos($form, 'name="governorate_public_id"'))
        ->toBeLessThan(strpos($form, 'name="city_public_id"'));
    expect(strpos($form, 'name="city_public_id"'))
        ->toBeLessThan(strpos($form, 'name="event_starts_at"'));
    expect($form)->toMatch('/name="city_public_id"[^>]*disabled/');
});

it('accepts valid homepage builder selections on the search page', function (): void {
    $occasion = Occasion::factory()->active()->create(['code' => 'homepage-builder-occasion']);
    $governorate = Governorate::factory()->create(['is_active' => true]);
    $city = City::factory()->create([
        'governorate_id' => $governorate->id,
        'is_active' => true,
    ]);
    $startsAt = now()->addDay()->format('Y-m-d\TH:i');

    $response = $this->get('/en/search?'.http_build_query([
        'occasion' => $occasion->code,
        'governorate_public_id' => $governorate->public_id,
        'city_public_id' => $city->public_id,
        'event_starts_at' => $startsAt,
    ]));

    $response
        ->assertOk()
        ->assertSee('value="'.$occasion->code.'" selected', false)
        ->assertSee('value="'.$governorate->public_id.'" selected', false)
        ->assertSee('value="'.$city->public_id.'" selected', false)
        ->assertSee('name="event_starts_at" value="'.$startsAt.'"', false);
});
