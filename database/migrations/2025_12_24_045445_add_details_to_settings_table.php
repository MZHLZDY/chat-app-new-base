<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('settings', function (Blueprint $table) {
        // Tambahkan kolom yang kurang
        $table->string('telepon')->nullable()->after('email');
        $table->text('alamat')->nullable()->after('telepon');
        $table->string('pemerintah')->nullable()->after('alamat');
        $table->string('dinas')->nullable()->after('pemerintah');
    });
}

public function down()
{
    Schema::table('settings', function (Blueprint $table) {
        $table->dropColumn(['telepon', 'alamat', 'pemerintah', 'dinas']);
    });
}
};
