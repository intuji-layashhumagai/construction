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
        Schema::create('workers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('employee_id')->unique()->nullable()->index(); // Company employee ID
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('role'); // carpenter, foreman, supervisor, etc.
            $table->string('status')->default('active');
            // $table->uuid('supervisor_id')->nullable(); // Reports to
            $table->string('password')->nullable(); // For online auth
            $table->string('pin_code')->nullable(); // For offline device login
            $table->boolean('pin_required')->default(true);
            $table->text('public_key')->nullable(); // For certificate authentication
            $table->boolean('has_emergency_access')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'role']);
            $table->index(['last_name', 'first_name']);
        });

        Schema::table('workers', static function (Blueprint $table): void {
            $table->foreignUuid('supervisor_id')->nullable()->references('id')
                ->on('workers')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
