<?php

namespace App\Services\Sync;

enum DataPriority: int
{
    case CRITICAL = 1;      // Safety alerts, emergency data (sync immediately)
    case HIGH = 2;          // Real-time operational data (< 5 min delay)
    case MEDIUM = 3;        // Recent business data (< 24h, sync when possible)
    case LOW = 4;           // Historical/archive data (background sync)
    case BACKGROUND = 5;    // Logs, analytics (sync only when bandwidth abundant)
}
