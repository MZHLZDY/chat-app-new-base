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
        Schema::create('call_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('personal_calls')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('event_type', ['initiated', 'ringing', 'answered', 'rejected', 'cancelled', 'ended', 'missed']);
            $table->json('metadata')->nullable()->comment('Additional Event Data');
            $table->timestamp('created_at');

            // Indexes
            $table->index('call_id');
            $table->index('user_id');
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_events');
    }
};
