<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            if (Schema::hasColumn('todos', 'is_completed')) {
                $table->dropColumn('is_completed');
            }
            if (!Schema::hasColumn('todos', 'status')) {
                $table->string('status')->default('todo');
            }
            if (!Schema::hasColumn('todos', 'priority')) {
                $table->string('priority')->default('medium');
            }
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
            if (Schema::hasColumn('todos', 'priority'))      $table->dropColumn('priority');
            if (Schema::hasColumn('todos', 'status'))        $table->dropColumn('status');
            if (Schema::hasColumn('todos', 'due_date'))      $table->dropColumn('due_date');
            if (Schema::hasColumn('todos', 'reminder_sent')) $table->dropColumn('reminder_sent');
            if (!Schema::hasColumn('todos', 'is_completed')) $table->boolean('is_completed')->default(false);
        });
    }
};