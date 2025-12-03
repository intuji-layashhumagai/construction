<?php

namespace App\DTOs;

use Carbon\Carbon;

final class QuarantineContext
{
    public function __construct(
        public readonly array $invalidEvents,
        public readonly array $processedEvents,
        public readonly string $sessionId,
        public readonly string $deviceId,
        public readonly string $workerId,
        public readonly Carbon $serverReceivedAt,
        public readonly array $violations,
    ) {}

    public static function create(
        array $invalidEvents,
        array $processedEvents,
        string $sessionId,
        string $deviceId,
        string $workerId,
        Carbon $serverReceivedAt,
        array $violations,
    ): self {
        return new self(
            invalidEvents: $invalidEvents,
            processedEvents: $processedEvents,
            sessionId: $sessionId,
            deviceId: $deviceId,
            workerId: $workerId,
            serverReceivedAt: $serverReceivedAt,
            violations: $violations,
        );
    }
}
