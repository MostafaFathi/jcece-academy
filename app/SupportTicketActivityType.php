<?php

namespace App;

enum SupportTicketActivityType: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case Reassigned = 'reassigned';
    case CategoryChanged = 'category_changed';
    case PriorityChanged = 'priority_changed';
    case StatusChanged = 'status_changed';
    case Resolved = 'resolved';
    case Reopened = 'reopened';
    case Closed = 'closed';
}
