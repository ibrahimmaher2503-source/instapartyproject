<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Route;

it('disables production debug even when cached configuration enables it', function (): void {
    app()->instance('env', 'production');
    config(['app.debug' => true]);

    (new AppServiceProvider(app()))->register();

    expect(config('app.debug'))->toBeFalse();
});

it('preserves explicitly enabled local debug', function (): void {
    app()->instance('env', 'local');
    config(['app.debug' => true]);

    (new AppServiceProvider(app()))->register();

    expect(config('app.debug'))->toBeTrue();
});

it('redacts production method and server errors with unsafe cached debug enabled', function (): void {
    app()->instance('env', 'production');
    config(['app.debug' => true]);
    (new AppServiceProvider(app()))->register();
    Route::get('/qa-production-failure', fn () => throw new RuntimeException('private-provider-secret'));

    foreach ([$this->post('/vendor-portal/login'), $this->get('/qa-production-failure')] as $index => $response) {
        $response->assertStatus($index === 0 ? 405 : 500)
            ->assertSee('InstaParty')
            ->assertDontSee('private-provider-secret')
            ->assertDontSee('MethodNotAllowedHttpException')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('vendor/laravel/framework')
            ->assertDontSee('Stack trace');
    }
});
