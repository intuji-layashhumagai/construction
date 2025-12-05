<?php

namespace App\Services\Sync;

use Exception;

class SyncException extends Exception
{
    public function __construct(
        string $message,
        public string $errorCode = 'SYNC_ERROR',
        public bool $canResume = true,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
