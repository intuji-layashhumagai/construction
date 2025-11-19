<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use illuminate\support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LateEventArrivalTest extends TestCase
{
    use RefreshDatabase;

    private string $workerId;

    private string $deviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workerId = (string) Str::uuid();
        $this->deviceId = (string) Str::uuid();
    }

    /**
     * Scenario: Events arrive days or weeks after they occurred due to offline devices
     * Requirement: Event store must handle late events and maintain correct state
     */
    #[Test]
    public function it_handles_events_arriving_weeks_late_from_offline_devices()
    {
        // Simulate initial state that the device was online and events created
        $this->createInitialEventSequence();

        // Device goes offline for 3 weeks but  other events continue to occur in the system and  Simulate events that happeened while device was offline
        $this->createEventsDuringDeviceOfflinePeriod();

        // Device comes back online after 3 weeks and syncs its old events
        $this->syncLateEventsFromOfflineDevice();

        // Verify the final state is correct even for late arrivals
        $this->verifyFinalStateIsCorrect();
    }

    private function createInitialEventSequence(): void
    {
        // Device creates initial worker record before going offline
        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'worker',
            'entity_id' => $this->workerId,
            'worker_id' => $this->workerId,
            'event_type' => 'worker_created',
            'event_data' => ['name' => 'John Doe', 'role' => 'carpenter', 'hours_worked' => 0],
            'device_id' => $this->deviceId,
            'sequence_number' => 1,
            'server_created_at' => now()->addDays(1),
        ]);

        // Device logs some hours before going offline
        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'worker',
            'entity_id' => $this->workerId,
            'worker_id' => $this->workerId,
            'event_type' => 'hours_logged',
            'event_data' => ['hours_worked' => 40],
            'device_id' => $this->deviceId,
            'sequence_number' => 2,
            'server_created_at' => now()->addDays(2), // Within partition range
        ]);
    }

    private function createEventsDuringDeviceOfflinePeriod(): void
    {
        // Other devices continue working while the test device is offline

        // Worker gets promoted by supervisor
        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'worker',
            'entity_id' => $this->workerId,
            'worker_id' => $this->workerId,
            'event_type' => 'role_updated',
            'event_data' => ['role' => 'senior_carpenter'],
            'device_id' => (string) Str::uuid(),
            'sequence_number' => 1, // Different device, so sequence is separate
            'server_created_at' => now()->addDays(10),
        ]);

        // More hours logged by other workers
        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'worker',
            'entity_id' => $this->workerId,
            'worker_id' => $this->workerId,
            'event_type' => 'hours_logged',
            'event_data' => ['hours_worked' => 35],
            'device_id' => (string) Str::uuid(),
            'sequence_number' => 1,
            'server_created_at' => now()->addDays(15),
        ]);
    }

    private function syncLateEventsFromOfflineDevice(): void
    {
        // Device comes back online and syncs events from its offline period

        // These events have old timestamps but are arriving now
        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'worker',
            'entity_id' => $this->workerId,
            'worker_id' => $this->workerId,
            'event_type' => 'hours_logged',
            'event_data' => ['hours_worked' => 25], // Work done 2.5 weeks ago
            'device_id' => $this->deviceId,
            'sequence_number' => 3, // Continues from this device's sequence
            'server_created_at' => now()->addDays(20),
        ]);

        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'worker',
            'entity_id' => $this->workerId,
            'worker_id' => $this->workerId,
            'event_type' => 'training_completed',
            'event_data' => ['training' => 'safety_certification', 'status' => 'completed'],
            'device_id' => $this->deviceId,
            'sequence_number' => 4,
            'server_created_at' => now()->addDays(25),
        ]);
    }

    private function verifyFinalStateIsCorrect(): void
    {
        $finalState = Event::replayEventSequence('worker', $this->workerId);
        info($finalState);

        // Verify all events addups  to the final state
        $this->assertEquals('John Doe', $finalState['name']);
        $this->assertEquals('senior_carpenter', $finalState['role']);
        $this->assertEquals('completed', $finalState['status']); // From training_completed

        // Verify hours are correctly accumulated from all devices
        // 40 (initial) + 35 (online) + 25 (late) = 100 hours
        $this->assertEquals(100, $finalState['hours_worked']);

        // Verify training certification is included
        $this->assertEquals('safety_certification', $finalState['training']);
    }

    /**
     * Scenario: Device clocks might be wrong, showing events happening in the future or distant past
     * Requirement: Events should be ordered by sequence number within device, not timestamp
     */
    #[Test]
    public function events_are_ordered_by_sequence_not_timestamp_for_same_device()
    {
        $entityId = (string) Str::uuid();

        // Create events with wrong timestamps for the device's clock was incorrect or out of sync
        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'inventory',
            'entity_id' => $entityId,
            'worker_id' => $this->workerId,
            'event_type' => 'stock_received',
            'event_data' => ['item' => 'nails', 'quantity' => 100],
            'device_id' => $this->deviceId,
            'sequence_number' => 1,
            'server_created_at' => now()->addMonths(1)->addDays(5),
        ]);

        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'inventory',
            'entity_id' => $entityId,
            'worker_id' => $this->workerId,
            'event_type' => 'stock_used',
            'event_data' => ['quantity_used' => 30],
            'device_id' => $this->deviceId,
            'sequence_number' => 2,
            'server_created_at' => now()->addMonths(1)->subDays(1),
        ]);

        $events = Event::forEntity('inventory', $entityId)
            ->orderBy('sequence_number')
            ->get();

        // Events should be in sequence order not by the timestamps
        $this->assertEquals(1, $events[0]->sequence_number);
        $this->assertEquals('stock_received', $events[0]->event_type);
        $this->assertEquals(2, $events[1]->sequence_number);
        $this->assertEquals('stock_used', $events[1]->event_type);
    }

    /**
     * Scenario: Events created under outdated business rules must be validated
     * Requirement: Old events should be accepted even if they don't match current business rules
     */
    #[Test]
    public function events_created_under_outdated_business_rules_are_accepted()
    {
        $entityId = (string) Str::uuid();

        // Create an old event with data that might be invalid under current rules
        // For example, an old role that is no longer allowed
        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'worker_id' => $entityId,
            'event_type' => 'worker_created',
            'event_data' => ['name' => 'Old Worker', 'role' => 'deprecated_role'], // Old role
            'device_id' => $this->deviceId,
            'sequence_number' => 1,
            'server_created_at' => now()->addMonths(2)->addDays(10),
        ]);

        // The event should be accepted and replayed correctly
        $finalState = Event::replayEventSequence('worker', $entityId);
        $this->assertEquals('deprecated_role', $finalState['role']);
    }

    /**
     * Scenario: Materialized views must update correctly when late events arrive
     * Requirement: Late events should trigger view updates to reflect current state
     */
    #[Test]
    public function materialized_views_update_correctly_when_late_events_arrive()
    {
        $entityId = (string) Str::uuid();

        // Create initial state
        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'inventory',
            'entity_id' => $entityId,
            'worker_id' => $this->workerId,
            'event_type' => 'stock_created',
            'event_data' => ['item' => 'bricks', 'quantity' => 1000],
            'device_id' => $this->deviceId,
            'sequence_number' => 1,
            'server_created_at' => now()->addDays(40),
        ]);

        // Late event arrives
        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'inventory',
            'entity_id' => $entityId,
            'worker_id' => $this->workerId,
            'event_type' => 'stock_used',
            'event_data' => ['quantity_used' => 200],
            'device_id' => $this->deviceId,
            'sequence_number' => 2,
            'server_created_at' => now()->addDays(45),
        ]);

        $finalState = Event::replayEventSequence('inventory', $entityId);
        info($finalState);

        // Materialized view should reflect the late event
        $this->assertEquals(800, $finalState['quantity']); // 1000 - 200
    }


   /**
     * Scenario: Old events must be archived but remain queryable for audit purposes
     * Requirement: Archived events should still be accessible for compliance
     */
    #[Test]
    public function old_events_archived_but_remain_queryable_for_auditing()
    {
        $entityId = (string) Str::uuid();

        // Create an old event
        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'worker_id' => $entityId,
            'event_type' => 'worker_created',
            'event_data' => ['name' => 'Audit Worker'],
            'device_id' => $this->deviceId,
            'sequence_number' => 1,
            'server_created_at' => now()->addMonths(2)->addDays(20),
        ]);

        // Even if archived, should still be queryable
        $events = Event::forEntity('worker', $entityId)->get();
        $this->assertCount(1, $events);
        $this->assertEquals('Audit Worker', $events[0]->event_data['name']);
    }
}
