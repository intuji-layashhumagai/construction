<?php

namespace App\Enums;

enum QuarantinedEventStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case MIGRATED = 'migrated';
}
