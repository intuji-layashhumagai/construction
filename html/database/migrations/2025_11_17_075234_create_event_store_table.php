<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_store', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('event_type', 100);
            $table->uuid('device_id');
            $table->uuid('worker_id');

            $table->jsonb('event_data');

            $table->bigInteger('sequence_number');

            $table->timestamps();

            // Basic indexes
            $table->index(['worker_id']);
            $table->index(['device_id', 'sequence_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_store');
    }
};
