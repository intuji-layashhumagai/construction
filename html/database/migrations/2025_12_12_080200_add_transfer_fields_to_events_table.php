<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Transfer-related fields for inventory transfers
            $table->uuid('transfer_id')->nullable()->index();
            $table->text('sender_signature')->nullable();
            $table->text('receiver_signature')->nullable();
            $table->string('qr_code_hash', 128)->nullable()->index();
            $table->integer('chain_position')->nullable();
            $table->string('transfer_status', 50)->nullable();
            $table->string('chain_validation_status', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'transfer_id',
                'sender_signature',
                'receiver_signature',
                'qr_code_hash',
                'chain_position',
                'transfer_status',
                'chain_validation_status',
            ]);
        });
    }
};
