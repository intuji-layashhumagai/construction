<?php

namespace App\Services\Sync;

use App\DTOs\Conflict;
use Exception;

class UnresolvedConflictException extends Exception
{
    public function __construct(
        public Conflict $conflict,
        string $message = 'Conflict could not be resolved automatically',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
