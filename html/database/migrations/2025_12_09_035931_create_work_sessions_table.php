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
        Schema::create('work_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('worker_id')->constrained('workers')->onDelete('cascade');
            $table->foreignUuid('current_device_id')->constrained('devices')->onDelete('cascade');
            $table->string('status')->default('active'); // active, ended, merged
            $table->decimal('total_hours', 5, 2)->default(0);
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->timestamps();

            $table->index(['worker_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_sessions');
    }
};
