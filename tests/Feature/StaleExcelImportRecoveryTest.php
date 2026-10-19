<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\FailStaleExcelImportsAction;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('fails imports left pending or processing past the staleness window', function (): void {
    $oldPending = ExcelImport::factory()->create(['status' => 'pending']);
    $oldProcessing = ExcelImport::factory()->processing()->create();
    $fresh = ExcelImport::factory()->processing()->create();
    $completed = ExcelImport::factory()->completed()->create();

    ExcelImport::query()->whereKey([$oldPending->id, $oldProcessing->id])->update(['updated_at' => now()->subHours(7)]);

    $failed = app(FailStaleExcelImportsAction::class)->execute(6);

    expect($failed)->toBe(2)
        ->and($oldPending->refresh()->status)->toBe('failed')
        ->and($oldProcessing->refresh()->status)->toBe('failed')
        ->and($oldPending->errors()->where('field', 'import')->count())->toBe(1)
        ->and($fresh->refresh()->status)->toBe('processing')
        ->and($completed->refresh()->status)->toBe('completed');
});

it('schedules the stale import sweep', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) ($event->description ?? ''), 'catalog:fail-stale-excel-imports')
            || ($event->name ?? '') === 'catalog:fail-stale-excel-imports');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *');
});
