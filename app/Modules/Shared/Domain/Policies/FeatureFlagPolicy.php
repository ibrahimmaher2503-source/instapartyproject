<?php

namespace App\Modules\Shared\Domain\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Models\FeatureFlag;
use Illuminate\Auth\Access\HandlesAuthorization;

class FeatureFlagPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_feature::flag');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FeatureFlag $featureFlag): bool
    {
        return $user->can('view_feature::flag');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_feature::flag');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FeatureFlag $featureFlag): bool
    {
        return $user->can('update_feature::flag');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FeatureFlag $featureFlag): bool
    {
        return $user->can('delete_feature::flag');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_feature::flag');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, FeatureFlag $featureFlag): bool
    {
        return $user->can('force_delete_feature::flag');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_feature::flag');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, FeatureFlag $featureFlag): bool
    {
        return $user->can('restore_feature::flag');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_feature::flag');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, FeatureFlag $featureFlag): bool
    {
        return $user->can('replicate_feature::flag');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_feature::flag');
    }
}
