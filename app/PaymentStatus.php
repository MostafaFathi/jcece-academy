<?php

namespace App;

enum PaymentStatus: string
{
    case PendingReview = 'pending_review';
    case Paid = 'paid';
    case Rejected = 'rejected';
}
