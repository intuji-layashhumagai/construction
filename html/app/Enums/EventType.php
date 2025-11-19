<?php

namespace App\Enums;

enum EventType: string
{
    case WORKER_CREATED = 'worker_created';
    case PROJECT_CREATED = 'project_created';
    case STOCK_CREATED = 'stock_created';
    case INVENTORY_CREATED = 'inventory_created';
    case HOURS_LOGGED = 'hours_logged';
    case STOCK_USED = 'stock_used';
    case STOCK_ADJUSTED = 'stock_adjusted';
    case TRAINING_COMPLETED = 'training_completed';
    case ROLE_UPDATED = 'role_updated';
    case STATUS_UPDATED = 'status_updated';
    case BUDGET_UPDATED = 'budget_updated';
    case STOCK_RECEIVED = 'stock_received';
}