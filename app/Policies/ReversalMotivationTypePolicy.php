<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ReversalMotivationType;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReversalMotivationTypePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_reversal::motivation::type');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReversalMotivationType $reversalMotivationType): bool
    {
        return $user->can('view_reversal::motivation::type');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_reversal::motivation::type');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReversalMotivationType $reversalMotivationType): bool
    {
        return $user->can('update_reversal::motivation::type');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReversalMotivationType $reversalMotivationType): bool
    {
        return $user->can('delete_reversal::motivation::type');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_reversal::motivation::type');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ReversalMotivationType $reversalMotivationType): bool
    {
        return $user->can('force_delete_reversal::motivation::type');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_reversal::motivation::type');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ReversalMotivationType $reversalMotivationType): bool
    {
        return $user->can('restore_reversal::motivation::type');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_reversal::motivation::type');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ReversalMotivationType $reversalMotivationType): bool
    {
        return $user->can('replicate_reversal::motivation::type');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_reversal::motivation::type');
    }
}
