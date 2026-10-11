<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\NotificationTypePolicy;
use App\Models\User;

class NotificationTypeRegistry
{
    public function all(): array
    {
        return config('platform_notifications.types');
    }

    public function get(string $type): array
    {
        return $this->all()[$type] ?? throw new \InvalidArgumentException('Unknown notification type.');
    }

    public function channels(User $user, string $type): array
    {
        $definition = $this->get($type);
        $policy = NotificationTypePolicy::query()->where('type', $type)->first();
        $preference = NotificationPreference::query()->where('user_id', $user->id)->where('type', $type)->first();

        return ['in_app' => $definition['mandatory'] || ($preference?->in_app ?? $policy?->in_app ?? true), 'email' => $definition['mandatory'] && $definition['email'] || ($preference?->email ?? $policy?->email ?? $definition['email']), 'digest' => $preference?->digest ?? false];
    }
}
