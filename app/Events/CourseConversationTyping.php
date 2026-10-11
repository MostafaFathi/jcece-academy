<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class CourseConversationTyping implements ShouldBroadcastNow
{
    public function __construct(public int $conversationId, public int $userId, public bool $typing) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('course-conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'conversation.typing';
    }

    public function broadcastWith(): array
    {
        return ['userId' => $this->userId, 'typing' => $this->typing];
    }
}
