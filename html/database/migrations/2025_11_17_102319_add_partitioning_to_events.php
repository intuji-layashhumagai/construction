<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('event_type', 100);
            $table->uuid('device_id');
            $table->uuid('worker_id');

            $table->jsonb('event_data');

            $table->bigInteger('sequence_number');
            $table->timestamp('server_created_at')->useCurrent();

            $table->timestamps();

            // Basic indexes
            $table->index(['worker_id']);
            $table->index(['device_id', 'sequence_number']);
        });

        $this->createPartition(date('Y-m-01'));
    }

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            //
        });
    }
};
