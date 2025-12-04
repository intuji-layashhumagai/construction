<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WorkerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $workers = [
            [
                'id' => (string) Str::uuid(),
                'employee_id' => 'EMP001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone' => '1234567890',
                'role' => 'carpenter',
                'status' => 'active',
                'supervisor_id' => null,
                'password' => Hash::make('password123'),
                'pin_code' => '1234',
                'pin_required' => true,
                'public_key' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'has_emergency_access' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'employee_id' => 'EMP002',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@example.com',
                'phone' => '0987654321',
                'role' => 'foreman',
                'status' => 'active',
                'supervisor_id' => null,
                'password' => Hash::make('password123'),
                'pin_code' => '5678',
                'pin_required' => true,
                'public_key' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'has_emergency_access' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'employee_id' => null,
                'first_name' => 'Alice',
                'last_name' => 'Johnson',
                'email' => 'alice.johnson@example.com',
                'phone' => null,
                'role' => 'supervisor',
                'status' => 'active',
                'supervisor_id' => null,
                'password' => Hash::make('password123'),
                'pin_code' => '91011',
                'pin_required' => false,
                'public_key' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'has_emergency_access' => false,
            ],

        ];

        DB::table('workers')->insert($workers);
    }
}
