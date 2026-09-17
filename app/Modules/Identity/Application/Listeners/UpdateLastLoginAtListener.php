<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Listeners;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Auth\Events\Login;

class UpdateLastLoginAtListener
{
    public function handle(Login $event): void
    {
        if (! ($event->user instanceof User)) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->save();
    }
}
