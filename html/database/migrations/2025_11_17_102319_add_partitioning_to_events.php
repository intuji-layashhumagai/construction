<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('events');

        DB::statement('
            CREATE TABLE events (
                id UUID NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                device_id UUID NOT NULL,
                worker_id UUID NOT NULL,
                event_data JSONB NOT NULL,
                sequence_number BIGINT NOT NULL,
                server_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                PRIMARY KEY (id, server_created_at)
            ) PARTITION BY RANGE (server_created_at)
        ');

        $this->createPartition(date('Y-m-01'));
    }

    /**
     * Creates a new partition for the events table.
     * The partition is named events_{Y_m} and spans from the given month start to the next month.
     * If the partition already exists, it is skipped.
     *
     * @param  string  $monthStart  The start of the month for the partition in the format Y-m-d.
     */
    private function createPartition(string $monthStart)
    {
        $partitionName = 'events_'.date('Y_m', strtotime($monthStart));
        $nextMonth = date('Y-m-d', strtotime($monthStart.' +1 month'));

        DB::statement("
            CREATE TABLE IF NOT EXISTS {$partitionName} 
            PARTITION OF events 
            FOR VALUES FROM ('{$monthStart}') TO ('{$nextMonth}')
        ");

        echo "Created partition: {$partitionName}\n";
    }

    // todo: Auto manage the partation before partation is required

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        // Drop all partitions
        $partitions = DB::select("
            SELECT tablename 
            FROM pg_tables 
            WHERE tablename LIKE 'events_%'
        ");

        foreach ($partitions as $partition) {
            DB::statement("DROP TABLE IF EXISTS {$partition->tablename}");
        }

        // drop the main events table
        Schema::dropIfExists('events');
    }
};
