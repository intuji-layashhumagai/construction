<?php

namespace App\Services;

use App\DTOs\SyncContext;
use App\Enums\SyncType;
use Illuminate\Http\JsonResponse;

class SyncResponseBuilder
{
    public static function success(SyncContext $context, array $validEvents, string $jobId): JsonResponse
    {
        return response()->json([
            'status' => 'queued',
            'session_id' => $context->sessionId,
            'job_id' => $jobId,
            'sync_type' => SyncType::NEW->value,
            'total_events' => count($context->processedEvents),
            'valid_events' => count($validEvents),
            'invalid_events' => 0,
            'quarantined_events' => 0,
            'violations' => [],
            'estimated_events' => count($validEvents),
            'queue_name' => \App\Jobs\HighPerformanceSyncJob::QUEUE_NAME,
            'estimated_completion' => self::estimateCompletionTime(count($validEvents)),
            'server_timestamp' => $context->serverReceivedAt,
            'message' => 'All events processed successfully.',
        ], 202);
    }

    public static function validationError(SyncContext $context, array $validationResults): JsonResponse
    {
        return response()->json([
            'status' => 'failed',
            'reason' => 'business_rule_violations',
            'violations' => $validationResults['violations'],
            'session_id' => $context->sessionId,
            'total_events' => count($context->processedEvents),
            'valid_events' => count($validationResults['valid_events']),
            'invalid_events' => count($validationResults['invalid_events']),
            'quarantined_events' => count($context->processedEvents),
            'server_timestamp' => $context->serverReceivedAt,
            'message' => 'All events quarantined due to business rule violations.',
        ], 422);
    }

    public static function dispatchError(SyncContext $context, string $error): JsonResponse
    {
        return response()->json([
            'status' => 'dispatch_failed',
            'session_id' => $context->sessionId,
            'error' => $error,
            'all_events_quarantined' => count($context->processedEvents),
        ], 500);
    }

    public static function resumeSuccess(array $result): JsonResponse
    {
        return response()->json($result);
    }

    private static function estimateCompletionTime(int $eventCount): string
    {
        // Target: 10,000 events/second during peak
        // Conservative estimate: 5,000 events/second average
        $estimatedSeconds = ceil($eventCount / 5000);

        // Add overhead for queue processing, database operations, etc.
        $estimatedSeconds += 2; // 2 second overhead

        if ($estimatedSeconds < 60) {
            return "{$estimatedSeconds} seconds";
        }

        $minutes = floor($estimatedSeconds / 60);
        $seconds = $estimatedSeconds % 60;

        if ($minutes < 60) {
            return $seconds > 0 ? "{$minutes}m {$seconds}s" : "{$minutes}m";
        }

        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;

        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    }
}
