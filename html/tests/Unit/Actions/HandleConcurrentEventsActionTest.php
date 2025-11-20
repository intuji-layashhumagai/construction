<?php

namespace Tests\Unit\Actions;

use App\Actions\Event\HandleConcurrentEventsAction;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HandleConcurrentEventsActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_handles_incoming_event_with_smaller_device_id_as_winner()
    {
        $id1 = (string) Str::uuid();
        $id2 = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $workerId = (string) Str::uuid();

        // Use fixed UUIDs with known lexicographic ordering
        $deviceId1 = '550e8400-e29b-41d4-a716-446655440000'; // Smaller (existing, will win)
        $deviceId2 = '550e8400-e29b-41d4-a716-446655440001'; // Larger (incoming)

        // Create existing event with device ID that comes later lexicographically
        $existingEvent = Event::create([
            'id' => $id1,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'worker_created',
            'event_data' => ['name' => 'John'],
            'device_id' => $deviceId1,
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'vector_clock' => [$deviceId1 => 1],
        ]);

        $incomingEventData = [
            'id' => $id2,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'hours_logged',
            'event_data' => ['hours_worked' => 8],
            'device_id' => $deviceId2, // Smaller lexicographically
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'device_vector_clock' => [$deviceId2 => 1],
        ];

        // Handle the concurrent event
        HandleConcurrentEventsAction::handle($incomingEventData, $existingEvent);

        // Verify the incoming event (device-a) was saved as winner
        $this->assertDatabaseHas('events', [
            'id' => $id2,
            'device_id' => $deviceId2,
        ]);

        $savedEvent = Event::find($id2);
        $expectedClock = [$deviceId1 => 1, $deviceId2 => 1]; // merged clocks
        $this->assertEquals($expectedClock, $savedEvent->vector_clock);
    }

    #[Test]
    public function it_handles_existing_event_with_smaller_device_id_as_winner()
    {
        $id1 = (string) Str::uuid();
        $id2 = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $workerId = (string) Str::uuid();

        // Use fixed UUIDs with known lexicographic ordering
        $deviceId1 = '550e8400-e29b-41d4-a716-446655440001'; // Larger (existing)
        $deviceId2 = '550e8400-e29b-41d4-a716-446655440000'; // Smaller (incoming, will win)
        // Create existing event with device ID that comes first lexicographically
        $existingEvent = Event::create([
            'id' => $id1,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'worker_created',
            'event_data' => ['name' => 'John'],
            'device_id' => $deviceId1, // Smaller lexicographically
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'vector_clock' => [$deviceId1 => 1],
        ]);

        $incomingEventData = [
            'id' => $id2,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'hours_logged',
            'event_data' => ['hours_worked' => 8],
            'device_id' => $deviceId2, // Larger lexicographically
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'device_vector_clock' => [$deviceId2 => 1],
        ];

        // Handle the concurrent event
        HandleConcurrentEventsAction::handle($incomingEventData, $existingEvent);

        // Verify the incoming event was still saved (existing event wins, but incoming still gets saved)
        $this->assertDatabaseHas('events', [
            'id' => $id2,
            'device_id' => $deviceId2,
        ]);

        $savedEvent = Event::find($id2);
        $expectedClock = [$deviceId1 => 1, $deviceId2 => 1]; // merged clocks
        $this->assertEquals($expectedClock, $savedEvent->vector_clock);
    }

    #[Test]
    public function it_uses_lexicographic_device_id_comparison_for_deterministic_resolution()
    {
        $id1 = (string) Str::uuid();
        $id2 = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $workerId = (string) Str::uuid();

        // Use fixed UUIDs with known lexicographic ordering
        $deviceId1 = '550e8400-e29b-41d4-a716-446655440001'; // Larger (existing)
        $deviceId2 = '550e8400-e29b-41d4-a716-446655440000'; // Smaller (incoming, will win)
        // Test that device-a always wins over device-b lexicographically
        $existingEvent = Event::create([
            'id' => $id1,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'worker_created',
            'event_data' => ['name' => 'Test'],
            'device_id' => $deviceId1,
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'vector_clock' => [$deviceId1 => 1],
        ]);

        $incomingEventData = [
            'id' => $id2,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'hours_logged',
            'event_data' => ['hours_worked' => 8],
            'device_id' => $deviceId2, // Should win lexicographically
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'device_vector_clock' => [$deviceId2 => 1],
        ];

        HandleConcurrentEventsAction::handle($incomingEventData, $existingEvent);

        // Verify device-a event was saved
        $this->assertDatabaseHas('events', [
            'id' => $id2,
            'device_id' => $deviceId2,
        ]);
    }
}
