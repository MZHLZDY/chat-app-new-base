<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_board_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_board_id')->constrained('task_boards')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role')->default('member'); // owner | member
            $table->timestamps();

            $table->unique(['task_board_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_board_user');
    }
};