<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'id',
        'event_id',
        'entity_type',
        'entity_id',
        'worker_id',
        'event_type',
        'event_data',
        'device_id',
        'sequence_number',
        'vector_clock',
        'server_created_at',
        'authority_level',
        'modification_justification',
        'previous_values',
        'approval_status',
        'workflow_instance_id',
        'transfer_id',
        'sender_signature',
        'receiver_signature',
        'qr_code_hash',
        'chain_position',
        'transfer_status',
        'chain_validation_status',
    ];

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'vector_clock' => 'array',
            'previous_values' => 'array',
            'server_created_at' => 'datetime',
            'chain_position' => 'integer',
        ];
    }

    // todo: logics for sequence generation for events and logics for conflict resolution for data

    /**
     * Scope for events related to specific entity
     */
    public function scopeForEntity($query, string $entityType, string $entityId)
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    /**
     * Replay events to reconstruct entity state
     * Implements requires current state received by replaying events
     */
    public static function replayEventSequence(string $entityType, string $entityId): array
    {
        $events = static::forEntity($entityType, $entityId)
            ->orderBy('sequence_number')
            ->orderBy('server_created_at')
            ->get();

        $state = [];
        foreach ($events as $event) {
            $state = $event->stateCalculations($state);
        }

        return $state;
    }

    /**
     * Replay events up to a specific point in time
     */
    public static function replayEventSequenceAtTime(string $entityType, string $entityId, \DateTime $atTime): array
    {
        $events = static::forEntity($entityType, $entityId)
            ->where('server_created_at', '<=', $atTime)
            ->orderBy('sequence_number')
            ->orderBy('server_created_at')
            ->get();

        $state = [];
        foreach ($events as $event) {
            $state = $event->stateCalculations($state);
        }

        return $state;
    }

    /**
     * Replay events up to a specific sequence number
     */
    public static function replayEventSequenceAtSequence(string $entityType, string $entityId, int $sequenceNumber): array
    {
        $events = static::forEntity($entityType, $entityId)
            ->where('sequence_number', '<=', $sequenceNumber)
            ->orderBy('sequence_number')
            ->orderBy('server_created_at')
            ->get();

        $state = [];
        foreach ($events as $event) {
            $state = $event->stateCalculations($state);
        }

        return $state;
    }

    /**
     * Check if this event requires approval
     */
    public function requiresApproval(): bool
    {
        return in_array($this->event_type, [
            EventType::HOURS_LOGGED->value,
            EventType::TIMESHEET_MODIFIED->value,
            EventType::TIMESHEET_APPROVED->value,
            EventType::TIMESHEET_REJECTED->value,
            EventType::TIMESHEET_DISPUTED->value,
            EventType::TIMESHEET_ESCALATED->value,
        ]);
    }

    /**
     * Get the authority level for this event
     */
    public function getAuthorityLevel(): string
    {
        return $this->authority_level ?? 'worker';
    }

    /**
     * Set the authority level for this event
     */
    public function setAuthorityLevel(string $level): self
    {
        $this->authority_level = $level;

        return $this;
    }

    /**
     * Validate authority level for this event type
     */
    public function validateAuthority(string $authorityLevel): bool
    {
        $authorityHierarchy = [
            'worker' => 1,
            'supervisor' => 2,
            'manager' => 3,
        ];

        $currentLevel = $authorityHierarchy[$this->getAuthorityLevel()] ?? 0;
        $requestedLevel = $authorityHierarchy[$authorityLevel] ?? 0;

        return $requestedLevel >= $currentLevel;
    }

    /**
     * Check if this event can be modified by the given authority level
     */
    public function canBeModifiedBy(string $authorityLevel): bool
    {
        if ($this->event_type === EventType::HOURS_LOGGED->value) {
            return in_array($authorityLevel, ['supervisor', 'manager']);
        }

        if ($this->event_type === EventType::TIMESHEET_MODIFIED->value) {
            return $authorityLevel === 'manager';
        }

        return false;
    }

    /**
     * Get modification limits for authority levels
     */
    public function getModificationLimits(string $authorityLevel): array
    {
        return match ($authorityLevel) {
            'supervisor' => ['max_change' => 2.0, 'requires_justification' => true],
            'manager' => ['max_change' => null, 'requires_justification' => true],
            default => ['max_change' => 0, 'requires_justification' => false],
        };
    }

    /**
     * Handles different event types for materialized views
     */
    protected function stateCalculations(array $currentState): array
    {
        $newState = $currentState;
        $eventType = EventType::tryFrom($this->event_type);

        if (in_array($eventType, [
            EventType::WORKER_CREATED,
            EventType::PROJECT_CREATED,
            EventType::STOCK_CREATED,
            EventType::INVENTORY_CREATED,
        ])) {
            // Initial set data for the events
            $newState = array_merge($newState, $this->event_data);
        } elseif ($eventType === EventType::HOURS_LOGGED) {
            // Initial worker logging
            $newState['hours_logged'] = $this->event_data['hours'] ?? 0;
            $newState['authority_level'] = 'worker';
            $newState['status'] = 'draft';
            $newState['modifications'] = [];
            $newState['approvals'] = [];
            $newState['disputes'] = [];
        } elseif ($eventType === EventType::TIMESHEET_MODIFIED) {
            // Track modifications with authority validation
            $modAuthority = $this->event_data['authority_level'] ?? 'worker';
            $currentAuthority = $newState['authority_level'] ?? 'worker';
            $authorityHierarchy = ['worker' => 1, 'supervisor' => 2, 'manager' => 3];
            $modLevel = $authorityHierarchy[$modAuthority] ?? 0;
            $currentLevel = $authorityHierarchy[$currentAuthority] ?? 0;

            if ($modLevel > $currentLevel) {
                $newState['modifications'][] = [
                    'previous_hours' => $newState['hours_logged'] ?? 0,
                    'new_hours' => $this->event_data['new_hours'] ?? 0,
                    'authority' => $modAuthority,
                    'justification' => $this->event_data['justification'] ?? '',
                    'timestamp' => $this->server_created_at,
                ];
                $newState['hours_logged'] = $this->event_data['new_hours'] ?? 0;
                $newState['authority_level'] = $modAuthority;
            }
        } elseif ($eventType === EventType::TIMESHEET_APPROVED) {
            // Approval logic with hierarchy
            $newState['approvals'][] = [
                'authority_level' => $this->event_data['authority_level'] ?? 'worker',
                'approved_by' => $this->worker_id,
                'timestamp' => $this->server_created_at,
            ];
            $newState['status'] = $this->calculateApprovalStatus($newState);
        } elseif ($eventType === EventType::TIMESHEET_REJECTED) {
            $newState['status'] = 'rejected';
            $newState['rejection_reason'] = $this->event_data['reason'] ?? '';
        } elseif ($eventType === EventType::TIMESHEET_DISPUTED) {
            $newState['disputes'][] = [
                'disputed_by' => $this->worker_id,
                'reason' => $this->event_data['reason'] ?? '',
                'timestamp' => $this->server_created_at,
                'authority_level' => $this->event_data['authority_level'] ?? 'worker',
            ];
            $newState['status'] = 'disputed';
        } elseif ($eventType === EventType::TIMESHEET_ESCALATED) {
            $newState['status'] = 'manager_review';
        } elseif ($eventType === EventType::APPROVAL_WORKFLOW_STARTED) {
            $newState['workflow_instance_id'] = $this->workflow_instance_id;
            $newState['status'] = 'submitted';
        } elseif ($eventType === EventType::STOCK_USED) {
            // Subtract from quantity
            $newState['quantity'] = ($newState['quantity'] ?? 0) - ($this->event_data['quantity_used'] ?? 0);
        } elseif ($eventType === EventType::STOCK_ADJUSTED) {
            // Add or subtract stocks
            $newState['quantity'] = ($newState['quantity'] ?? 0) + ($this->event_data['adjustment'] ?? 0);
        } elseif ($eventType === EventType::INVENTORY_TRANSFER_INITIATED) {
            // Track transfer initiation
            $newState['transfers'] = $newState['transfers'] ?? [];
            $newState['transfers'][] = [
                'transfer_id' => $this->transfer_id,
                'type' => 'initiated',
                'quantity' => $this->event_data['quantity'] ?? 0,
                'sender_id' => $this->worker_id,
                'status' => 'pending',
                'qr_code_hash' => $this->qr_code_hash,
                'chain_position' => $this->chain_position ?? 0,
                'timestamp' => $this->server_created_at,
            ];
            $newState['transfer_status'] = 'transfer_pending';
        } elseif ($eventType === EventType::INVENTORY_TRANSFER_RECEIVED) {
            // Track transfer receipt
            $transferId = $this->transfer_id;
            if (isset($newState['transfers'])) {
                foreach ($newState['transfers'] as &$transfer) {
                    if ($transfer['transfer_id'] === $transferId) {
                        $transfer['type'] = 'received';
                        $transfer['receiver_id'] = $this->worker_id;
                        $transfer['status'] = 'completed';
                        $transfer['receiver_signature'] = $this->receiver_signature;
                        break;
                    }
                }
            }
            // Update quantity when transfer is received
            $newState['quantity'] = ($newState['quantity'] ?? 0) + ($this->event_data['quantity'] ?? 0);
            $newState['transfer_status'] = 'active';
        } elseif ($eventType === EventType::INVENTORY_TRANSFER_CANCELLED) {
            // Track transfer cancellation
            $transferId = $this->transfer_id;
            if (isset($newState['transfers'])) {
                foreach ($newState['transfers'] as &$transfer) {
                    if ($transfer['transfer_id'] === $transferId) {
                        $transfer['status'] = 'cancelled';
                        break;
                    }
                }
            }
            $newState['transfer_status'] = 'active';
        } elseif ($eventType === EventType::INVENTORY_TRANSFER_DISPUTED) {
            // Track transfer dispute
            $transferId = $this->transfer_id;
            if (isset($newState['transfers'])) {
                foreach ($newState['transfers'] as &$transfer) {
                    if ($transfer['transfer_id'] === $transferId) {
                        $transfer['status'] = 'disputed';
                        $transfer['dispute_reason'] = $this->event_data['reason'] ?? '';
                        break;
                    }
                }
            }
            $newState['transfer_status'] = 'disputed';
        } elseif ($eventType === EventType::INVENTORY_TRANSFER_CHAIN_VALIDATED) {
            // Track chain validation
            $newState['chain_validation_status'] = $this->event_data['validation_status'] ?? 'valid';
            $newState['chain_validation_timestamp'] = $this->server_created_at;
        } else {
            $newState = array_merge($newState, $this->event_data);
        }

        return $newState;
    }

    /**
     * Calculate approval status based on current approvals and workflow state
     */
    protected function calculateApprovalStatus(array $currentState): string
    {
        $approvals = $currentState['approvals'] ?? [];
        $currentStatus = $currentState['status'] ?? 'draft';
        $authorityLevels = array_column($approvals, 'authority_level');

        // If already in a terminal state, don't change
        if (in_array($currentStatus, ['approved', 'rejected'])) {
            return $currentStatus;
        }

        // Manager approval always results in final approval
        if (in_array('manager', $authorityLevels)) {
            return 'approved';
        }

        // Supervisor approval - check if escalation is needed
        if (in_array('supervisor', $authorityLevels)) {
            // If there are disputes, escalate to manager
            if (! empty($currentState['disputes'] ?? [])) {
                return 'manager_review';
            }

            // Otherwise, supervisor approval is sufficient
            return 'approved';
        }

        // If submitted and no approvals yet, it's in supervisor review
        if ($currentStatus === 'submitted') {
            return 'supervisor_review';
        }

        // If escalated, it's in manager review
        if ($currentStatus === 'manager_review') {
            return 'manager_review';
        }

        return 'pending';
    }
}
