<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;
use App\PermissionName;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SupportTicketsView->value);
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $ticket->user_id === $user->id || $user->can(PermissionName::SupportTicketsView->value);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return $user->can(PermissionName::SupportTicketsManage->value);
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $ticket->user_id === $user->id || $user->can(PermissionName::SupportTicketsReply->value);
    }

    public function replyAsStaff(User $user, SupportTicket $ticket): bool
    {
        return $user->can(PermissionName::SupportTicketsReply->value);
    }

    public function manage(User $user, SupportTicket $ticket): bool
    {
        return $user->can(PermissionName::SupportTicketsManage->value);
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return false;
    }

    public function restore(User $user, SupportTicket $ticket): bool
    {
        return false;
    }

    public function forceDelete(User $user, SupportTicket $ticket): bool
    {
        return false;
    }
}
