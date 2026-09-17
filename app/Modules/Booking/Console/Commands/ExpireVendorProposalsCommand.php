<?php

declare(strict_types=1);

namespace App\Modules\Booking\Console\Commands;

use App\Modules\Booking\Application\Actions\ExpireVendorProposalConsentsAction;
use Illuminate\Console\Command;

class ExpireVendorProposalsCommand extends Command
{
    protected $signature = 'booking:expire-vendor-proposals';

    protected $description = 'Expire vendor proposal consents that have passed their consent_expires_at deadline';

    public function handle(ExpireVendorProposalConsentsAction $action): int
    {
        $expired = $action->execute();
        $this->info("Expired {$expired} vendor proposal consent(s).");

        return Command::SUCCESS;
    }
}
