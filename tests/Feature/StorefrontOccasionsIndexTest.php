<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Occasion;
use Illuminate\Support\Facades\Blade;

it('links the homepage to all active occasions without redirecting to search', function (string $locale): void {
    app()->setLocale($locale);
    $occasions = collect(range(1, 7))->map(fn (int $index) => Occasion::factory()->create([
        'code' => 'occasion-'.$index,
        'name' => ['en' => 'Occasion '.$index, 'ar' => 'مناسبة '.$index],
        'is_active' => true,
        'sort_order' => $index,
    ]));
    Occasion::factory()->create(['name' => ['en' => 'Hidden occasion', 'ar' => 'مناسبة مخفية'], 'is_active' => false]);
    $html = Blade::render('<x-home.taxonomy :block="$block" :items="$items" kind="occasion" />', [
        'block' => ['public_id' => 'test-occasions', 'payload' => []], 'items' => $occasions,
    ]);
    expect($html)->toContain('/'.$locale.'/occasions');
    $response = $this->get('/'.$locale.'/occasions')->assertOk();
    foreach ($occasions as $occasion) {
        $response->assertSee($occasion->getTranslation('name', $locale))
            ->assertSee('/'.$locale.'/o/'.$occasion->code, false);
    }
    $response->assertDontSee('Hidden occasion')->assertDontSee('مناسبة مخفية');
    $this->get('/'.$locale.'/o/'.$occasions->first()->code)
        ->assertRedirect('/'.$locale.'/search?occasion='.$occasions->first()->code);
})->with(['en', 'ar']);
