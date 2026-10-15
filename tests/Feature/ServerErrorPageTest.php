<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('renders readable server errors before module translations are available', function (string $locale, string $path, string $dashboard): void {
    app('translation.loader')->addNamespace('shared', base_path('tests/Fixtures/missing-translations'));
    app('translator')->setLoaded([]);
    app()->setLocale($locale);
    Route::get($path, fn () => abort(500));

    $this->get($path)
        ->assertStatus(500)
        ->assertSee($locale === 'ar' ? 'تعذّر إكمال الطلب' : 'We could not complete this request')
        ->assertSee($locale === 'ar' ? 'مرجع الدعم' : 'Support reference')
        ->assertSee('href="'.$dashboard.'"', false)
        ->assertDontSee('shared::panel.')
        ->assertHeader('X-Trace-Id');
})->with(['en', 'ar'])->with([
    ['/admin/qa-server-error', '/admin'],
    ['/vendor-portal/qa-server-error', '/vendor-portal'],
    ['/qa-server-error', '/'],
]);
