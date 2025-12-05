<?php

namespace App\Enums;

enum SyncType: string
{
    case NEW = 'new';
    case RESUME = 'resume';
}
