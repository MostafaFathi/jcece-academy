<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditTrail
{
    /** @param array<string, bool|int|string|null> $metadata */
    public function record(string $eventType, Model $subject, ?User $actor = null, array $metadata = []): AuditEvent
    {
        $allowedKeys = ['from_status', 'to_status', 'role', 'permission', 'grants_created', 'grants_existing', 'changed_fields', 'amount', 'currency', 'refund_id', 'order_id', 'report_type', 'report_format', 'report_from', 'report_to', 'before_permissions', 'after_permissions', 'added_permissions', 'removed_permissions'];
        $safeMetadata = array_intersect_key($metadata, array_flip($allowedKeys));

        return AuditEvent::query()->create([
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'metadata' => $safeMetadata,
        ]);
    }
}
