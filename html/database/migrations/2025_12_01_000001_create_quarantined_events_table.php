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
        Schema::create('quarantined_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id')->nullable();
            $table->foreignUuid('device_id')->references('id')->on('devices');
            $table->foreignUuid('worker_id')->references('id')->on('workers');

            // Event data storage
            $table->jsonb('event_data');
            $table->jsonb('validation_errors');
            $table->jsonb('vector_clock')->nullable();

            // Timestamps
            $table->timestamp('device_timestamp')->nullable(); // Untrusted device time
            $table->timestamp('server_received_at')->index(); // Trusted server time
            $table->timestamp('quarantined_at')->useCurrent();

            // Status and review
            $table->string('status')->default('pending_review');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->references('id')->on('users');
            $table->timestamp('reviewed_at')->nullable();

            // Migration tracking
            $table->jsonb('migration_applied')->nullable(); // What migration was applied
            $table->uuid('migrated_event_id')->nullable(); // If event was successfully migrated

            $table->index(['status', 'device_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quarantined_events');
    }
};
