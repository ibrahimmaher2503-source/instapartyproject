<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Filament\Pages\AdminTutorials;

it('does not expose admin tutorials when the viewer has no permitted flows', function (): void {
    $this->actingAs(User::factory()->asAdmin()->create(), 'web');

    expect(AdminTutorials::canAccess())->toBeFalse();
});
