<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rule_id');
            $table->string('version', 20);
            $table->jsonb('rule_definition');
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('rule_id')->references('id')->on('rules')->onDelete('cascade');
            $table->index(['rule_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_versions');
    }
};
