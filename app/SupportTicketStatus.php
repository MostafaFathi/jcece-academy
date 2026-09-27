<?php

namespace App;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingForStudent = 'waiting_for_student';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
