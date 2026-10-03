<?php

namespace App\Policies;

use App\Models\PolicyPage;
use App\Models\User;
use App\PermissionName;
use App\RoleName;

class PolicyPagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PolicyPagesView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PolicyPage $policyPage): bool
    {
        return $user->can(PermissionName::PolicyPagesView->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PolicyPage $policyPage): bool
    {
        return $user->can(PermissionName::PolicyPagesUpdate->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PolicyPage $policyPage): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PolicyPage $policyPage): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PolicyPage $policyPage): bool
    {
        return false;
    }

    public function publish(User $user, PolicyPage $policyPage): bool
    {
        return $user->hasRole(RoleName::Admin->value) && $user->can(PermissionName::PolicyPagesPublish->value);
    }
}
