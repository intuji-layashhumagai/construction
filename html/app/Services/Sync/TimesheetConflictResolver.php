<?php

namespace App\Services\Sync;

use App\Constants\AuthorityLevel;
use App\DTOs\Conflict;
use App\DTOs\SyncItem;
use Illuminate\Support\Collection;

class TimesheetConflictResolver
{
    /**
     * Resolve conflicts for timesheet entities
     */
    public function resolve(Collection $conflictingEvents): SyncItem
    {
        // Group events by timesheet_id
        $eventsByEntity = $conflictingEvents->groupBy('entity_id');

        foreach ($eventsByEntity as $entityId => $events) {
            $resolvedEvent = $this->resolveEntityConflicts($events);
            if ($resolvedEvent) {
                return $resolvedEvent;
            }
        }

        // If no resolution found, return the most recent event
        return $conflictingEvents->sortByDesc('server_created_at')->first();
    }

    /**
     * Resolve conflicts for a specific entity
     * Logic: Higher authority always wins, within same authority use deterministic rules
     */
    private function resolveEntityConflicts(Collection $events): ?SyncItem
    {
        // Group by authority level
        $eventsByAuthority = $events->groupBy(function ($event) {
            return $event->authority_level ?? 'worker';
        });

        // Find highest authority level
        $highestAuthority = $this->getHighestAuthorityLevel($eventsByAuthority->keys());

        // Get events from highest authority
        $highestAuthEvents = $eventsByAuthority->get($highestAuthority, collect());

        if ($highestAuthEvents->isNotEmpty()) {
            // Within same authority, apply resolution rules
            return $this->resolveSameAuthorityConflicts($highestAuthEvents);
        }

        return null;
    }

    /**
     * Resolve conflicts within the same authority level
     */
    private function resolveSameAuthorityConflicts(Collection $events): ?SyncItem
    {
        // First, prioritize server-side events over device events
        $serverEvents = $events->filter(function ($event) {
            return empty($event->device_id); // Assume null/empty device_id means server-side
        });

        if ($serverEvents->isNotEmpty()) {
            return $serverEvents->sortByDesc('server_created_at')->first();
        }

        // If no server events, use most recent device event
        return $events->sortByDesc('server_created_at')->first();
    }

    /**
     * Get the highest authority level from a collection of levels
     */
    private function getHighestAuthorityLevel(Collection $authorityLevels): string
    {
        $highestLevel = AuthorityLevel::getLowestLevel();
        $highestValue = 0;

        foreach ($authorityLevels as $level) {
            $value = AuthorityLevel::getLevel($level);
            if ($value > $highestValue) {
                $highestValue = $value;
                $highestLevel = $level;
            }
        }

        return $highestLevel;
    }

    /**
     * Check if a conflict involves timesheet entities
     */
    public static function isTimesheetConflict(Conflict $conflict): bool
    {
        return ($conflict->local->entityType ?? '') === 'timesheet' ||
               ($conflict->remote->entityType ?? '') === 'timesheet';
    }

    /**
     * Resolve a timesheet conflict between two sync items
     */
    public static function resolveTimesheetConflict(Conflict $conflict): SyncItem
    {
        $localAuthority = $conflict->local->data['authority_level'] ?? 'worker';
        $remoteAuthority = $conflict->remote->data['authority_level'] ?? 'worker';

        $localLevel = AuthorityLevel::getLevel($localAuthority);
        $remoteLevel = AuthorityLevel::getLevel($remoteAuthority);

        // Higher authority wins
        if ($localLevel > $remoteLevel) {
            return $conflict->local;
        } elseif ($remoteLevel > $localLevel) {
            return $conflict->remote;
        }

        // Same authority level - check device priority
        $localIsServer = empty($conflict->local->deviceId);
        $remoteIsServer = empty($conflict->remote->deviceId);

        if ($localIsServer && ! $remoteIsServer) {
            return $conflict->local;
        } elseif (! $localIsServer && $remoteIsServer) {
            return $conflict->remote;
        }

        // Both same type (both server or both device) - most recent wins
        return $conflict->local->timestamp > $conflict->remote->timestamp
            ? $conflict->local : $conflict->remote;
    }

    /**
     * Validate if a modification is within authority limits
     */
    public function validateModificationAuthority(
        string $authorityLevel,
        float $originalHours,
        float $modifiedHours
    ): bool {
        return AuthorityLevel::canModifyHours($authorityLevel, $originalHours, $modifiedHours);
    }

    /**
     * Get conflict resolution metadata
     */
    public function getConflictMetadata(Collection $conflictingEvents): array
    {
        $authorityLevels = $conflictingEvents->pluck('authority_level')->unique();
        $timestamps = $conflictingEvents->pluck('server_created_at');
        $eventTypes = $conflictingEvents->pluck('event_type')->unique();
        $justifications = $conflictingEvents->pluck('modification_justification')->filter()->unique();
        $previousValues = $conflictingEvents->pluck('previous_values')->filter();

        return [
            'authority_levels_involved' => $authorityLevels->toArray(),
            'time_range' => [
                'earliest' => $timestamps->min(),
                'latest' => $timestamps->max(),
            ],
            'event_types' => $eventTypes->toArray(),
            'resolution_strategy' => 'authority_hierarchy',
            'highest_authority' => $this->getHighestAuthorityLevel($authorityLevels),
            'audit_trail' => [
                'justifications_provided' => $justifications->toArray(),
                'previous_values_count' => $previousValues->count(),
                'workflow_instance_ids' => $conflictingEvents->pluck('workflow_instance_id')->filter()->unique()->toArray(),
            ],
        ];
    }

    /**
     * Detect timesheet-specific conflicts
     */
    public function detectTimesheetConflicts(Collection $events): array
    {
        $conflicts = [];

        // Group by entity
        $eventsByEntity = $events->groupBy('entity_id');

        foreach ($eventsByEntity as $entityId => $entityEvents) {
            $entityConflicts = $this->detectEntityConflicts($entityEvents);
            $conflicts = array_merge($conflicts, $entityConflicts);
        }

        return $conflicts;
    }

    /**
     * Detect conflicts for a specific timesheet entity
     */
    private function detectEntityConflicts(Collection $events): array
    {
        $conflicts = [];

        // Multiple modifications to same timesheet
        $modificationEvents = $events->where('event_type', 'timesheet_modified');
        if ($modificationEvents->count() > 1) {
            $conflicts[] = [
                'type' => 'multiple_modifications',
                'events' => $modificationEvents,
                'description' => 'Multiple modifications detected for same timesheet',
            ];
        }

        // Conflicting approvals
        $approvalEvents = $events->where('event_type', 'timesheet_approved');
        if ($approvalEvents->count() > 1) {
            $authorities = $approvalEvents->pluck('authority_level')->unique();
            if ($authorities->count() === 1) {
                $conflicts[] = [
                    'type' => 'conflicting_approvals',
                    'events' => $approvalEvents,
                    'description' => 'Conflicting approvals from same authority level',
                ];
            }
        }

        // Time overlap conflicts (if timesheet has time ranges)
        $timeOverlapConflicts = $this->detectTimeOverlapConflicts($events);
        $conflicts = array_merge($conflicts, $timeOverlapConflicts);

        return $conflicts;
    }

    /**
     * Check if conflict requires manual intervention
     */
    public function requiresManualIntervention(Collection $conflictingEvents): bool
    {
        // If we have conflicting approvals from same authority level, manual intervention needed
        $approvalEvents = $conflictingEvents->where('event_type', 'timesheet_approved');

        if ($approvalEvents->count() > 1) {
            $authorities = $approvalEvents->pluck('authority_level')->unique();

            return $authorities->count() === 1; // Same authority approving differently
        }

        // If we have disputes without escalation, manual intervention needed
        $disputeEvents = $conflictingEvents->where('event_type', 'timesheet_disputed');
        $escalationEvents = $conflictingEvents->where('event_type', 'timesheet_escalated');

        return $disputeEvents->isNotEmpty() && $escalationEvents->isEmpty();
    }

    /**
     * Detect time overlap conflicts in timesheet events
     */
    private function detectTimeOverlapConflicts(Collection $events): array
    {
        $conflicts = [];

        // Get events that have time data (hours logged, modifications)
        $timeEvents = $events->filter(function ($event) {
            return in_array($event->event_type, [
                'hours_logged',
                'timesheet_modified',
            ]);
        });

        if ($timeEvents->count() < 2) {
            return $conflicts;
        }

        // Group by date if available, or check all combinations
        $timeRanges = $timeEvents->map(function ($event) {
            $data = $event->event_data ?? [];

            return [
                'event' => $event,
                'hours' => $data['hours'] ?? $data['new_hours'] ?? 0,
                'date' => $data['date'] ?? $data['work_date'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
            ];
        });

        // Check for overlapping time ranges
        foreach ($timeRanges as $i => $range1) {
            for ($j = $i + 1; $j < $timeRanges->count(); $j++) {
                $range2 = $timeRanges[$j];

                if ($this->rangesOverlap($range1, $range2)) {
                    $conflicts[] = [
                        'type' => 'time_overlap',
                        'events' => [$range1['event'], $range2['event']],
                        'description' => 'Timesheet entries have overlapping time ranges',
                        'overlap_details' => [
                            'range1' => $range1,
                            'range2' => $range2,
                        ],
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check if two time ranges overlap
     */
    private function rangesOverlap(array $range1, array $range2): bool
    {
        // If dates are different, no overlap
        if ($range1['date'] && $range2['date'] && $range1['date'] !== $range2['date']) {
            return false;
        }

        // If we have specific start/end times, check for overlap
        if ($range1['start_time'] && $range1['end_time'] &&
            $range2['start_time'] && $range2['end_time']) {

            $start1 = strtotime($range1['start_time']);
            $end1 = strtotime($range1['end_time']);
            $start2 = strtotime($range2['start_time']);
            $end2 = strtotime($range2['end_time']);

            return $start1 < $end2 && $end1 > $start2;
        }

        // If only hours are available, check if total hours exceed reasonable limits
        $totalHours = ($range1['hours'] ?? 0) + ($range2['hours'] ?? 0);

        return $totalHours > 24; // More than 24 hours in a day is suspicious
    }
}
