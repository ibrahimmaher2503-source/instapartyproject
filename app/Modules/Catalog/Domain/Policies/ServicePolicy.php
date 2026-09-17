<?php

namespace App\Modules\Catalog\Domain\Policies;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServicePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canAnyType($user, 'view_any')
            || $this->canAnyType($user, 'create')
            || $this->canAnyType($user, 'update');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Service $service): bool
    {
        return $this->canForService($user, $service, 'view')
            || $this->canForService($user, $service, 'create')
            || $this->canForService($user, $service, 'update')
            || $this->canForService($user, $service, 'publish');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canAnyType($user, 'create') || $this->canAnyType($user, 'service.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Service $service): bool
    {
        return $this->canForService($user, $service, 'update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Service $service): bool
    {
        return $this->canForService($user, $service, 'delete');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $this->canAnyType($user, 'delete_any') || $this->canAnyType($user, 'service.delete');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Service $service): bool
    {
        return $this->canForService($user, $service, 'force_delete');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $this->canAnyType($user, 'force_delete_any') || $this->canAnyType($user, 'service.delete');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Service $service): bool
    {
        return $this->canForService($user, $service, 'restore');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $this->canAnyType($user, 'restore_any') || $this->canAnyType($user, 'service.update');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Service $service): bool
    {
        return $this->canForService($user, $service, 'replicate');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $this->canAnyType($user, 'reorder') || $this->canAnyType($user, 'service.update');
    }

    public function archive(User $user, Service $service): bool
    {
        $type = $service->product_type->value;

        return $user->can("publish_{$type}_service")
            || $user->can('archive_service')
            || $user->can("service.delete.{$type}.own");
    }

    public function approve(User $user, Service $service): bool
    {
        $type = $service->product_type->value;

        return $user->can("publish_{$type}_service");
    }

    public function reject(User $user, Service $service): bool
    {
        $type = $service->product_type->value;

        return $user->can("publish_{$type}_service");
    }

    public function requestChanges(User $user, Service $service): bool
    {
        $type = $service->product_type->value;

        return $user->can("publish_{$type}_service");
    }

    private function canForService(User $user, Service $service, string $action): bool
    {
        $type = $service->product_type->value;

        return $user->can("{$action}_{$type}::service")
            || $user->can("service.{$action}.{$type}.own");
    }

    private function canAnyType(User $user, string $permissionPrefix): bool
    {
        foreach (ProductType::cases() as $type) {
            if ($user->can("{$permissionPrefix}_{$type->value}::service")
                || $user->can("{$permissionPrefix}.{$type->value}.own")) {
                return true;
            }
        }

        return false;
    }
}
