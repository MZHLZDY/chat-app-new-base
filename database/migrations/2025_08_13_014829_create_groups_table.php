<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade'); 
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('receiver_id')->constrained()->onDelete('cascade');
            $table->foreignId('receiver_id')->nullable()->change(); 
        });
    }
};
