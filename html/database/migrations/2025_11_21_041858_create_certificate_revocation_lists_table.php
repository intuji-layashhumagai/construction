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
        Schema::create('certificate_revocation_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('version')->unique();
            $table->json('revoked_serials'); // Array of revoked certificate serials
            $table->timestamp('issued_at');
            $table->timestamp('next_update'); // When next CRL will be issued
            $table->text('signature'); // Digital signature for CRL integrity
            $table->timestamps();

            $table->index('version');
            $table->index('issued_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_revocation_lists');
    }
};
