<?php

namespace App\Actions\Sync;

use App\DTOs\QuarantineContext;
use App\DTOs\SyncContext;
use App\Models\SyncSession;
use App\Services\RuleEngineService;

final class ProcessAndValidateEventsAction
{
    public function __construct(
        private RuleEngineService $ruleEngine
    ) {}

    public function handle(SyncContext $syncContext): array
    {
        $validationResults = $this->validateSyncDataAgainstRules($syncContext->processedEvents);
        $validEvents = $validationResults['valid_events'];
        $invalidEvents = $validationResults['invalid_events'];

        // If any events are invalid, quarantine all and fail the sync
        if (! empty($invalidEvents)) {
            $quarantineContext = QuarantineContext::create(
                invalidEvents: $invalidEvents,
                processedEvents: $syncContext->processedEvents,
                sessionId: $syncContext->sessionId,
                deviceId: $syncContext->deviceId,
                workerId: $syncContext->workerId,
                serverReceivedAt: $syncContext->serverReceivedAt,
                violations: $validationResults['violations']
            );

            QuarantineInvalidEventsAction::handle($quarantineContext);
            $this->markSessionFailed($syncContext->sessionId, $validationResults['violations']);

            return [
                'is_valid' => false,
                'valid_events' => $validEvents,
                'invalid_events' => $invalidEvents,
                'violations' => $validationResults['violations'],
            ];
        }

        return [
            'is_valid' => true,
            'valid_events' => $validEvents,
            'invalid_events' => $invalidEvents,
            'violations' => [],
        ];
    }

    private function validateSyncDataAgainstRules(array $syncData): array
    {
        $validEvents = [];
        $invalidEvents = [];
        $violations = [];

        foreach ($syncData as $index => $eventData) {
            $tempEvent = (object) $eventData;
            $validationResult = $this->ruleEngine->validateEvent($tempEvent, [
                'sync_context' => true,
            ]);

            if ($validationResult['isValid']) {
                $validEvents[] = $eventData;
            } else {
                $invalidEvents[] = $eventData;
                $violations = array_merge($violations, array_map(function ($violation) use ($index) {
                    return ['event_index' => $index] + $violation;
                }, $validationResult['violations']));
            }
        }

        return [
            'valid_events' => $validEvents,
            'invalid_events' => $invalidEvents,
            'violations' => $violations,
        ];
    }

    private function markSessionFailed(string $sessionId, array $violations): void
    {
        $session = SyncSession::find($sessionId);
        if ($session) {
            $session->update([
                'status' => 'failed',
                'error_message' => 'Business rule violations: '.json_encode($violations),
            ]);
        }
    }
}
