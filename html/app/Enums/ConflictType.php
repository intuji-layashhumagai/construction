<?php

namespace App\Enums;

enum ConflictType: string
{
    case CONCURRENT_MODIFICATION = 'concurrent_modification';
    case DUPLICATE_DATA = 'duplicate_data';
    case DIVERGED_HISTORY = 'diverged_history';
}
