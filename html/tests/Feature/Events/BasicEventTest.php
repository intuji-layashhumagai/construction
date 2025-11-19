<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BasicEventTest extends TestCase
{
    // use RefreshDatabase;

    #[Test]
    public function it_can_store_and_retrieve_an_event()
    {
        $id = Str::uuid();

        $event = Event::create([
            'id' => $id,
            'entity_type' => 'worker',
            'entity_id' => Str::uuid(),
            'event_type' => 'worker_created',
            'event_data' => ['name' => 'John Doe'],
            'worker_id' => $id,
            'device_id' => Str::uuid(),
            'sequence_number' => 1,
        ]);

        $retrievedEvent = Event::where('id', $id->toString())->first();

        $this->assertNotNull($retrievedEvent);
        $this->assertEquals('worker_created', $retrievedEvent->event_type);
        $this->assertEquals('John Doe', $retrievedEvent->event_data['name']);
    }
}
