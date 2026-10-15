<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

final class DeleteCustomerAddressAction
{
    public function execute(CustomerAddress $address): void
    {
        DB::transaction(fn () => $address->delete());
    }
}
