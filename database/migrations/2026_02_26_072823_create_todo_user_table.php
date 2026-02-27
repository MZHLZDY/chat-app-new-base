<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('todo_user');

        Schema::create('todo_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role')->default('member'); // owner | member
            $table->timestamps();

            $table->unique(['todo_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_user');
    }
};