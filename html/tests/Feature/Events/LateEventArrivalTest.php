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
            'server_created_at' => now()->addDays(1), // Within partition range
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

   
}
