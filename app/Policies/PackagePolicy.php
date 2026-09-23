<?php

namespace App\Policies;

use App\Models\Package;
use App\Models\User;
use App\PermissionName;

class PackagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PackagesView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Package $package): bool
    {
        return $user->can(PermissionName::PackagesView->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::PackagesCreate->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Package $package): bool
    {
        return $user->can(PermissionName::PackagesUpdate->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Package $package): bool
    {
        return $user->can(PermissionName::PackagesDelete->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Package $package): bool
    {
        return $user->can(PermissionName::PackagesUpdate->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Package $package): bool
    {
        return $user->can(PermissionName::PackagesDelete->value);
    }

    public function publish(User $user, Package $package): bool
    {
        return $user->can(PermissionName::PackagesPublish->value);
    }
}
