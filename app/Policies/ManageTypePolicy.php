<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ManageType;
use Illuminate\Auth\Access\HandlesAuthorization;

class ManageTypePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_manage::type');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ManageType $manageType): bool
    {
        return $user->can('view_manage::type');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_manage::type');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ManageType $manageType): bool
    {
        return $user->can('update_manage::type');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ManageType $manageType): bool
    {
        return $user->can('delete_manage::type');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_manage::type');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ManageType $manageType): bool
    {
        return $user->can('force_delete_manage::type');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_manage::type');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ManageType $manageType): bool
    {
        return $user->can('restore_manage::type');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_manage::type');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ManageType $manageType): bool
    {
        return $user->can('replicate_manage::type');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_manage::type');
    }
}
