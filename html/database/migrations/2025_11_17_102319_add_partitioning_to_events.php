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

        $this->createPartitionManagement();
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

    private function createPartitionManagement()
    {
        // Function to automatically create partitions
        DB::statement("
            CREATE OR REPLACE FUNCTION create_events_partition()
            RETURNS trigger AS $$
            DECLARE
                partition_date date;
                partition_name text;
            BEGIN
                partition_date := date_trunc('month', NEW.server_created_at);
                partition_name := 'events_' || to_char(partition_date, 'YYYY_MM');
                
                IF NOT EXISTS (
                    SELECT 1 FROM pg_tables 
                    WHERE tablename = partition_name
                ) THEN
                    EXECUTE format(
                        'CREATE TABLE %I PARTITION OF events FOR VALUES FROM (%L) TO (%L)',
                        partition_name,
                        partition_date,
                        partition_date + interval '1 month'
                    );
                    
                    -- Create indexes on new partition
                    EXECUTE format('
                        CREATE INDEX %I ON %I (entity_type, entity_id)
                    ', partition_name || '_entity_idx', partition_name);
                    
                    EXECUTE format('
                        CREATE INDEX %I ON %I (device_id, sequence_number)  
                    ', partition_name || '_device_idx', partition_name);
                    
                    EXECUTE format('
                        CREATE INDEX %I ON %I (server_created_at)
                    ', partition_name || '_timestamp_idx', partition_name);
                END IF;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Create trigger
        DB::statement("
            CREATE TRIGGER ensure_events_partition
            BEFORE INSERT ON events
            FOR EACH ROW EXECUTE FUNCTION create_events_partition();
        ");

        // Create future partitions
        $this->createFuturePartitions();
    }

        private function createFuturePartitions()
    {
        for ($i = 1; $i <= 3; $i++) {
            $monthStart = now()->addMonths($i)->format('Y-m-01');
            $this->createPartition($monthStart);
        }
    }

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
