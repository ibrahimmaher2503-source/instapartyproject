<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('services')->select('id')->orderBy('id')->chunkById(500, function ($services): void {
            $serviceIds = $services->pluck('id')->all();
            $aggregates = DB::table('service_reviews')
                ->whereIn('service_id', $serviceIds)
                ->where('moderation_status', 'approved')
                ->whereNull('deleted_at')
                ->select('service_id')
                ->selectRaw('AVG(rating) as average, COUNT(*) as count')
                ->groupBy('service_id')
                ->get()
                ->keyBy('service_id');

            foreach ($serviceIds as $serviceId) {
                $aggregate = $aggregates->get($serviceId);

                DB::table('services')->where('id', $serviceId)->update([
                    'rating_avg' => round((float) ($aggregate->average ?? 0), 2),
                    'rating_count' => (int) ($aggregate->count ?? 0),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Reverting derived values would restore stale rating projections.
    }
};
