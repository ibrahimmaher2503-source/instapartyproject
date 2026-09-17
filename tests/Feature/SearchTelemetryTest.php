<?php

declare(strict_types=1);

use App\Modules\Discovery\Application\Listeners\LogSearchQueryListener;
use App\Modules\Discovery\Domain\Events\ServiceSearchPerformed;
use App\Modules\Discovery\Domain\Models\SearchLog;

it('skips meaningless searches and keeps filter-only searches explicit', function () {
    $listener = app(LogSearchQueryListener::class);

    $listener->handle(new ServiceSearchPerformed('   ', 'en', [], 0, null));
    $listener->handle(new ServiceSearchPerformed(null, 'en', ['product_type' => 'rental'], 6, null));

    expect(SearchLog::query()->count())->toBe(1)
        ->and(SearchLog::query()->firstOrFail()->query)->toBe('')
        ->and(SearchLog::query()->firstOrFail()->filters)->toBe(['product_type' => 'rental']);
});
