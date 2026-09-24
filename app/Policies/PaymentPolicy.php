<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\PermissionName;

class PaymentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PaymentsView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payment $payment): bool
    {
        return $payment->order()->whereBelongsTo($user)->exists()
            || $user->can(PermissionName::PaymentsView->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function submit(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    public function review(User $user, Payment $payment): bool
    {
        return $user->can(PermissionName::PaymentsManage->value);
    }

    public function viewProof(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
