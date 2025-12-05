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
        Schema::create('sync_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_id')->references('id')->on('devices');
            $table->foreignUuid('worker_id')->references('id')->on('workers');

            $table->string('direction')->index();
            $table->string('status')->index(); // Maps to SyncPhase
            $table->jsonb('vector_clock_state')->nullable();

            // Resume and Progress Tracking
            $table->jsonb('last_checkpoint')->nullable();
            $table->bigInteger('bytes_transferred')->default(0);

            $table->timestamp('start_time');
            $table->timestamp('last_activity_time')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_sessions');
    }
};
