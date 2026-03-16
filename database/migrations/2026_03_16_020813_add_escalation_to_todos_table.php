<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->tinyInteger('overdue_escalation_level')->default(0)->after('reminder_sent');
            $table->timestamp('last_escalated_at')->nullable()->after('overdue_escalation_level');
            $table->boolean('owner_notified')->default(false)->after('last_escalated_at');
        });

        Schema::table('task_boards', function (Blueprint $table) {
            $table->boolean('auto_escalate')->default(true)->after('icon');
        });

        Schema::create('todo_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropColumn(['overdue_escalation_level', 'last_escalated_at', 'owner_notified']);
        });
        Schema::table('task_boards', function (Blueprint $table) {
            $table->dropColumn('auto_escalate');
        });
        Schema::dropIfExists('todo_activity_logs');
    }
};