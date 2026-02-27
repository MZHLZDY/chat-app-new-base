<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // pemilik board
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#5e6ad2'); // warna board (hex)
            $table->string('icon')->default('📋');          // emoji icon board
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_boards');
    }
};