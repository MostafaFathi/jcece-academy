<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;
use App\PermissionName;

class CertificatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::CertificatesView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Certificate $certificate): bool
    {
        return $certificate->user_id === $user->id
            || $user->can(PermissionName::CertificatesView->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::CertificatesIssue->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Certificate $certificate): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Certificate $certificate): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Certificate $certificate): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Certificate $certificate): bool
    {
        return false;
    }

    public function issue(User $user): bool
    {
        return $user->can(PermissionName::CertificatesIssue->value);
    }

    public function revoke(User $user, Certificate $certificate): bool
    {
        return $user->can(PermissionName::CertificatesRevoke->value);
    }

    public function download(User $user, Certificate $certificate): bool
    {
        return $this->view($user, $certificate);
    }
}
