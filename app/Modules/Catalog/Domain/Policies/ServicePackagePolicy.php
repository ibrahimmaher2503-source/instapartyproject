<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Policies;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ServicePackage;
use App\Modules\Identity\Domain\Models\User;

class ServicePackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAnyType($user, ['view_any', 'create', 'update']);
    }

    public function view(User $user, ServicePackage $package): bool
    {
        return $this->canForPackage($user, $package, ['view', 'update']);
    }

    public function create(User $user): bool
    {
        return $this->canAnyType($user, ['create']);
    }

    public function update(User $user, ServicePackage $package): bool
    {
        return $this->canForPackage($user, $package, ['update']);
    }

    public function delete(User $user, ServicePackage $package): bool
    {
        return $this->canForPackage($user, $package, ['delete']);
    }

    private function canForPackage(User $user, ServicePackage $package, array $actions): bool
    {
        $type = $package->service?->product_type;
        if (! $type instanceof ProductType) {
            return false;
        }

        foreach ($actions as $action) {
            if ($user->can("{$action}_{$type->value}::service", 'web')) {
                return true;
            }
        }

        return false;
    }

    private function canAnyType(User $user, array $actions): bool
    {
        foreach (ProductType::cases() as $type) {
            foreach ($actions as $action) {
                if ($user->can("{$action}_{$type->value}::service", 'web')) {
                    return true;
                }
            }
        }

        return false;
    }
}
