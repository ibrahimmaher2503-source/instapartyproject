<?php

declare(strict_types=1);

use App\Modules\Discovery\Domain\Models\SavedSearch;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a signed-in customer save the current search filters', function (): void {
    $customer = User::factory()->asCustomer()->create();

    $this->actingAs($customer, 'web')
        ->post('/en/account/saved-searches', [
            'label' => 'Rentals in Cairo',
            'filters' => [
                'q' => 'castle',
                'product_type' => 'rental',
                'city_public_id' => '01JC7G3F0AY3T9V5K0X9N1QY3S',
                'price_max' => '300000',
                'page' => '3',
                'unexpected' => 'ignored',
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('saved_search_status');

    $saved = SavedSearch::query()->where('user_id', $customer->id)->sole();

    expect($saved->label)->toBe('Rentals in Cairo')
        ->and($saved->filters)->toBe([
            'q' => 'castle',
            'product_type' => 'rental',
            'city_public_id' => '01JC7G3F0AY3T9V5K0X9N1QY3S',
            'price_max' => '300000',
        ]);

    $this->get('/en/account/saved-searches')
        ->assertOk()
        ->assertSeeText('Rentals in Cairo');
});

it('requires a label and refuses to delete another customers saved search', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $other = User::factory()->asCustomer()->create();
    $foreign = SavedSearch::factory()->create(['user_id' => $other->id]);

    $this->actingAs($customer, 'web')
        ->post('/en/account/saved-searches', ['label' => ''])
        ->assertSessionHasErrors('label');

    $this->actingAs($customer, 'web')
        ->delete("/en/account/saved-searches/{$foreign->id}")
        ->assertNotFound();

    expect(SavedSearch::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('deletes a saved search owned by the signed-in customer', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $saved = SavedSearch::factory()->create(['user_id' => $customer->id]);

    $this->actingAs($customer, 'web')
        ->delete("/en/account/saved-searches/{$saved->id}")
        ->assertRedirect()
        ->assertSessionHas('saved_search_status');

    expect(SavedSearch::query()->whereKey($saved->id)->exists())->toBeFalse();
});

it('redirects guests away from saving a search', function (): void {
    $this->post('/en/account/saved-searches', ['label' => 'Guest search'])
        ->assertRedirect('/login');

    expect(SavedSearch::query()->count())->toBe(0);
});
