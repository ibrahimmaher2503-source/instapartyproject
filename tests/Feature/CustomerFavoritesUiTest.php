<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Identity\Domain\Models\User;

it('lets a customer add a published service from search and remove only their favorite', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $otherCustomer = User::factory()->asCustomer()->create();
    $service = Service::factory()->sale()->published()->create([
        'name' => ['en' => 'Favorite storefront marker', 'ar' => 'خدمة مفضلة للاختبار'],
    ]);

    $this->actingAs($customer, 'web')
        ->get(route('storefront.search', ['locale' => 'en', 'q' => 'Favorite storefront marker']))
        ->assertOk()
        ->assertSee(__('discovery.account.add_favorite'))
        ->assertSee(route('storefront.favorites.store', ['locale' => 'en', 'servicePublicId' => $service->public_id]), false);

    $this->get(route('storefront.services.show', ['locale' => 'en', 'servicePublicId' => $service->public_id]))
        ->assertOk()
        ->assertSee(__('discovery.account.add_favorite'));
    $this->get(route('storefront.services.show', ['locale' => 'ar', 'servicePublicId' => $service->public_id]))
        ->assertOk()
        ->assertSee(__('discovery.account.add_favorite', [], 'ar'));

    $this->post(route('storefront.favorites.store', ['locale' => 'en', 'servicePublicId' => $service->public_id]))
        ->assertRedirect(route('storefront.account.favorites', ['locale' => 'en']))
        ->assertSessionHas('status', __('discovery.wishlist_added'));

    $wishlist = Wishlist::query()->where('user_id', $customer->id)->firstOrFail();
    expect($wishlist->items()->where('service_id', $service->id)->exists())->toBeTrue();

    $this->get(route('storefront.account.favorites', ['locale' => 'en']))
        ->assertOk()
        ->assertSee($service->getTranslation('name', 'en'))
        ->assertSee('Favorites (1)')
        ->assertSee(__('discovery.account.remove_favorite'));

    $this->actingAs($otherCustomer, 'web')
        ->delete(route('storefront.favorites.destroy', ['locale' => 'en', 'servicePublicId' => $service->public_id]))
        ->assertNotFound();
    expect($wishlist->items()->where('service_id', $service->id)->exists())->toBeTrue();

    $this->actingAs($customer, 'web')
        ->delete(route('storefront.favorites.destroy', ['locale' => 'en', 'servicePublicId' => $service->public_id]))
        ->assertRedirect();
    expect($wishlist->items()->where('service_id', $service->id)->exists())->toBeFalse();
});

it('does not expose favorite controls to guests', function (): void {
    $service = Service::factory()->sale()->published()->create([
        'name' => ['en' => 'Guest favorite marker', 'ar' => 'عنصر اختبار الزائر'],
    ]);

    $this->get(route('storefront.services.show', ['locale' => 'en', 'servicePublicId' => $service->public_id]))
        ->assertOk()
        ->assertDontSee(__('discovery.account.add_favorite'));

    $this->post(route('storefront.favorites.store', ['locale' => 'en', 'servicePublicId' => $service->public_id]))
        ->assertRedirect();
});
