<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Events\ServiceRatingRecomputed;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;

it('backfills service search ratings from approved reviews and can be rerun', function (): void {
    config(['scout.driver' => 'database']);

    $ratedFour = Service::factory()->sale()->published()->create();
    $ratedFive = Service::factory()->sale()->published()->create();
    $pendingOnly = Service::factory()->sale()->published()->create();
    $rejectedOnly = Service::factory()->sale()->published()->create();
    Service::factory()->sale()->published()->create();

    $approvedFour = ServiceReview::factory()->approved()->create([
        'service_id' => $ratedFour->id,
        'rating' => 5,
    ]);
    ServiceReview::factory()->approved()->create([
        'service_id' => $ratedFour->id,
        'rating' => 3,
    ]);
    ServiceReview::factory()->approved()->create([
        'service_id' => $ratedFour->id,
        'rating' => 5,
    ])->delete();
    ServiceReview::factory()->approved()->create([
        'service_id' => $ratedFive->id,
        'rating' => 5,
    ]);
    $pending = ServiceReview::factory()->create([
        'service_id' => $pendingOnly->id,
        'rating' => 5,
        'moderation_status' => ModerationStatus::Pending,
    ]);
    $rejected = ServiceReview::factory()->rejected()->create([
        'service_id' => $rejectedOnly->id,
        'rating' => 5,
    ]);

    DB::table('services')->update(['rating_avg' => 0, 'rating_count' => 0]);
    $migration = require app_path('Modules/Reviews/Database/Migrations/2026_10_19_000001_backfill_service_rating_aggregates.php');
    $migration->up();

    expect((float) $ratedFour->fresh()->rating_avg)->toBe(4.0)
        ->and($ratedFour->fresh()->rating_count)->toBe(2)
        ->and((float) $ratedFive->fresh()->rating_avg)->toBe(5.0)
        ->and($ratedFive->fresh()->rating_count)->toBe(1)
        ->and((float) $pendingOnly->fresh()->rating_avg)->toBe(0.0)
        ->and($pendingOnly->fresh()->rating_count)->toBe(0)
        ->and((float) $rejectedOnly->fresh()->rating_avg)->toBe(0.0)
        ->and($rejectedOnly->fresh()->rating_count)->toBe(0)
        ->and($pending->fresh()->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($rejected->fresh()->moderation_status)->toBe(ModerationStatus::Rejected)
        ->and($approvedFour->fresh()->moderation_status)->toBe(ModerationStatus::Approved);

    expect($this->get('/en/search?min_rating=4')->assertOk()->viewData('services')->total())->toBe(2);

    $migration->up();

    expect((float) $ratedFour->fresh()->rating_avg)->toBe(4.0)
        ->and($ratedFour->fresh()->rating_count)->toBe(2)
        ->and((float) $ratedFive->fresh()->rating_avg)->toBe(5.0)
        ->and($ratedFive->fresh()->rating_count)->toBe(1);
});

it('reindexes a published service after its rating aggregate changes', function (): void {
    $published = Service::factory()->sale()->published()->create();
    $unpublished = Service::factory()->sale()->create();
    DB::table('services')->where('id', $published->id)->update([
        'rating_avg' => 4.5,
        'rating_count' => 2,
    ]);

    config(['scout.driver' => 'issue288-rating-engine', 'scout.queue' => false]);
    $engine = Mockery::mock(Engine::class);
    $engine->shouldReceive('update')
        ->once()
        ->with(Mockery::on(fn (EloquentCollection $services): bool => $services->modelKeys() === [$published->id]
            && (float) $services->first()->rating_avg === 4.5
            && (int) $services->first()->rating_count === 2));
    app(EngineManager::class)->extend('issue288-rating-engine', fn () => $engine);

    expect(Event::hasListeners(ServiceRatingRecomputed::class))->toBeTrue();

    event(new ServiceRatingRecomputed($published->id, $published->public_id, 4.5, 2));
    event(new ServiceRatingRecomputed($unpublished->id, $unpublished->public_id, 5.0, 1));
});
