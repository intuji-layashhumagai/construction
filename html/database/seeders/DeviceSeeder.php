<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeviceSeeder extends Seeder
{
    public function run()
    {
        // Seeding data for devices
        $devices = [
            [
                'id' => Str::uuid(),
                'name' => 'Device A',
                'model' => 'Model X1',
                'os_version' => 'Android 11',
                'status' => 'active',
                'storage_available' => 128, // in GB
                'network_type' => 'Wi-Fi',
                'public_key' => Str::random(64), // Random string as public key
                'certificate_expires_at' => now()->addYear(), // Certificate expires in 1 year
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null, // Soft delete not applied initially
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Device B',
                'model' => 'Model Y2',
                'os_version' => 'iOS 14',
                'status' => 'active',
                'storage_available' => 64,
                'network_type' => '4G',
                'public_key' => Str::random(64),
                'certificate_expires_at' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Device C',
                'model' => 'Model Z3',
                'os_version' => 'Windows 10',
                'status' => 'inactive', // Example of an inactive device
                'storage_available' => 512,
                'network_type' => 'Ethernet',
                'public_key' => Str::random(64),
                'certificate_expires_at' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
            // Add more devices as needed...
        ];

        // Inserting data into the devices table
        DB::table('devices')->insert($devices);
    }
}
