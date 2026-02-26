<?php

namespace App\Console\Commands;

use App\Models\Todo;
use App\Notifications\TodoDeadlineNotification;
use Illuminate\Console\Command;

class SendDeadlineReminders extends Command
{
    protected $signature   = 'todos:send-reminders';
    protected $description = 'Kirim notifikasi pengingat deadline tugas';

    public function handle(): void
    {
        $now = now();

        // ── 1. Reminder 1 jam sebelum deadline ────────────────────────────────
        /** @var \Illuminate\Database\Eloquent\Collection<int, Todo> $upcoming */
        $upcoming = Todo::whereNotNull('due_date')
            ->where('status', '!=', 'done')
            ->where('reminder_sent', false)
            ->whereBetween('due_date', [$now, $now->copy()->addHour()])
            ->with(['owner', 'assignees'])
            ->get();

        foreach ($upcoming as $todo) {
            /** @var Todo $todo */
            $recipients = collect([$todo->owner])->merge($todo->assignees)->unique('id')->filter();
            foreach ($recipients as $user) {
                try {
                    $user->notify(new TodoDeadlineNotification($todo, 'reminder'));
                } catch (\Exception $e) {
                    $this->warn("Gagal notif user {$user->id}: " . $e->getMessage());
                }
            }
            $todo->update(['reminder_sent' => true]);
            $this->info("Reminder dikirim: [{$todo->id}] {$todo->title}");
        }

        // ── 2. Overdue notification (terlambat, baru terlewat dalam 30 menit) ─
        /** @var \Illuminate\Database\Eloquent\Collection<int, Todo> $overdue */
        $overdue = Todo::whereNotNull('due_date')
            ->where('status', '!=', 'done')
            ->whereBetween('due_date', [$now->copy()->subMinutes(30), $now])
            ->with(['owner', 'assignees'])
            ->get();

        foreach ($overdue as $todo) {
            /** @var Todo $todo */
            $recipients = collect([$todo->owner])->merge($todo->assignees)->unique('id')->filter();
            foreach ($recipients as $user) {
                try {
                    $user->notify(new TodoDeadlineNotification($todo, 'overdue'));
                } catch (\Exception $e) {
                    $this->warn("Gagal notif overdue user {$user->id}: " . $e->getMessage());
                }
            }
            $this->info("Overdue dikirim: [{$todo->id}] {$todo->title}");
        }

        $this->info('Selesai: ' . $upcoming->count() . ' reminder, ' . $overdue->count() . ' overdue.');
    }
}