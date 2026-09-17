<?php

declare(strict_types=1);

namespace App\Modules\Booking\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'booking:release-expired-reservations';

    protected $description = 'Release held inventory reservations that have passed their TTL';

    public function handle(): int
    {
        $now = now();
        $released = 0;

        DB::table('service_inventory_reservations')
            ->where('status', 'held')
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(200, function ($reservations) use ($now, &$released): void {
                foreach ($reservations as $reservation) {
                    $released += DB::table('service_inventory_reservations')
                        ->where('id', $reservation->id)
                        ->where('status', 'held')
                        ->where('expires_at', '<=', $now)
                        ->update([
                            'status' => 'expired',
                            'updated_at' => now(),
                        ]);
                }
            });

        $this->info("Released {$released} expired reservations.");

        return Command::SUCCESS;
    }
}
