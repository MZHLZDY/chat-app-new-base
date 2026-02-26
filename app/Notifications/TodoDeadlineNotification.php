<?php

namespace App\Notifications;

use App\Models\Todo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TodoDeadlineNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Todo $todo,
        public readonly string $type = 'reminder',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    // ─── IN-APP (database) ────────────────────────────────────────────────────
    public function toDatabase(object $notifiable): array
    {
        return match ($this->type) {
            'invite' => [
                'type'     => 'todo_invite',
                'title'    => 'Kamu diundang ke tugas baru!',
                'message'  => "Kamu diundang untuk mengerjakan: \"{$this->todo->title}\"",
                'todo_id'  => $this->todo->id,
                'icon'     => '👥',
            ],
            'overdue' => [
                'type'     => 'todo_overdue',
                'title'    => '⏰ Tugas sudah melewati deadline!',
                'message'  => "\"{$this->todo->title}\" sudah melewati batas waktu.",
                'todo_id'  => $this->todo->id,
                'icon'     => '🔴',
            ],
            default => [  // reminder
                'type'     => 'todo_reminder',
                'title'    => '⏰ Deadline segera tiba!',
                'message'  => "\"{$this->todo->title}\" deadline: " . $this->todo->due_date?->format('d M Y, H:i'),
                'todo_id'  => $this->todo->id,
                'icon'     => '🟡',
            ],
        };
    }

    // ─── EMAIL ────────────────────────────────────────────────────────────────
    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'App');
        $todoTitle = $this->todo->title;

        return match ($this->type) {
            'invite' => (new MailMessage)
                ->subject("[{$appName}] Kamu diundang ke tugas: {$todoTitle}")
                ->greeting("Halo {$notifiable->name}! 👋")
                ->line("Kamu baru saja diundang untuk berkolaborasi pada tugas berikut:")
                ->line("**{$todoTitle}**")
                ->when($this->todo->description, fn ($m) => $m->line($this->todo->description))
                ->when($this->todo->due_date, fn ($m) => $m->line("⏰ Deadline: " . $this->todo->due_date->format('l, d F Y pukul H:i')))
                ->action('Lihat Tugas', url('/dashboard'))
                ->line("Selamat berkolaborasi! 🚀"),

            'overdue' => (new MailMessage)
                ->subject("[{$appName}] 🔴 Tugas terlambat: {$todoTitle}")
                ->greeting("Halo {$notifiable->name}!")
                ->line("Tugas berikut sudah **melewati batas waktu**:")
                ->line("**{$todoTitle}**")
                ->line("Deadline: " . $this->todo->due_date?->format('d F Y, H:i'))
                ->action('Selesaikan Sekarang', url('/dashboard'))
                ->line("Segera selesaikan atau perbarui status tugas."),

            default => (new MailMessage) // reminder
                ->subject("[{$appName}] ⏰ Deadline segera: {$todoTitle}")
                ->greeting("Halo {$notifiable->name}!")
                ->line("Jangan lupa, deadline tugas berikut sudah dekat:")
                ->line("**{$todoTitle}**")
                ->line("⏰ Deadline: " . $this->todo->due_date?->format('l, d F Y pukul H:i'))
                ->when($this->todo->description, fn ($m) => $m->line($this->todo->description))
                ->action('Buka Board', url('/dashboard'))
                ->line("Semangat! 💪"),
        };
    }
}