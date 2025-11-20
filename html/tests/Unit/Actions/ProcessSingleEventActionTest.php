<?php

namespace Tests\Unit\Actions;

use App\Actions\Event\ProcessSingleEventAction;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessSingleEventActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_processes_events_with_clear_causal_order()
    {
        $existingEventId = (string) Str::uuid();
        $newEventId = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $workerId = (string) Str::uuid();
        $deviceId = (string) Str::uuid();

        // Create an existing event in the database
        Event::create([
            'id' => $existingEventId,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'worker_created',
            'event_data' => ['name' => 'John'],
            'device_id' => $deviceId,
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'vector_clock' => [$deviceId => 1],
        ]);

        $incomingEventData = [
            'id' => $newEventId,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'hours_logged',
            'event_data' => ['hours_worked' => 8],
            'device_id' => $deviceId,
            'worker_id' => $workerId,
            'sequence_number' => 2,
            'device_vector_clock' => [$deviceId => 2],
        ];

        // Process the event - this should create a new event in the database
        ProcessSingleEventAction::handle($incomingEventData);

        // Verify the event was created with merged vector clock
        $this->assertDatabaseHas('events', [
            'id' => $newEventId,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'hours_logged',
        ]);

        $createdEvent = Event::find($newEventId);
        $expectedClock = [$deviceId => 2]; // max of [1] and [2]
        $this->assertEquals($expectedClock, $createdEvent->vector_clock);
    }

    #[Test]
    public function it_processes_concurrent_events()
    {
        $existingEventId = (string) Str::uuid();
        $concurrentEventId = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $workerId = (string) Str::uuid();

        // Use fixed UUIDs with known lexicographic ordering for deterministic testing
        // '550e8400-e29b-41d4-a716-446655440000' < '550e8400-e29b-41d4-a716-446655440001'
        $deviceIdA = '550e8400-e29b-41d4-a716-446655440000'; // Existing device (smaller, will win)
        $deviceIdB = '550e8400-e29b-41d4-a716-446655440001'; // Incoming device (larger)

        // Create an existing event
        Event::create([
            'id' => $existingEventId,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'worker_created',
            'event_data' => ['name' => 'John'],
            'device_id' => $deviceIdA,
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'vector_clock' => [$deviceIdA => 1],
        ]);

        $incomingEventData = [
            'id' => $concurrentEventId,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'hours_logged',
            'event_data' => ['hours_worked' => 8],
            'device_id' => $deviceIdB, // Different device, concurrent
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'device_vector_clock' => [$deviceIdB => 1],
        ];

        // Process the concurrent event
        ProcessSingleEventAction::handle($incomingEventData);

        // Verify the concurrent event was handled (existing device wins lexicographically)
        $this->assertDatabaseHas('events', [
            'id' => $concurrentEventId,
            'device_id' => $deviceIdB,
        ]);

        $createdEvent = Event::find($concurrentEventId);
        $expectedClock = [$deviceIdA => 1, $deviceIdB => 1]; // merged clocks
        $this->assertEquals($expectedClock, $createdEvent->vector_clock);
    }

    #[Test]
    public function it_handles_first_event_for_entity()
    {
        $firstEventId = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $workerId = (string) Str::uuid();
        $deviceId = (string) Str::uuid();

        $incomingEventData = [
            'id' => $firstEventId,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'worker_created',
            'event_data' => ['name' => 'John'],
            'device_id' => $deviceId,
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'device_vector_clock' => [$deviceId => 1],
        ];

        // Process the first event for this entity
        ProcessSingleEventAction::handle($incomingEventData);

        // Verify the event was created
        $this->assertDatabaseHas('events', [
            'id' => $firstEventId,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
        ]);

        $createdEvent = Event::find($firstEventId);
        // For first event, vector clock should be the device clock
        $this->assertEquals([$deviceId => 1], $createdEvent->vector_clock);
    }
}
