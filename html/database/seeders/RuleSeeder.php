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
        ];

        foreach ($rules as $rule) {
            Rule::create($rule);
        }
    }
}
