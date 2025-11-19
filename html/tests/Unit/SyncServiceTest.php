<?php

namespace Tests\Unit;

use App\Services\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private SyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncService = new SyncService;
    }

    #[Test]
    public function it_processes_batch_of_events_in_transaction()
    {
        $eventId1 = (string) Str::uuid();
        $eventId2 = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $workerId = (string) Str::uuid();
        $deviceId = (string) Str::uuid();

        $eventsBatch = [
            [
                'id' => $eventId1,
                'entity_type' => 'worker',
                'entity_id' => $entityId,
                'event_type' => 'worker_created',
                'event_data' => ['name' => 'John'],
                'device_id' => $deviceId,
                'worker_id' => $workerId,
                'sequence_number' => 1,
                'device_vector_clock' => [$deviceId => 1],
            ],
            [
                'id' => $eventId2,
                'entity_type' => 'worker',
                'entity_id' => $entityId,
                'event_type' => 'hours_logged',
                'event_data' => ['hours_worked' => 8],
                'device_id' => $deviceId,
                'worker_id' => $workerId,
                'sequence_number' => 2,
                'device_vector_clock' => [$deviceId => 2],
            ],
        ];

        // Process the batch - this should create events in the database
        $this->syncService->processEventBatch($eventsBatch);

        // Verify both events were created
        $this->assertDatabaseHas('events', [
            'id' => $eventId1,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $eventId2,
            'entity_type' => 'worker',
            'entity_id' => $entityId,
        ]);
    }

    #[Test]
    public function it_wraps_batch_processing_in_database_transaction()
    {
        $eventId = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $workerId = (string) Str::uuid();
        $deviceId = (string) Str::uuid();

        $eventsBatch = [
            [
                'id' => $eventId,
                'entity_type' => 'worker',
                'entity_id' => $entityId,
                'event_type' => 'worker_created',
                'event_data' => ['name' => 'John'],
                'device_id' => $deviceId,
                'worker_id' => $workerId,
                'sequence_number' => 1,
                'device_vector_clock' => [$deviceId => 1],
            ],
        ];

        // Mock DB transaction to verify it's called
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->syncService->processEventBatch($eventsBatch);
    }

    #[Test]
    public function it_handles_empty_event_batch()
    {
        // Should complete without errors
        $this->syncService->processEventBatch([]);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_processes_single_event_batch()
    {
        $eventId = (string) Str::uuid();
        $entityId = (string) Str::uuid();
        $workerId = (string) Str::uuid();
        $deviceId = (string) Str::uuid();

        $eventsBatch = [
            [
                'id' => $eventId,
                'entity_type' => 'project',
                'entity_id' => $entityId,
                'event_type' => 'project_created',
                'event_data' => ['name' => 'New Project'],
                'device_id' => $deviceId,
                'worker_id' => $workerId,
                'sequence_number' => 1,
                'device_vector_clock' => [$deviceId => 1],
            ],
        ];

        $this->syncService->processEventBatch($eventsBatch);

        // Verify the event was created
        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'entity_type' => 'project',
            'entity_id' => $entityId,
        ]);
    }
}
