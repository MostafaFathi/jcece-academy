<?php

namespace App;

enum CourseAccessState: string
{
    case Active = 'active';
    case Locked = 'locked';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Scheduled = 'scheduled';
    case Revoked = 'revoked';
    case Unavailable = 'unavailable';
}
