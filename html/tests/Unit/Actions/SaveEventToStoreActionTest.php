<?php

namespace Tests\Unit\Actions;

use App\Actions\Event\SaveEventToStoreAction;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SaveEventToStoreActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_and_saves_event_with_merged_vector_clock()
    {
        $eventId = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $workerId = (string) Str::uuid();
        $deviceId = (string) Str::uuid();

        $eventData = [
            'id' => $eventId,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => 'hours_logged',
            'event_data' => ['hours_worked' => 8],
            'device_id' => $deviceId,
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'created_at' => now(),
        ];

        $expectedMergedClock = ['device-1' => 2, $deviceId => 1];

        $result = SaveEventToStoreAction::handle($eventData, $expectedMergedClock);

        $this->assertInstanceOf(Event::class, $result);
        $this->assertEquals($eventId, $result->id);
        $this->assertEquals('worker', $result->entity_type);
        $this->assertEquals($entityId, $result->entity_id);
        $this->assertEquals('hours_logged', $result->event_type);
        $this->assertEquals(['hours_worked' => 8], $result->event_data);
        $this->assertEquals($deviceId, $result->device_id);
        $this->assertEquals($workerId, $result->worker_id);
        $this->assertEquals(1, $result->sequence_number);
        $this->assertEquals($expectedMergedClock, $result->vector_clock);

        // Verify it was saved to database
        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
        ]);
    }

    #[Test]
    public function it_handles_empty_authoritative_clock()
    {
        $id = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $deviceId = (string) Str::uuid();
        $workerId = (string) Str::uuid();
        $eventData = [
            'id' => $id,
            'entity_type' => 'project',
            'entity_id' => $entityId,
            'event_type' => 'project_created',
            'event_data' => ['name' => 'New Project'],
            'device_id' => $deviceId,
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'created_at' => now(),
        ];

        $deviceClock = [$deviceId => 1];

        $result = SaveEventToStoreAction::handle($eventData, $deviceClock);

        $this->assertEquals($deviceClock, $result->vector_clock);
    }
}
