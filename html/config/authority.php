<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authority Hierarchy Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines the authority levels and their hierarchy values
    | for the construction management system. Higher values indicate higher
    | authority levels.
    |
    | You can add, remove, or modify authority levels as needed for your
    | organization's structure.
    |
    */

    'hierarchy' => [
        'worker' => 1,
        'supervisor' => 2,
        'manager' => 3,
        'director' => 4,
        'executive' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Modification Limits
    |--------------------------------------------------------------------------
    |
    | Define the limits for each authority level when modifying timesheets
    | or other data. Set max_change to null for unlimited changes.
    |
    */

    'modification_limits' => [
        'worker' => [
            'max_change' => 0,
            'requires_justification' => false,
        ],
        'supervisor' => [
            'max_change' => 2.0,
            'requires_justification' => true,
        ],
        'manager' => [
            'max_change' => null, // Unlimited
            'requires_justification' => true,
        ],
        'director' => [
            'max_change' => null, // Unlimited
            'requires_justification' => false,
        ],
        'executive' => [
            'max_change' => null, // Unlimited
            'requires_justification' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Authority Level
    |--------------------------------------------------------------------------
    |
    | The default authority level assigned to new users or when no level
    | is specified.
    |
    */

    'default_level' => 'worker',

    /*
    |--------------------------------------------------------------------------
    | Emergency Override Authorities
    |--------------------------------------------------------------------------
    |
    | Authority levels that can bypass normal validation rules in emergency
    | situations.
    |
    */

    'emergency_override' => ['manager', 'director', 'executive'],
];
