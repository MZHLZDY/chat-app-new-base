<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            if (!Schema::hasColumn('todos', 'due_date')) {
                $table->dateTime('due_date')->nullable();
            }
            if (!Schema::hasColumn('todos', 'reminder_sent')) {
                $table->boolean('reminder_sent')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropColumnIfExists('due_date');
            $table->dropColumnIfExists('reminder_sent');
        });
    }
};