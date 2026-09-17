<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Seeders;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use App\Modules\Settlement\Domain\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReconciliationRunDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@instaparty.local')->first();
        $wallet = Wallet::query()->first();

        $rows = [
            [
                'scope_type' => 'all',
                'scope_params' => null,
                'status' => ReconciliationStatus::Clean,
                'trigger_kind' => 'scheduled',
                'wallets_scanned' => 12,
                'findings_count' => 0,
                'auto_repaired_count' => 0,
                'manual_review_count' => 0,
                'started_at' => now()->subHours(6),
                'completed_at' => now()->subHours(6)->addMinutes(3),
            ],
            [
                'scope_type' => 'recent_touch',
                'scope_params' => ['hours' => 24],
                'status' => ReconciliationStatus::AnomaliesDetected,
                'trigger_kind' => 'manual',
                'wallets_scanned' => 8,
                'findings_count' => 2,
                'auto_repaired_count' => 1,
                'manual_review_count' => 1,
                'started_at' => now()->subHour(),
                'completed_at' => now()->subHour()->addMinutes(2),
            ],
        ];

        foreach ($rows as $row) {
            $run = ReconciliationRun::query()->updateOrCreate(
                ['idempotency_key' => 'seed:'.$row['scope_type'].':'.$row['status']->value],
                array_merge($row, [
                    'public_id' => (string) Str::ulid(),
                    'triggered_by_user_id' => $admin?->id,
                    'correlation_id' => (string) Str::ulid(),
                    'created_at' => now()->subHours(7),
                ]),
            );

            if ($run->status === ReconciliationStatus::AnomaliesDetected && $wallet) {
                ReconciliationFinding::query()->updateOrCreate(
                    [
                        'reconciliation_run_id' => $run->id,
                        'resource_type' => Wallet::class,
                        'resource_id' => $wallet->id,
                        'finding_type' => 'wallet_cache_drift',
                    ],
                    [
                        'public_id' => (string) Str::ulid(),
                        'severity' => 'high',
                        'expected' => ['available_minor' => 100_000],
                        'actual' => ['available_minor' => 99_950],
                        'delta' => ['available_minor' => 50],
                        'resolution' => 'auto_repaired',
                        'created_at' => now()->subHour(),
                    ],
                );
            }
        }
    }
}
