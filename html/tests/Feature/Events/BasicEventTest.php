<?php

namespace Tests\Feature\Events;

use Tests\TestCase;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BasicEventTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_store_and_retrieve_an_event()
    {
        $event = Event::create([
            'event_id' => 'test-uuid-1',
            'entity_type' => 'worker',
            'entity_id' => 'worker-uuid-1',
            'event_type' => 'worker_created',
            'event_data' => ['name' => 'John Doe'],
            'device_id' => 'device-uuid-1',
            'sequence_number' => 1,
        ]);

        $retrievedEvent = Event::find('test-uuid-1');

        $this->assertNotNull($retrievedEvent);
        $this->assertEquals('worker_created', $retrievedEvent->event_type);
        $this->assertEquals('John Doe', $retrievedEvent->event_data['name']);
    }
}