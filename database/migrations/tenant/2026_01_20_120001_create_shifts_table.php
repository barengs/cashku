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
        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            // Assuming users table is central but synced or referenced. 
            // Since this is tenant migration, and users are in tenant DB (as per previous context), we use foreignId to users.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            
            $table->decimal('starting_cash', 15, 2)->default(0);
            $table->decimal('ending_cash', 15, 2)->nullable(); // Calculated by system
            $table->decimal('actual_cash', 15, 2)->nullable(); // Input by cashier
            
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
