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
        Schema::table('events', function (Blueprint $table) {
            $table->string('authority_level')->nullable()->index();
            $table->text('modification_justification')->nullable();
            $table->jsonb('previous_values')->nullable();
            $table->string('approval_status')->nullable()->index();
            $table->uuid('workflow_instance_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['authority_level']);
            $table->dropIndex(['approval_status']);
            $table->dropIndex(['workflow_instance_id']);

            $table->dropColumn([
                'authority_level',
                'modification_justification',
                'previous_values',
                'approval_status',
                'workflow_instance_id',
            ]);
        });
    }
};
