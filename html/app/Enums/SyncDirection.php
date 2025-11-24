<?php

namespace App\Enums;

enum SyncDirection: string
{
    case UPLOAD = 'upload';
    case DOWNLOAD = 'download';
    case BIDIRECTIONAL = 'bidirectional';
}
