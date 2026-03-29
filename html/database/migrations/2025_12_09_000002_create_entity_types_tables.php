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
        Schema::create('event_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type', 50)->unique(); // From EventType enum
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('entity_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Entity-event type relationship table that defines which events are allowed for which entity types
        Schema::create('entity_event_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entity_type_id')->references('id')->on('entity_types')->onDelete('cascade');
            $table->foreignUuid('event_type_id')->references('id')->on('event_types')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_initial_event')->default(false); // Creation events
            $table->boolean('requires_approval')->default(false); // Needs supervisor approval
            $table->integer('priority')->default(3); // 1=Critical, 2=High, 3=Medium, 4=Low, 5=Background
            $table->timestamps();

            $table->unique(['entity_type_id', 'event_type_id']);
            $table->index(['entity_type_id', 'is_initial_event']);
            $table->index(['entity_type_id', 'requires_approval']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_event_types');
        Schema::dropIfExists('entity_types');
        Schema::dropIfExists('event_types');
    }
};
