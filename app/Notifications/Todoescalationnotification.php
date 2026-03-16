<?php

namespace App\Notifications;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TodoEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Todo   $todo,
        public readonly string $escalationType,
        // 'assignee_overdue' | 'owner_alert' | 'needs_attention' | 'final_alert' | 'reassigned'
        public readonly ?User  $reassignedTo = null,
        public readonly ?User  $reassignedBy = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        $daysLate = $this->todo->due_date
            ? max(0, (int) now()->diffInDays($this->todo->due_date, false) * -1)
            : 0;

        return match ($this->escalationType) {
            'assignee_overdue' => [
                'type'     => 'todo_escalation',
                'level'    => 1,
                'icon'     => '🔴',
                'title'    => 'Tugasmu Melewati Deadline',
                'message'  => '"' . $this->todo->title . "\" sudah {$daysLate} hari terlambat. Segera selesaikan!",
                'todo_id'  => $this->todo->id,
                'board_id' => $this->todo->board_id,
                'action'   => 'view_todo',
            ],
            'owner_alert' => [
                'type'     => 'todo_escalation',
                'level'    => 2,
                'icon'     => '⚠️',
                'title'    => 'Tugas Belum Dikerjakan',
                'message'  => '"' . $this->todo->title . "\" sudah {$daysLate} hari terlambat. Pertimbangkan untuk reassign.",
                'todo_id'  => $this->todo->id,
                'board_id' => $this->todo->board_id,
                'action'   => 'reassign',
            ],
            'needs_attention' => [
                'type'     => 'todo_escalation',
                'level'    => 3,
                'icon'     => '🚨',
                'title'    => 'Tugas Perlu Perhatian Segera',
                'message'  => '"' . $this->todo->title . "\" sudah {$daysLate} hari terlambat dan ditandai perlu perhatian.",
                'todo_id'  => $this->todo->id,
                'board_id' => $this->todo->board_id,
                'action'   => 'view_board',
            ],
            'final_alert' => [
                'type'     => 'todo_escalation',
                'level'    => 4,
                'icon'     => '🆘',
                'title'    => 'Tugas Sangat Terlambat!',
                'message'  => '"' . $this->todo->title . "\" sudah {$daysLate} hari terlambat. Semua member board telah diberitahu.",
                'todo_id'  => $this->todo->id,
                'board_id' => $this->todo->board_id,
                'action'   => 'view_board',
            ],
            'reassigned' => [
                'type'     => 'todo_escalation',
                'level'    => 0,
                'icon'     => '🔄',
                'title'    => 'Tugas Dialihkan',
                'message'  => '"' . $this->todo->title . '" dialihkan ke ' . ($this->reassignedTo?->name ?? '-') . ' oleh ' . ($this->reassignedBy?->name ?? 'owner'),
                'todo_id'  => $this->todo->id,
                'board_id' => $this->todo->board_id,
                'action'   => 'view_todo',
            ],
            default => [
                'type'     => 'todo_escalation',
                'level'    => 0,
                'icon'     => '📋',
                'title'    => 'Update Tugas',
                'message'  => $this->todo->title,
                'todo_id'  => $this->todo->id,
                'board_id' => $this->todo->board_id,
            ],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName  = config('app.name', 'App');
        $daysLate = $this->todo->due_date
            ? max(0, (int) now()->diffInDays($this->todo->due_date, false) * -1)
            : 0;

        return match ($this->escalationType) {
            'assignee_overdue' => (new MailMessage)
                ->subject("[{$appName}] 🔴 Tugasmu terlambat: {$this->todo->title}")
                ->greeting("Halo {$notifiable->name}!")
                ->line("Tugas yang kamu emban sudah **{$daysLate} hari** melewati deadline.")
                ->line("**{$this->todo->title}**")
                ->line("Deadline: " . $this->todo->due_date?->format('d F Y, H:i'))
                ->action('Kerjakan Sekarang', url('/dashboard/todo-list'))
                ->line("Jika ada kendala, segera diskusikan dengan owner board."),

            'owner_alert' => (new MailMessage)
                ->subject("[{$appName}] ⚠️ Tugas belum dikerjakan: {$this->todo->title}")
                ->greeting("Halo {$notifiable->name}!")
                ->line("Sebagai owner board, tugas berikut belum selesai setelah **{$daysLate} hari** melewati deadline.")
                ->line("**{$this->todo->title}**")
                ->line("Assignee: " . $this->todo->assignees->pluck('name')->join(', '))
                ->action('Lihat & Reassign', url('/dashboard/todo-list'))
                ->line("Kamu bisa membuka detail tugas dan mereassign ke member lain."),

            'needs_attention' => (new MailMessage)
                ->subject("[{$appName}] 🚨 Perlu perhatian: {$this->todo->title}")
                ->greeting("Halo {$notifiable->name}!")
                ->line("Tugas **\"{$this->todo->title}\"** sudah ditandai **Perlu Perhatian** karena {$daysLate} hari melewati deadline.")
                ->action('Tangani Sekarang', url('/dashboard/todo-list')),

            'final_alert' => (new MailMessage)
                ->subject("[{$appName}] 🆘 {$daysLate} hari terlambat: {$this->todo->title}")
                ->greeting("Halo {$notifiable->name}!")
                ->line("Notifikasi final. Tugas **\"{$this->todo->title}\"** sudah **{$daysLate} hari** terlambat dan belum ada tindakan.")
                ->action('Tangani Segera', url('/dashboard/todo-list')),

            'reassigned' => (new MailMessage)
                ->subject("[{$appName}] 🔄 Tugas dialihkan: {$this->todo->title}")
                ->greeting("Halo {$notifiable->name}!")
                ->line("Tugas **\"{$this->todo->title}\"** telah dialihkan ke **" . ($this->reassignedTo?->name ?? '-') . "**.")
                ->line("Dialihkan oleh: " . ($this->reassignedBy?->name ?? '-'))
                ->action('Lihat Tugas', url('/dashboard/todo-list')),

            default => (new MailMessage)->subject("Update Tugas")->line($this->todo->title),
        };
    }
}