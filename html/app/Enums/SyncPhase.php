<?php

namespace App\Enums;

enum SyncPhase: string
{
    case HANDSHAKE = 'handshake';
    case DISCOVERY = 'discovery';
    case TRANSFER = 'transfer';
    case VALIDATION = 'validation';
    case PROCESSING = 'processing';
    case COMPLETE = 'complete';
    case RESUME = 'resume';
}
