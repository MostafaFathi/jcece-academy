<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;

class PlatformNotificationEvent
{
    public function __construct(public string $type, public Model $subject, public string $eventKey, public array $context = []) {}
}
