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
    case WORK_SESSION_STARTED = 'work_session_started';
    case WORK_LOGGED = 'work_logged';
    case DEVICE_HANDOVER = 'device_handover';
    case WORK_SESSION_ENDED = 'work_session_ended';
    case SESSION_MERGED = 'session_merged';
    case WORKER_LOGGED_IN = 'worker_logged_in';
    case WORKER_LOGGED_OUT = 'worker_logged_out';
    case TIMESHEET_MODIFIED = 'timesheet_modified';
    case TIMESHEET_APPROVED = 'timesheet_approved';
    case TIMESHEET_REJECTED = 'timesheet_rejected';
    case TIMESHEET_DISPUTED = 'timesheet_disputed';
    case TIMESHEET_ESCALATED = 'timesheet_escalated';
    case APPROVAL_WORKFLOW_STARTED = 'approval_workflow_started';
    case INVENTORY_TRANSFER_INITIATED = 'inventory_transfer_initiated';
    case INVENTORY_TRANSFER_RECEIVED = 'inventory_transfer_received';
    case INVENTORY_TRANSFER_CANCELLED = 'inventory_transfer_cancelled';
    case INVENTORY_TRANSFER_DISPUTED = 'inventory_transfer_disputed';
    case INVENTORY_TRANSFER_CHAIN_VALIDATED = 'inventory_transfer_chain_validated';
}
