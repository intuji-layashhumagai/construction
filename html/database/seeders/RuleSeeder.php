<?php

namespace Database\Seeders;

use App\Models\Rule;
use Illuminate\Database\Seeder;

class RuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rules = [
            // Validation Rules
            [
                'name' => 'Budget Limit Validation',
                'version' => '1.0.0',
                'entity_type' => 'project',
                'event_type' => 'budget_updated',
                'conditions' => [
                    [
                        'fact' => 'event_data.new_budget',
                        'operator' => 'greaterThan',
                        'value' => 10000,
                    ],
                ],
                'actions' => [
                    'reject' => true,
                    'message' => 'Budget exceeds maximum allowed limit of $10,000',
                ],
                'priority' => 10,
                'is_active' => true,
                'effective_from' => null,
                'effective_until' => null,
                'created_by' => null,
            ],
            [
                'name' => 'Stock Quantity Validation',
                'version' => '1.0.0',
                'entity_type' => 'inventory',
                'event_type' => 'stock_used',
                'conditions' => [
                    [
                        'fact' => 'event_data.quantity_used',
                        'operator' => 'lessThan',
                        'value' => 0,
                    ],
                ],
                'actions' => [
                    'reject' => true,
                    'message' => 'Stock quantity cannot be negative',
                ],
                'priority' => 10,
                'is_active' => true,
                'effective_from' => null,
                'effective_until' => null,
                'created_by' => null,
            ],

            // Business Rules (System Configuration)
            [
                'name' => 'Payment Structure Configuration',
                'version' => '1.0.0',
                'entity_type' => null, // System-wide rule
                'event_type' => null, // System-wide rule
                'conditions' => [],
                'actions' => [
                    'payment_structure' => [
                        'onsite_percentage' => 35,
                        'later_percentage' => 65,
                        'discount_percentage' => 15,
                    ],
                ],
                'priority' => 1,
                'is_active' => true,
                'effective_from' => null,
                'effective_until' => null,
                'created_by' => null,
            ],
            [
                'name' => 'Approval Hierarchy Configuration',
                'version' => '1.0.0',
                'entity_type' => null, // System-wide rule
                'event_type' => null, // System-wide rule
                'conditions' => [],
                'actions' => [
                    'approval_hierarchy' => [
                        'worker',
                        'supervisor',
                        'manager',
                    ],
                ],
                'priority' => 1,
                'is_active' => true,
                'effective_from' => null,
                'effective_until' => null,
                'created_by' => null,
            ],
            [
                'name' => 'Conflict Resolution Configuration',
                'version' => '1.0.0',
                'entity_type' => null, // System-wide rule
                'event_type' => null, // System-wide rule
                'conditions' => [],
                'actions' => [
                    'conflict_resolution' => [
                        'default_strategy' => 'prioritize_supervisor',
                        'tie_breaker' => 'most_recent',
                        'max_conflict_age_days' => 30,
                    ],
                ],
                'priority' => 1,
                'is_active' => true,
                'effective_from' => null,
                'effective_until' => null,
                'created_by' => null,
            ],
            [
                'name' => 'Event Generation Configuration',
                'version' => '1.0.0',
                'entity_type' => null, // System-wide rule
                'event_type' => null, // System-wide rule
                'conditions' => [],
                'actions' => [
                    'event_generation' => [
                        'require_entity_context' => true,
                        'validate_business_rules' => true,
                        'max_offline_days' => 30,
                        'max_events_per_sync' => 1000,
                        'duplicate_detection_window' => '24 hours',
                        'validation_timeout' => '30 seconds',
                    ],
                ],
                'priority' => 1,
                'is_active' => true,
                'effective_from' => null,
                'effective_until' => null,
                'created_by' => null,
            ],
            [
                'name' => 'Device Handover Configuration',
                'version' => '1.0.0',
                'entity_type' => null, // System-wide rule
                'event_type' => null, // System-wide rule
                'conditions' => [],
                'actions' => [
                    'device_handover' => [
                        'data_isolation' => true,
                        'session_continuation' => true,
                        'conflict_detection' => true,
                    ],
                ],
                'priority' => 1,
                'is_active' => true,
                'effective_from' => null,
                'effective_until' => null,
                'created_by' => null,
            ],
            [
                'name' => 'Offline Limits Configuration',
                'version' => '1.0.0',
                'entity_type' => null, // System-wide rule
                'event_type' => null, // System-wide rule
                'conditions' => [],
                'actions' => [
                    'offline_limits' => [
                        'max_offline_days' => 30,
                        'sync_retry_attempts' => 3,
                        'bandwidth_optimization' => true,
                    ],
                ],
                'priority' => 1,
                'is_active' => true,
                'effective_from' => null,
                'effective_until' => null,
                'created_by' => null,
            ],
        ];

        foreach ($rules as $rule) {
            Rule::create($rule);
        }
    }
}
