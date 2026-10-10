<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class CourseConversationChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $conversationId, public int $version, public string $kind) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('course-conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'conversation.changed';
    }

    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversationId, 'version' => $this->version, 'kind' => $this->kind];
    }
}
