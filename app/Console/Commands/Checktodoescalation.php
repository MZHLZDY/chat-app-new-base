<?php

namespace App\Console\Commands;

use App\Models\Todo;
use App\Models\TodoActivityLog;
use App\Notifications\TodoDeadlineNotification;
use App\Notifications\TodoEscalationNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckTodoEscalation extends Command
{
    protected $signature = 'todo:check-escalation';
    protected $description = 'Cek deadline todo & jalankan eskalasi bertahap untuk tugas yang terlambat';

    public function handle(): int
    {
        $now = Carbon::now();
        $this->info("[{$now}] Menjalankan pengecekan eskalasi todo...");

        // ── 1. REMINDER — deadline dalam 24 jam & belum terkirim ─────────────
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Todo> $reminders */
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Todo> $reminders */
        $reminders = Todo::whereNotNull('due_date')
            ->where('status', '!=', 'done')
            ->where('reminder_sent', false)
            ->where('due_date', '>', $now)
            ->where('due_date', '<=', $now->copy()->addHours(24))
            ->with(['assignees'])
            ->get();

        foreach ($reminders as $todo) { /** @var \App\Models\Todo $todo */
            foreach ($todo->assignees as $user) {
                try {
                    $user->notify(new TodoDeadlineNotification($todo, 'reminder'));
                } catch (\Exception $e) {
                    logger()->warning("Reminder gagal todo#{$todo->id}: " . $e->getMessage());
                }
            }
            $todo->update(['reminder_sent' => true]);
            $this->logActivity($todo->id, null, 'reminder_sent');
            $this->line("  Reminder → #{$todo->id}: {$todo->title}");
        }

        // ── 2. ESKALASI — todo overdue & status bukan done ───────────────────
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Todo> $overdueTodos */
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Todo> $overdueTodos */
        $overdueTodos = Todo::whereNotNull('due_date')
            ->where('status', '!=', 'done')
            ->where('due_date', '<', $now)
            ->with(['assignees', 'board.owner', 'board.members'])
            ->get();

        foreach ($overdueTodos as $todo) { /** @var \App\Models\Todo $todo */
            // Lewati board yang auto_escalate = false
            if ($todo->board && !$todo->board->auto_escalate)
                continue;

            $daysLate = (int) $now->diffInDays($todo->due_date);
            $level = $todo->overdue_escalation_level;
            $board = $todo->board;
            $hoursSinceLast = $todo->last_escalated_at
                ? $now->diffInHours($todo->last_escalated_at)
                : 9999;

            // Jangan eskalasi terlalu sering (minimal 20 jam sekali)
            if ($hoursSinceLast < 20)
                continue;

            // Level 0 → 1 : Hari ke-0, notif assignee
            if ($level === 0) {
                foreach ($todo->assignees as $user) {
                    try {
                        $user->notify(new TodoEscalationNotification($todo, 'assignee_overdue'));
                    } catch (\Exception $e) {
                        logger()->warning($e->getMessage());
                    }
                }
                $todo->update([
                    'overdue_escalation_level' => 1,
                    'last_escalated_at' => $now,
                ]);
                $this->logActivity($todo->id, null, 'escalated_level_1', ['days_late' => $daysLate]);
                $this->line("  L1 → #{$todo->id}: {$todo->title}");
            }

            // Level 1 → 2 : Hari ke-1, notif owner board
            elseif ($level === 1 && $daysLate >= 1) {
                if ($board?->owner && !$todo->owner_notified) {
                    try {
                        $board->owner->notify(new TodoEscalationNotification($todo, 'owner_alert'));
                    } catch (\Exception $e) {
                        logger()->warning($e->getMessage());
                    }
                }
                $todo->update([
                    'overdue_escalation_level' => 2,
                    'last_escalated_at' => $now,
                    'owner_notified' => true,
                ]);
                $this->logActivity($todo->id, null, 'escalated_level_2', ['days_late' => $daysLate]);
                $this->line("  L2 → #{$todo->id}: {$todo->title}");
            }

            // Level 2 → 3 : Hari ke-2, tandai perlu perhatian
            elseif ($level === 2 && $daysLate >= 2) {
                if ($board?->owner) {
                    try {
                        $board->owner->notify(new TodoEscalationNotification($todo, 'needs_attention'));
                    } catch (\Exception $e) {
                        logger()->warning($e->getMessage());
                    }
                }
                $todo->update([
                    'overdue_escalation_level' => 3,
                    'last_escalated_at' => $now,
                ]);
                $this->logActivity($todo->id, null, 'escalated_level_3', ['days_late' => $daysLate]);
                $this->line("  L3 → #{$todo->id}: {$todo->title}");
            }

            // Level 3 → 4 : Hari ke-3, notif SEMUA member board
            elseif ($level === 3 && $daysLate >= 3) {
                $allMembers = $board?->members ?? collect();
                foreach ($allMembers as $member) {
                    try {
                        $member->notify(new TodoEscalationNotification($todo, 'final_alert'));
                    } catch (\Exception $e) {
                        logger()->warning($e->getMessage());
                    }
                }
                $todo->update([
                    'overdue_escalation_level' => 4,
                    'last_escalated_at' => $now,
                ]);
                $this->logActivity($todo->id, null, 'escalated_level_4', ['days_late' => $daysLate]);
                $this->line("  L4 → #{$todo->id}: {$todo->title}");
            }
        }

        $this->info("Selesai. Reminder: {$reminders->count()}, Overdue: {$overdueTodos->count()}");
        return self::SUCCESS;
    }

    private function logActivity(int $todoId, ?int $userId, string $action, array $meta = []): void
    {
        try {
            TodoActivityLog::create([
                'todo_id' => $todoId,
                'user_id' => $userId,
                'action' => $action,
                'meta' => $meta ?: null,
            ]);
        } catch (\Exception $e) {
            logger()->warning("Activity log gagal: " . $e->getMessage());
        }
    }
}