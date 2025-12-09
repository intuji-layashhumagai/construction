<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('version', 20)->default('1.0.0');
            $table->string('entity_type', 50)->nullable();
            $table->string('event_type', 50)->nullable();
            $table->jsonb('conditions');
            $table->jsonb('actions');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_until')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'event_type', 'is_active'], 'idx_rules_entity_event');
            $table->index(['priority', 'is_active'], 'idx_rules_priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rules');
    }
};
