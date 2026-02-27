<?php

namespace App\Notifications;

use App\Models\TaskBoard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BoardInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TaskBoard $board) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'     => 'board_invite',
            'icon'     => '📋',
            'title'    => "Undangan Board: {$this->board->name}",
            'message'  => "{$this->board->owner->name} mengundangmu ke board \"{$this->board->name}\"",
            'board_id' => $this->board->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Undangan Board: {$this->board->name}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("{$this->board->owner->name} mengundangmu untuk bergabung ke board **\"{$this->board->name}\"**.")
            ->action('Lihat Board', url('/dashboard/todo-list'))
            ->line('Kamu bisa langsung melihat dan mengerjakan tugas di board ini.');
    }
}