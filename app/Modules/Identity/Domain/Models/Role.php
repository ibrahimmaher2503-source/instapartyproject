<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function getRouteKeyName(): string
    {
        return 'name';
    }
}
