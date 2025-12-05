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
                'description' => 'Reject budget updates that exceed $10,000',
                'entity_type' => 'project',
                'event_type' => 'budget_updated',
                'conditions' => [
                    [
                        'fact' => 'event_data.new_budget',
                        'operator' => 'greaterThan',
                        'value' => 10000
                    ]
                ],
                'actions' => [
                    'reject' => true,
                    'message' => 'Budget exceeds maximum allowed limit of $10,000'
                ],
                'priority' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Stock Quantity Validation',
                'description' => 'Reject stock updates that result in negative quantities',
                'entity_type' => 'inventory',
                'event_type' => 'stock_used',
                'conditions' => [
                    [
                        'fact' => 'event_data.quantity_used',
                        'operator' => 'lessThan',
                        'value' => 0
                    ]
                ],
                'actions' => [
                    'reject' => true,
                    'message' => 'Stock quantity cannot be negative'
                ],
                'priority' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($rules as $rule) {
            Rule::create($rule);
        }
    }
}