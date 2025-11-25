<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Instrument;
use Illuminate\Auth\Access\HandlesAuthorization;

class InstrumentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_instrument');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Instrument $instrument): bool
    {
        return $user->can('view_instrument');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_instrument');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Instrument $instrument): bool
    {
        return $user->can('update_instrument');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Instrument $instrument): bool
    {
        return $user->can('delete_instrument');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_instrument');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Instrument $instrument): bool
    {
        return $user->can('force_delete_instrument');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_instrument');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Instrument $instrument): bool
    {
        return $user->can('restore_instrument');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_instrument');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Instrument $instrument): bool
    {
        return $user->can('replicate_instrument');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_instrument');
    }
}
