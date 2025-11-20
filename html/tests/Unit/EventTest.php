<?php

namespace Tests\Unit;

use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_applies_creation_events_by_merging_data()
    {
        $event = new Event([
            'event_type' => EventType::WORKER_CREATED->value,
            'event_data' => ['name' => 'John Doe', 'role' => 'carpenter'],
        ]);

        $currentState = [];
        $result = $this->invokePrivateMethod($event, 'stateCalculations', [$currentState]);

        $expected = ['name' => 'John Doe', 'role' => 'carpenter'];
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_accumulates_hours_worked_for_hours_logged_events()
    {
        $event = new Event([
            'event_type' => EventType::HOURS_LOGGED->value,
            'event_data' => ['hours_worked' => 8],
        ]);

        $currentState = ['hours_worked' => 5];
        $result = $this->invokePrivateMethod($event, 'stateCalculations', [$currentState]);

        $expected = ['hours_worked' => 13];
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_initializes_hours_worked_when_not_present()
    {
        $event = new Event([
            'event_type' => EventType::HOURS_LOGGED->value,
            'event_data' => ['hours_worked' => 8],
        ]);

        $currentState = [];
        $result = $this->invokePrivateMethod($event, 'stateCalculations', [$currentState]);

        $expected = ['hours_worked' => 8];
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_subtracts_quantity_for_stock_used_events()
    {
        $event = new Event([
            'event_type' => EventType::STOCK_USED->value,
            'event_data' => ['quantity_used' => 50],
        ]);

        $currentState = ['quantity' => 100];
        $result = $this->invokePrivateMethod($event, 'stateCalculations', [$currentState]);

        $expected = ['quantity' => 50];
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_adjusts_quantity_for_stock_adjusted_events()
    {
        $event = new Event([
            'event_type' => EventType::STOCK_ADJUSTED->value,
            'event_data' => ['adjustment' => 25],
        ]);

        $currentState = ['quantity' => 100];
        $result = $this->invokePrivateMethod($event, 'stateCalculations', [$currentState]);

        $expected = ['quantity' => 125];
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_handles_negative_adjustments()
    {
        $event = new Event([
            'event_type' => EventType::STOCK_ADJUSTED->value,
            'event_data' => ['adjustment' => -10],
        ]);

        $currentState = ['quantity' => 100];
        $result = $this->invokePrivateMethod($event, 'stateCalculations', [$currentState]);

        $expected = ['quantity' => 90];
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_merges_data_for_other_event_types()
    {
        $event = new Event([
            'event_type' => EventType::ROLE_UPDATED->value,
            'event_data' => ['role' => 'senior_carpenter'],
        ]);

        $currentState = ['name' => 'John Doe'];
        $result = $this->invokePrivateMethod($event, 'stateCalculations', [$currentState]);

        $expected = ['name' => 'John Doe', 'role' => 'senior_carpenter'];
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_handles_unknown_event_types_by_merging_data()
    {
        $event = new Event([
            'event_type' => 'unknown_event_type',
            'event_data' => ['custom_field' => 'value'],
        ]);

        $currentState = ['existing_field' => 'existing_value'];
        $result = $this->invokePrivateMethod($event, 'stateCalculations', [$currentState]);

        $expected = ['existing_field' => 'existing_value', 'custom_field' => 'value'];
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_replays_events_for_entity_correctly()
    {
        $workerId = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $deviceId = (string) Str::uuid();

        // Create test events with explicit timestamps to ensure deterministic ordering
        $baseTime = now();

        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => EventType::WORKER_CREATED->value,
            'event_data' => ['name' => 'John Doe', 'hours_worked' => 0],
            'device_id' => $deviceId,
            'worker_id' => $workerId,
            'sequence_number' => 1,
            'server_created_at' => $baseTime,
            'vector_clock' => json_encode([$deviceId => 1]),
        ]);

        Event::create([
            'id' => (string) Str::uuid(),
            'entity_type' => 'worker',
            'entity_id' => $entityId,
            'event_type' => EventType::HOURS_LOGGED->value,
            'event_data' => ['hours_worked' => 8],
            'device_id' => $deviceId,
            'worker_id' => $workerId,
            'sequence_number' => 2,
            'vector_clock' => json_encode([$deviceId => 1]),
            'server_created_at' => $baseTime->addSecond(), // Ensure later timestamp
        ]);

        $finalState = Event::replayEventSequence('worker', $entityId);

        $expected = [
            'name' => 'John Doe',
            'hours_worked' => 8,
        ];
        $this->assertEquals($expected, $finalState);
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invokePrivateMethod($object, string $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
