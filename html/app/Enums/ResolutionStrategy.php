<?php

namespace App\DTOs;

enum ResolutionStrategy: string
{
    case LATEST_WINS = 'latest_wins';
    case PRIORITIZE_SUPERVISOR = 'prioritize_supervisor';
    case MERGE_WITH_USER_INTERVENTION = 'merge_with_user_intervention';
}
