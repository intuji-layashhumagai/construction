<?php

namespace App\Services\Sync;

use App\DTOs\SyncItem;
use App\Enums\DataPriority;

class PriorityQueue
{
    private array $queues = [];

    public function enqueue(SyncItem $item): void
    {
        $priority = $item->priority->value;
        $this->queues[$priority][] = $item;
    }

    public function dequeue(): ?SyncItem
    {
        foreach (DataPriority::cases() as $priority) {
            if (! empty($this->queues[$priority->value])) {
                return array_shift($this->queues[$priority->value]);
            }
        }

        return null;
    }

    public function getHighestPriority(): DataPriority
    {
        foreach (DataPriority::cases() as $priority) {
            if (! empty($this->queues[$priority->value])) {
                return $priority;
            }
        }

        return DataPriority::BACKGROUND;
    }

    public function isEmpty(): bool
    {
        foreach ($this->queues as $queue) {
            if (! empty($queue)) {
                return false;
            }
        }

        return true;
    }

    public function count(): int
    {
        $total = 0;
        foreach ($this->queues as $queue) {
            $total += count($queue);
        }

        return $total;
    }
}
