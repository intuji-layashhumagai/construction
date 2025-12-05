<?php

namespace App\Actions\Sync;

use DateTime;

class ScheduleResultAction
{
    public function __construct(
        public bool $immediate = false,
        public bool $scheduled = false,
        public bool $chunked = false,
        public ?DateTime $time = null,
        public ?string $reason = null,
        public ?int $chunkSize = null,
        public ?int $interval = null,
        public ?int $estimatedDuration = null
    ) {}

    public function isImmediate(): bool
    {
        return $this->immediate;
    }

    public function isScheduled(): bool
    {
        return $this->scheduled && $this->time !== null;
    }

    public function isChunked(): bool
    {
        return $this->chunked;
    }

    public function getDelaySeconds(): int
    {
        if (! $this->time) {
            return 0;
        }

        $now = now();
        $diff = $this->time->getTimestamp() - $now->getTimestamp();

        return max(0, $diff);
    }
}
