<?php

namespace App\DTOs;

use App\Enums\DataPriority;
use DateTime;
use Illuminate\Support\Str;

class SyncItem
{
    public function __construct(
        public string $id,
        public string $type,
        public array $data,
        public array $vectorClock = [],
        public ?string $workerId = null,
        public ?string $deviceId = null,
        public ?string $logicalId = null,
        public ?string $contentHash = null,
        public ?string $entityId = null,
        public ?string $entityType = null,
        public ?string $userRole = null,
        public ?DateTime $timestamp = null,
        public DataPriority $priority = DataPriority::MEDIUM,
        public bool $isSafetyCritical = false,
        public bool $isRealTime = false,
        public ?array $conflictedFields = null,
        public ?string $mergeStatus = null,
        public ?array $merged_vector_clock = null,
        public ?int $sequenceNumber = null
    ) {
        $this->timestamp = $timestamp ?? now();
    }

    public function isSameLogicalItem(SyncItem $other): bool
    {
        return $this->logicalId === $other->logicalId &&
               $this->type === $other->type &&
               $this->entityId === $other->entityId;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id ?? Str::uuid(),
            'type' => $this->type,
            'data' => $this->data,
            'vector_clock' => $this->vectorClock,
            'worker_id' => $this->workerId,
            'device_id' => $this->deviceId,
            'logical_id' => $this->logicalId,
            'content_hash' => $this->contentHash,
            'entity_id' => $this->entityId,
            'entity_type' => $this->entityType,
            'user_role' => $this->userRole,
            'timestamp' => $this->timestamp->format('c'),
            'priority' => $this->priority->value,
            'is_safety_critical' => $this->isSafetyCritical,
            'is_real_time' => $this->isRealTime,
            'conflicted_fields' => $this->conflictedFields,
            'merge_status' => $this->mergeStatus,
        ];
    }

    public static function fromArray(array $data): self
    {
        $eventType = $data['event_type'] ?? '';
        $entityType = $data['entity_type'] ?? '';

        // Assign higher priority to approval-related events
        $priority = self::determineEventPriority($eventType, $entityType, $data);

        return new self(
            id: $data['id'] ?? Str::uuid(),
            type: $eventType,
            data: $data['event_data'],
            vectorClock: $data['vector_clock'] ?? [],
            workerId: $data['worker_id'] ?? null,
            deviceId: $data['device_id'] ?? null,
            logicalId: $data['logical_id'] ?? null,
            contentHash: $data['content_hash'] ?? null,
            entityId: $data['entity_id'] ?? null,
            entityType: $entityType,
            userRole: $data['user_role'] ?? null,
            timestamp: isset($data['timestamp']) ? new DateTime($data['timestamp']) : null,
            priority: $priority,
            isSafetyCritical: $data['is_safety_critical'] ?? false,
            isRealTime: $data['is_real_time'] ?? false,
            conflictedFields: $data['conflicted_fields'] ?? null,
            mergeStatus: $data['merge_status'] ?? null,
            merged_vector_clock: $data['merged_vector_clock'] ?? null,
            sequenceNumber: $data['sequence_number'] ?? null
        );
    }

    /**
     * Determine priority based on event type and content
     */
    private static function determineEventPriority(string $eventType, string $entityType, array $data): DataPriority
    {
        // Approval-related events get high priority
        $approvalEvents = [
            'timesheet_modified',
            'timesheet_approved',
            'timesheet_rejected',
            'timesheet_disputed',
            'timesheet_escalated',
            'approval_workflow_started',
        ];

        if ($entityType === 'timesheet' && in_array($eventType, $approvalEvents)) {
            return DataPriority::HIGH;
        }

        // Check if explicitly set
        if (isset($data['priority'])) {
            return DataPriority::from($data['priority']);
        }

        return DataPriority::MEDIUM;
    }
}
