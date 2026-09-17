<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

it('refuses automatic payouts without transfer proof', function (): void {
    expect(Artisan::call('settle:run'))->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('Automatic payouts are disabled');
});
