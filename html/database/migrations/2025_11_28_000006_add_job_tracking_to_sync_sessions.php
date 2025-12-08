<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_sessions', function (Blueprint $table) {
            // Job tracking for async processing
            $table->string('job_id')->nullable();
            $table->integer('estimated_events')->default(0);

            // Performance metrics
            $table->integer('actual_events_processed')->default(0);
            $table->decimal('throughput_eps', 8, 2)->nullable();
            $table->decimal('processing_duration_seconds', 10, 3)->nullable();

            // Conflict and error tracking
            $table->integer('conflicts_detected')->default(0);
            $table->integer('duplicates_found')->default(0);
            $table->integer('errors_encountered')->default(0);

            // Indexes for performance
            $table->index(['job_id'], 'idx_sync_sessions_job_id');
            $table->index(['status', 'created_at'], 'idx_sync_sessions_status_created');
            $table->index(['device_id', 'status'], 'idx_sync_sessions_device_status');
        });
    }

    public function down(): void
    {
        Schema::table('sync_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_sync_sessions_job_id');
            $table->dropIndex('idx_sync_sessions_status_created');
            $table->dropIndex('idx_sync_sessions_device_status');

            $table->dropColumn([
                'job_id',
                'estimated_events',
                'actual_events_processed',
                'throughput_eps',
                'processing_duration_seconds',
                'conflicts_detected',
                'duplicates_found',
                'errors_encountered',
            ]);
        });
    }
};
