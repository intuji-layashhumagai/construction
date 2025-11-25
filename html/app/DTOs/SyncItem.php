<?php

namespace App\DTOs;

use App\Enums\DataPriority;
use DateTime;

class SyncItem
{
    public function __construct(
        public string $id,
        public string $type,
        public array $data,
        public array $vectorClock = [],
        public ?string $deviceId = null,
        public ?string $logicalId = null,
        public ?string $contentHash = null,
        public ?string $entityId = null,
        public ?string $userRole = null,
        public ?DateTime $timestamp = null,
        public DataPriority $priority = DataPriority::MEDIUM,
        public bool $isSafetyCritical = false,
        public bool $isRealTime = false,
        public ?array $conflictedFields = null,
        public ?string $mergeStatus = null
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
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data,
            'vector_clock' => $this->vectorClock,
            'device_id' => $this->deviceId,
            'logical_id' => $this->logicalId,
            'content_hash' => $this->contentHash,
            'entity_id' => $this->entityId,
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
        return new self(
            id: $data['id'],
            type: $data['type'],
            data: $data['data'],
            vectorClock: $data['vector_clock'] ?? [],
            deviceId: $data['device_id'] ?? null,
            logicalId: $data['logical_id'] ?? null,
            contentHash: $data['content_hash'] ?? null,
            entityId: $data['entity_id'] ?? null,
            userRole: $data['user_role'] ?? null,
            timestamp: isset($data['timestamp']) ? new DateTime($data['timestamp']) : null,
            priority: DataPriority::from($data['priority'] ?? DataPriority::MEDIUM->value),
            isSafetyCritical: $data['is_safety_critical'] ?? false,
            isRealTime: $data['is_real_time'] ?? false,
            conflictedFields: $data['conflicted_fields'] ?? null,
            mergeStatus: $data['merge_status'] ?? null
        );
    }
}
