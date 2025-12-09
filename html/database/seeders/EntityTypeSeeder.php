<?php

namespace Database\Seeders;

use App\Models\EntityEventType;
use App\Models\EntityType;
use App\Models\EventType;
use Illuminate\Database\Seeder;

class EntityTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, create all event types from the enum
        $this->createEventTypes();

        // Then create entity types
        $this->createEntityTypes();

        // Finally create the relationships
        $this->createEntityEventRelationships();
    }

    private function createEventTypes(): void
    {
        $eventTypes = [
            ['event_type' => 'WORKER_CREATED', 'name' => 'Worker Created', 'description' => 'A new worker is added to the system'],
            ['event_type' => 'PROJECT_CREATED', 'name' => 'Project Created', 'description' => 'A new project is initiated'],
            ['event_type' => 'STOCK_CREATED', 'name' => 'Stock Created', 'description' => 'New inventory stock is created'],
            ['event_type' => 'INVENTORY_CREATED', 'name' => 'Inventory Created', 'description' => 'New inventory item is created'],
            ['event_type' => 'HOURS_LOGGED', 'name' => 'Hours Logged', 'description' => 'Work hours are recorded'],
            ['event_type' => 'STOCK_USED', 'name' => 'Stock Used', 'description' => 'Inventory stock is consumed'],
            ['event_type' => 'STOCK_ADJUSTED', 'name' => 'Stock Adjusted', 'description' => 'Inventory stock levels are adjusted'],
            ['event_type' => 'TRAINING_COMPLETED', 'name' => 'Training Completed', 'description' => 'Worker completes training'],
            ['event_type' => 'ROLE_UPDATED', 'name' => 'Role Updated', 'description' => 'Worker role is changed'],
            ['event_type' => 'STATUS_UPDATED', 'name' => 'Status Updated', 'description' => 'Entity status is updated'],
            ['event_type' => 'BUDGET_UPDATED', 'name' => 'Budget Updated', 'description' => 'Project budget is modified'],
            ['event_type' => 'STOCK_RECEIVED', 'name' => 'Stock Received', 'description' => 'New inventory stock is received'],
            ['event_type' => 'WORK_SESSION_STARTED', 'name' => 'Work Session Started', 'description' => 'A work session begins'],
            ['event_type' => 'WORK_LOGGED', 'name' => 'Work Logged', 'description' => 'Work activity is recorded'],
            ['event_type' => 'DEVICE_HANDOVER', 'name' => 'Device Handover', 'description' => 'Device is handed over between workers'],
            ['event_type' => 'WORK_SESSION_ENDED', 'name' => 'Work Session Ended', 'description' => 'A work session concludes'],
            ['event_type' => 'SESSION_MERGED', 'name' => 'Session Merged', 'description' => 'Multiple sessions are merged'],
        ];

        foreach ($eventTypes as $eventType) {
            EventType::create($eventType);
        }
    }

    private function createEntityTypes(): void
    {
        $entityTypes = [
            ['entity_type' => 'worker', 'name' => 'Worker', 'description' => 'Tracks worker profiles and basic information'],
            ['entity_type' => 'worker_session', 'name' => 'Worker Session', 'description' => 'Tracks worker time, activities, and session management across devices'],
            ['entity_type' => 'project', 'name' => 'Project', 'description' => 'Tracks project assignments, progress, and management'],
            ['entity_type' => 'inventory', 'name' => 'Inventory', 'description' => 'Tracks materials, supplies, and stock levels'],
            ['entity_type' => 'equipment', 'name' => 'Equipment', 'description' => 'Tracks tools, machinery, and equipment lifecycle'],
            ['entity_type' => 'payment_ledger', 'name' => 'Payment Ledger', 'description' => 'Tracks financial transactions and payment processing'],
            ['entity_type' => 'break_session', 'name' => 'Break Session', 'description' => 'Tracks worker break times and durations'],
            ['entity_type' => 'timesheet', 'name' => 'Timesheet', 'description' => 'Tracks work hours and time reporting'],
            ['entity_type' => 'shift_session', 'name' => 'Shift Session', 'description' => 'Tracks shift handovers and transitions'],
            ['entity_type' => 'inventory_transfer', 'name' => 'Inventory Transfer', 'description' => 'Tracks peer-to-peer material transfers'],
            ['entity_type' => 'project_assignment', 'name' => 'Project Assignment', 'description' => 'Tracks worker assignments to projects'],
            ['entity_type' => 'equipment_reservation', 'name' => 'Equipment Reservation', 'description' => 'Tracks equipment reservations and usage'],
            ['entity_type' => 'equipment_lifecycle', 'name' => 'Equipment Lifecycle', 'description' => 'Tracks equipment maintenance and lifecycle events'],
            ['entity_type' => 'equipment_location', 'name' => 'Equipment Location', 'description' => 'Tracks equipment physical locations'],
            ['entity_type' => 'equipment_maintenance', 'name' => 'Equipment Maintenance', 'description' => 'Tracks equipment maintenance schedules and records'],
            ['entity_type' => 'equipment_damage', 'name' => 'Equipment Damage', 'description' => 'Tracks equipment damage reports and repairs'],
            ['entity_type' => 'equipment_sharing', 'name' => 'Equipment Sharing', 'description' => 'Tracks equipment sharing between workers/projects'],
        ];

        foreach ($entityTypes as $entityType) {
            EntityType::create($entityType);
        }
    }

    private function createEntityEventRelationships(): void
    {
        $relationships = [
            // Worker entity relationships
            ['entity_type' => 'worker', 'event_type' => 'WORKER_CREATED', 'is_initial_event' => true, 'priority' => 1],
            ['entity_type' => 'worker', 'event_type' => 'STATUS_UPDATED', 'priority' => 3],
            ['entity_type' => 'worker', 'event_type' => 'ROLE_UPDATED', 'priority' => 3],
            ['entity_type' => 'worker', 'event_type' => 'TRAINING_COMPLETED', 'priority' => 3],

            // Worker session relationships
            ['entity_type' => 'worker_session', 'event_type' => 'WORK_SESSION_STARTED', 'is_initial_event' => true, 'priority' => 2],
            ['entity_type' => 'worker_session', 'event_type' => 'HOURS_LOGGED', 'requires_approval' => true, 'priority' => 2],
            ['entity_type' => 'worker_session', 'event_type' => 'WORK_LOGGED', 'priority' => 3],
            ['entity_type' => 'worker_session', 'event_type' => 'STATUS_UPDATED', 'priority' => 3],
            ['entity_type' => 'worker_session', 'event_type' => 'DEVICE_HANDOVER', 'priority' => 2],
            ['entity_type' => 'worker_session', 'event_type' => 'WORK_SESSION_ENDED', 'priority' => 2],
            ['entity_type' => 'worker_session', 'event_type' => 'SESSION_MERGED', 'priority' => 1],

            // Project relationships
            ['entity_type' => 'project', 'event_type' => 'PROJECT_CREATED', 'is_initial_event' => true, 'priority' => 1],
            ['entity_type' => 'project', 'event_type' => 'STATUS_UPDATED', 'priority' => 3],
            ['entity_type' => 'project', 'event_type' => 'BUDGET_UPDATED', 'requires_approval' => true, 'priority' => 2],
            ['entity_type' => 'project', 'event_type' => 'ROLE_UPDATED', 'priority' => 3],

            // Inventory relationships
            ['entity_type' => 'inventory', 'event_type' => 'STOCK_CREATED', 'is_initial_event' => true, 'priority' => 2],
            ['entity_type' => 'inventory', 'event_type' => 'STOCK_RECEIVED', 'priority' => 2],
            ['entity_type' => 'inventory', 'event_type' => 'STOCK_USED', 'priority' => 2],
            ['entity_type' => 'inventory', 'event_type' => 'STOCK_ADJUSTED', 'requires_approval' => true, 'priority' => 2],
            ['entity_type' => 'inventory', 'event_type' => 'STATUS_UPDATED', 'priority' => 3],

            // Equipment relationships
            ['entity_type' => 'equipment', 'event_type' => 'STATUS_UPDATED', 'priority' => 3],
            ['entity_type' => 'equipment', 'event_type' => 'TRAINING_COMPLETED', 'priority' => 3],

            // Payment ledger relationships
            ['entity_type' => 'payment_ledger', 'event_type' => 'STATUS_UPDATED', 'priority' => 2],

            // Other entity relationships
            ['entity_type' => 'break_session', 'event_type' => 'STATUS_UPDATED', 'priority' => 3],
            ['entity_type' => 'timesheet', 'event_type' => 'HOURS_LOGGED', 'requires_approval' => true, 'priority' => 2],
            ['entity_type' => 'timesheet', 'event_type' => 'STATUS_UPDATED', 'priority' => 3],
            ['entity_type' => 'shift_session', 'event_type' => 'STATUS_UPDATED', 'priority' => 3],
            ['entity_type' => 'shift_session', 'event_type' => 'DEVICE_HANDOVER', 'priority' => 2],
            ['entity_type' => 'inventory_transfer', 'event_type' => 'STATUS_UPDATED', 'priority' => 2],
            ['entity_type' => 'project_assignment', 'event_type' => 'STATUS_UPDATED', 'priority' => 2],
            ['entity_type' => 'equipment_reservation', 'event_type' => 'STATUS_UPDATED', 'priority' => 2],
            ['entity_type' => 'equipment_lifecycle', 'event_type' => 'STATUS_UPDATED', 'priority' => 3],
            ['entity_type' => 'equipment_location', 'event_type' => 'STATUS_UPDATED', 'priority' => 4],
            ['entity_type' => 'equipment_maintenance', 'event_type' => 'STATUS_UPDATED', 'priority' => 2],
            ['entity_type' => 'equipment_damage', 'event_type' => 'STATUS_UPDATED', 'priority' => 1],
            ['entity_type' => 'equipment_sharing', 'event_type' => 'STATUS_UPDATED', 'priority' => 3],
        ];

        foreach ($relationships as $relationship) {
            $entityType = EntityType::where('entity_type', $relationship['entity_type'])->first();
            $eventType = EventType::where('event_type', $relationship['event_type'])->first();

            if ($entityType && $eventType) {
                EntityEventType::create([
                    'entity_type_id' => $entityType->id,
                    'event_type_id' => $eventType->id,
                    'name' => $eventType->name.' for '.$entityType->name,
                    'description' => $relationship['description'] ?? null,
                    'is_initial_event' => $relationship['is_initial_event'] ?? false,
                    'requires_approval' => $relationship['requires_approval'] ?? false,
                    'priority' => $relationship['priority'] ?? 3,
                ]);
            }
        }
    }
}
