<?php

declare(strict_types=1);

namespace App\Modules\Communication\Console;

use App\Modules\Communication\Application\Actions\CampaignAlreadyDispatchedException;
use App\Modules\Communication\Application\Actions\DispatchCampaignAction;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchDueCampaignsCommand extends Command
{
    protected $signature = 'communication:dispatch-due-campaigns';

    protected $description = 'Dispatch scheduled campaigns whose due time has passed.';

    public function handle(DispatchCampaignAction $action): int
    {
        $campaigns = Campaign::query()
            ->where('status', CampaignStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->oldest('scheduled_at')
            ->limit(100)
            ->get();

        $dispatched = 0;
        $failed = 0;

        foreach ($campaigns as $campaign) {
            try {
                $action->execute($campaign);
                $dispatched++;
            } catch (CampaignAlreadyDispatchedException) {
                continue;
            } catch (Throwable $exception) {
                $campaign->refresh();

                if ($campaign->status === CampaignStatus::Scheduled) {
                    $campaign->update(['status' => CampaignStatus::Failed->value]);
                }

                Log::error('Scheduled campaign dispatch failed.', [
                    'campaign_public_id' => $campaign->public_id,
                    'error' => $exception->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->info("Dispatched {$dispatched} due campaign(s); failed {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
