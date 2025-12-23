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
        Schema::create('personal_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('callee_id')->constrained('users')->onDelete('cascade');
            $table->enum('call_type', ['voice', 'video']);
            $table->enum('status', ['ringing', 'ongoing', 'ended', 'missed', 'rejected', 'cancelled'])->default('ringing');
            $table->string('channel_name')->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable()->comment('Duration in seconds');
            $table->foreignId('ended_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // indexes untuk performance
            $table->index(['caller_id', 'callee_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_calls');
    }
};
