<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Console\Commands;

use Illuminate\Console\Command;

class SettleRunCommand extends Command
{
    protected $signature = 'settle:run';

    protected $description = 'Disabled: payouts require an admin transfer reference and proof';

    public function handle(): int
    {
        $this->error('Automatic payouts are disabled. Use the withdrawal queue and attach transfer proof.');

        return self::FAILURE;
    }
}
