<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TodoActivityLog extends Model
{
    protected $fillable = ['todo_id', 'user_id', 'action', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function todo()
    {
        return $this->belongsTo(Todo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getReadableLabelAttribute(): string
    {
        return match ($this->action) {
            'created'            => 'Tugas dibuat',
            'status_changed'     => 'Status diubah ke ' . ($this->meta['new_status'] ?? '-'),
            'reassigned'         => 'Dialihkan ke ' . ($this->meta['to_name'] ?? '-'),
            'assignee_added'     => ($this->meta['name'] ?? '-') . ' ditambahkan sebagai anggota',
            'assignee_removed'   => ($this->meta['name'] ?? '-') . ' dikeluarkan',
            'escalated_level_1'  => '🔴 Eskalasi: assignee dinotifikasi (terlambat)',
            'escalated_level_2'  => '⚠️ Eskalasi: owner board dinotifikasi',
            'escalated_level_3'  => '🚨 Eskalasi: ditandai Perlu Perhatian',
            'escalated_level_4'  => '🆘 Eskalasi: semua member dinotifikasi',
            'reminder_sent'      => '⏰ Pengingat deadline dikirim',
            'deadline_updated'   => 'Deadline diperbarui ke ' . ($this->meta['due_date'] ?? '-'),
            'comment_added'      => 'Komentar ditambahkan',
            default              => $this->action,
        };
    }
}