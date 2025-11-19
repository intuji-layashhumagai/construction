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
}
