<?php

declare(strict_types=1);

use App\Modules\Catalog\Database\Factories\ServiceFactory;
use App\Modules\Discovery\Database\Factories\WishlistFactory;
use App\Modules\Discovery\Database\Factories\WishlistItemFactory;
use App\Modules\Identity\Domain\Models\User;
use Laravel\Sanctum\Sanctum;

it('reports published wishlist services available for every product type', function (): void {
    $customer = User::factory()->asCustomer()->create();
    Sanctum::actingAs($customer);

    foreach ([
        'rental' => ServiceFactory::new()->rental()->published(),
        'sale' => ServiceFactory::new()->sale()->published(),
        'digital' => ServiceFactory::new()->digital()->published(),
    ] as $productType => $factory) {
        $service = $factory->create();

        $this->postJson('/api/v1/customer/wishlist/items', ['service_id' => $service->public_id])
            ->assertCreated()
            ->assertJsonPath('data.product_type', $productType)
            ->assertJsonPath('data.is_available', true);
    }
});

it('reports unpublished wishlist services unavailable for every product type', function (): void {
    $customer = User::factory()->asCustomer()->create();
    Sanctum::actingAs($customer);
    $wishlist = WishlistFactory::new()->create(['user_id' => $customer->id]);

    foreach ([
        'rental' => ServiceFactory::new()->rental(),
        'sale' => ServiceFactory::new()->sale(),
        'digital' => ServiceFactory::new()->digital(),
    ] as $factory) {
        $service = $factory->create();
        WishlistItemFactory::new()->create([
            'wishlist_id' => $wishlist->id,
            'service_id' => $service->id,
        ]);
    }

    $response = $this->getJson('/api/v1/customer/wishlist')->assertOk()->assertJsonCount(3, 'data');
    $itemsByType = collect($response->json('data'))->keyBy('product_type');

    foreach (['rental', 'sale', 'digital'] as $productType) {
        expect($itemsByType->get($productType)['is_available'])->toBeFalse();
    }
});
