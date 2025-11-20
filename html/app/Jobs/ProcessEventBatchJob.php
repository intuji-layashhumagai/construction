<?php

namespace App\Jobs;

use App\Actions\Event\ProcessSingleEventAction;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Processes a batch of events for a given device.
 *
 * Handles bulk event synchronization, updates the authoritative vector clock in Redis,
 * and logs outcomes for observability. Retries up to 3 times and times out after 30 minutes.
 */
class ProcessEventBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'event_sync_processing';

    public int $timeout = 1800; // 30 minutes

    public int $tries = 3;

    public function __construct(
        protected string $deviceId,
        protected array $events
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->deviceId)) {
            throw new \InvalidArgumentException('Device ID cannot be empty.');
        }

        if (empty($this->events)) {
            throw new \InvalidArgumentException('Events array cannot be empty.');
        }
    }

    public function handle(): void
    {
        Log::info("Processing batch for device {$this->deviceId}", [
            'event_count' => count($this->events),
        ]);

        try {
            collect($this->events)->chunk(50)->each(function ($chunk) {
                DB::transaction(function () use ($chunk) {
                    foreach ($chunk as $event) {
                        ProcessSingleEventAction::handle($event);
                    }
                });
            });

            $this->updateAuthoritativeVectorClock();

            Log::info("Batch processed successfully for device {$this->deviceId}", [
                'event_count' => count($this->events),
            ]);
        } catch (Throwable $e) {
            Log::error("Batch processing failed for device {$this->deviceId}", [
                'event_count' => count($this->events),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function updateAuthoritativeVectorClock(): void
    {
        $latestEvent = Event::where('device_id', $this->deviceId)
            ->orderBy('server_created_at', 'desc')
            ->first();
        $finalVc = $latestEvent ? $latestEvent->vector_clock : [];

        $payload = [
            'status' => 'complete',
            'last_sync_time' => now()->toDateTimeString(),
            'final_vc' => $finalVc,
            'processed_at' => now()->toISOString(),
        ];

        Redis::set("vc_auth:{$this->deviceId}", json_encode($payload));

        Log::debug("Updated authoritative vector clock for device {$this->deviceId}", [
            'key' => "vc_auth:{$this->deviceId}",
            'data' => $payload,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("Sync job permanently failed for device {$this->deviceId}", [
            'event_count' => count($this->events),
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }

    /**
     * Safely retrieve the authoritative vector clock with corruption recovery.
     */
    protected function getAuthoritativeVectorClock(): array
    {
        $key = "vc_auth:{$this->deviceId}";

        $data = Redis::get($key);

        if ($data) {
            $decoded = json_decode($data, true);

            if ($this->isValidVectorClockData($decoded)) {
                return $decoded['final_vc'];
            }

            Log::warning("Corrupted vc_auth data for {$this->deviceId}, reconstructing from database");
        }

        // Reconstruct from database
        $latestEvent = Event::where('device_id', $this->deviceId)
            ->orderBy('server_created_at', 'desc')
            ->first();

        $finalVc = $latestEvent ? $latestEvent->vector_clock : [];

        // Update Redis with reconstructed data
        $payload = [
            'status' => 'reconstructed',
            'last_sync_time' => now()->toDateTimeString(),
            'final_vc' => $finalVc,
            'processed_at' => now()->toISOString(),
        ];

        Redis::set($key, json_encode($payload));

        Log::info("Reconstructed and updated vc_auth for {$this->deviceId}");

        return $finalVc;
    }

    /**
     * Validate the structure of vector clock data.
     */
    private function isValidVectorClockData(?array $data): bool
    {
        if (! is_array($data) || ! isset($data['final_vc']) || ! is_array($data['final_vc'])) {
            return false;
        }

        foreach ($data['final_vc'] as $device => $count) {
            if (! is_string($device) || ! is_int($count) || $count < 0) {
                return false;
            }
        }

        return true;
    }
}
