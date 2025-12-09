<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // 1. Tabel Groups
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade'); // Pembuat grup
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        // 2. Tabel Pivot (Anggota Group)
        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // 3. Update Tabel Chat Messages (Tambah kolom group_id)
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('receiver_id')->constrained()->onDelete('cascade');
            // Ubah receiver_id jadi nullable karena kalau group chat, receiver-nya kosong
            $table->foreignId('receiver_id')->nullable()->change(); 
        });
    }
};
