<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Support\Str;

class ApprovalService
{
    /**
     * Authority hierarchy levels
     */
    private const AUTHORITY_HIERARCHY = [
        'worker' => 1,
        'supervisor' => 2,
        'manager' => 3,
    ];

    /**
     * Workflow states
     */
    private const WORKFLOW_STATES = [
        'draft' => 'Worker logging hours',
        'submitted' => 'Ready for supervisor review',
        'supervisor_review' => 'Supervisor can modify/approve',
        'manager_review' => 'Escalated or manager override needed',
        'approved' => 'Final approval given',
        'rejected' => 'Timesheet rejected',
        'disputed' => 'Worker disputes modification',
    ];

    /**
     * Start approval workflow for a timesheet
     */
    public function startWorkflow(string $entityId, string $workerId): string
    {
        $workflowId = Str::uuid();

        Event::create([
            'entity_type' => 'timesheet',
            'entity_id' => $entityId,
            'event_type' => EventType::APPROVAL_WORKFLOW_STARTED->value,
            'worker_id' => $workerId,
            'event_data' => [
                'workflow_started_at' => now(),
            ],
            'workflow_instance_id' => $workflowId,
            'authority_level' => 'worker',
        ]);

        return $workflowId;
    }

    /**
     * Submit timesheet for approval
     */
    public function submitForApproval(string $entityId, string $workerId): bool
    {
        // Check if timesheet is in draft state
        $currentState = Event::replayEventSequence('timesheet', $entityId);
        if (($currentState['status'] ?? '') !== 'draft') {
            return false;
        }

        Event::create([
            'entity_type' => 'timesheet',
            'entity_id' => $entityId,
            'event_type' => EventType::APPROVAL_WORKFLOW_STARTED->value,
            'worker_id' => $workerId,
            'event_data' => [
                'submitted_at' => now(),
            ],
            'authority_level' => 'worker',
        ]);

        return true;
    }

    /**
     * Modify timesheet hours (supervisor/manager action)
     */
    public function modifyHours(
        string $entityId,
        string $authorityLevel,
        float $newHours,
        string $justification,
        string $workerId
    ): bool {
        $currentState = Event::replayEventSequence('timesheet', $entityId);
        $currentHours = $currentState['hours_logged'] ?? 0;

        // Validate authority
        if (! $this->canModifyHours($authorityLevel, $currentState)) {
            return false;
        }

        // Check modification limits
        if (! $this->validateModificationLimits($authorityLevel, $currentHours, $newHours)) {
            return false;
        }

        Event::create([
            'entity_type' => 'timesheet',
            'entity_id' => $entityId,
            'event_type' => EventType::TIMESHEET_MODIFIED->value,
            'worker_id' => $workerId,
            'event_data' => [
                'previous_hours' => $currentHours,
                'new_hours' => $newHours,
                'authority_level' => $authorityLevel,
                'justification' => $justification,
                'modified_at' => now(),
            ],
            'authority_level' => $authorityLevel,
            'modification_justification' => $justification,
            'previous_values' => ['hours_logged' => $currentHours],
        ]);

        return true;
    }

    /**
     * Approve timesheet at current authority level
     */
    public function approveTimesheet(
        string $entityId,
        string $authorityLevel,
        string $workerId,
        string $comments = ''
    ): bool {
        $currentState = Event::replayEventSequence('timesheet', $entityId);

        // Validate approval authority
        if (! $this->canApproveAtLevel($authorityLevel, $currentState)) {
            return false;
        }

        Event::create([
            'entity_type' => 'timesheet',
            'entity_id' => $entityId,
            'event_type' => EventType::TIMESHEET_APPROVED->value,
            'worker_id' => $workerId,
            'event_data' => [
                'authority_level' => $authorityLevel,
                'approved_at' => now(),
                'comments' => $comments,
            ],
            'authority_level' => $authorityLevel,
            'approval_status' => 'approved',
        ]);

        return true;
    }

    /**
     * Reject timesheet with reason
     */
    public function rejectTimesheet(
        string $entityId,
        string $authorityLevel,
        string $workerId,
        string $reason
    ): bool {
        $currentState = Event::replayEventSequence('timesheet', $entityId);

        if (! $this->canApproveAtLevel($authorityLevel, $currentState)) {
            return false;
        }

        Event::create([
            'entity_type' => 'timesheet',
            'entity_id' => $entityId,
            'event_type' => EventType::TIMESHEET_REJECTED->value,
            'worker_id' => $workerId,
            'event_data' => [
                'authority_level' => $authorityLevel,
                'rejected_at' => now(),
                'reason' => $reason,
            ],
            'authority_level' => $authorityLevel,
            'approval_status' => 'rejected',
        ]);

        return true;
    }

    /**
     * Worker disputes a modification
     */
    public function disputeModification(
        string $entityId,
        string $workerId,
        string $reason
    ): bool {
        $currentState = Event::replayEventSequence('timesheet', $entityId);

        // Only workers can dispute modifications
        if (($currentState['authority_level'] ?? 'worker') !== 'worker') {
            return false;
        }

        Event::create([
            'entity_type' => 'timesheet',
            'entity_id' => $entityId,
            'event_type' => EventType::TIMESHEET_DISPUTED->value,
            'worker_id' => $workerId,
            'event_data' => [
                'disputed_at' => now(),
                'reason' => $reason,
            ],
            'authority_level' => 'worker',
        ]);

        return true;
    }

    /**
     * Escalate to higher authority
     */
    public function escalateToHigherAuthority(
        string $entityId,
        string $currentAuthorityLevel,
        string $workerId,
        string $reason
    ): bool {
        $nextLevel = $this->getNextAuthorityLevel($currentAuthorityLevel);
        if (! $nextLevel) {
            return false;
        }

        Event::create([
            'entity_type' => 'timesheet',
            'entity_id' => $entityId,
            'event_type' => EventType::TIMESHEET_ESCALATED->value,
            'worker_id' => $workerId,
            'event_data' => [
                'escalated_from' => $currentAuthorityLevel,
                'escalated_to' => $nextLevel,
                'escalated_at' => now(),
                'reason' => $reason,
            ],
            'authority_level' => $currentAuthorityLevel,
        ]);

        return true;
    }

    /**
     * Get current workflow state for a timesheet
     */
    public function getWorkflowState(string $entityId): array
    {
        $state = Event::replayEventSequence('timesheet', $entityId);

        return [
            'status' => $state['status'] ?? 'draft',
            'status_description' => self::WORKFLOW_STATES[$state['status'] ?? 'draft'] ?? 'Unknown',
            'hours_logged' => $state['hours_logged'] ?? 0,
            'modifications' => $state['modifications'] ?? [],
            'approvals' => $state['approvals'] ?? [],
            'disputes' => $state['disputes'] ?? [],
            'authority_level' => $state['authority_level'] ?? 'worker',
            'workflow_instance_id' => $state['workflow_instance_id'] ?? null,
        ];
    }

    /**
     * Check if authority level can modify hours
     */
    private function canModifyHours(string $authorityLevel, array $currentState): bool
    {
        $currentStatus = $currentState['status'] ?? 'draft';

        // Retroactive changes to approved timesheets require manager approval
        if ($currentStatus === 'approved' && $authorityLevel !== 'manager') {
            return false;
        }

        $currentAuthorityLevel = $currentState['authority_level'] ?? 'worker';
        $currentAuthValue = self::AUTHORITY_HIERARCHY[$currentAuthorityLevel] ?? 0;
        $requestedAuthValue = self::AUTHORITY_HIERARCHY[$authorityLevel] ?? 0;

        return $requestedAuthValue > $currentAuthValue;
    }

    /**
     * Validate modification limits based on authority
     */
    public function validateModificationLimits(
        string $authorityLevel,
        float $currentHours,
        float $newHours
    ): bool {
        $change = abs($newHours - $currentHours);

        return match ($authorityLevel) {
            'supervisor' => $change <= 2.0,
            'manager' => true, // No limits for managers
            default => false,
        };
    }

    /**
     * Check if authority level can approve at current state
     */
    private function canApproveAtLevel(string $authorityLevel, array $currentState): bool
    {
        $currentStatus = $currentState['status'] ?? 'draft';

        return match ($currentStatus) {
            'submitted', 'supervisor_review' => in_array($authorityLevel, ['supervisor', 'manager']),
            'manager_review' => $authorityLevel === 'manager',
            default => false,
        };
    }

    /**
     * Get next authority level in hierarchy
     */
    private function getNextAuthorityLevel(string $currentLevel): ?string
    {
        $currentValue = self::AUTHORITY_HIERARCHY[$currentLevel] ?? 0;

        foreach (self::AUTHORITY_HIERARCHY as $level => $value) {
            if ($value > $currentValue) {
                return $level;
            }
        }

        return null;
    }
}
