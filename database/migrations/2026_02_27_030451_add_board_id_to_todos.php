<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Tambah kolom board_id jika belum ada
        if (!Schema::hasColumn('todos', 'board_id')) {
            Schema::table('todos', function (Blueprint $table) {
                $table->foreignId('board_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('task_boards')
                    ->onDelete('cascade');
            });
        }

        // 2. Buat board default "Tugas Utama" untuk setiap user yang punya todo
        $userIds = DB::table('todos')->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            // Buat board default untuk user ini
            $boardId = DB::table('task_boards')->insertGetId([
                'user_id'     => $userId,
                'name'        => 'Tugas Utama',
                'description' => 'Board default hasil migrasi',
                'color'       => '#5e6ad2',
                'icon'        => '📋',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // Daftarkan user sebagai owner di board ini
            DB::table('task_board_user')->insert([
                'task_board_id' => $boardId,
                'user_id'       => $userId,
                'role'          => 'owner',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Pindahkan semua todo milik user ini ke board default
            DB::table('todos')
                ->where('user_id', $userId)
                ->whereNull('board_id')
                ->update(['board_id' => $boardId]);
        }

        // 3. Setelah data dimigrasikan, buat board_id NOT NULL
        Schema::table('todos', function (Blueprint $table) {
            $table->foreignId('board_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropForeign(['board_id']);
            $table->dropColumn('board_id');
        });
    }
};