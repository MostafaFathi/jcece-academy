<?php

namespace App\Events;

use App\Models\SupportTicket;
use App\SupportTicketStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTicketStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public SupportTicket $ticket, public SupportTicketStatus $from, public SupportTicketStatus $to) {}
}
