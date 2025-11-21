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
        Schema::create('worker_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('worker_id')->references('id')->on('workers')->onDelete('cascade');
            $table->foreignUuid('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->timestamp('login_at');
            $table->jsonb('last_vc_sent')->nullable();
            $table->timestamp('logout_at')->nullable();
            $table->string('session_status')->default('active');
            $table->integer('events_created')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worker_devices');
    }
};
