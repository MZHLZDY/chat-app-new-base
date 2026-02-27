<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Todo extends Model
{
    use HasFactory;

    protected $fillable = [
        'board_id',  // ← wajib ada agar Todo::create() bisa menyimpan board_id
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'reminder_sent',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'reminder_sent' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Semua user yang di-assign ke tugas ini (pivot: role = owner|member).
     */
    public function assignees()
    {
        return $this->belongsToMany(User::class, 'todo_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Lampiran tugas (file & link).
     */
    public function attachments()
    {
        return $this->hasMany(TodoAttachment::class);
    }

    /**
     * Komentar tugas.
     */
    public function comments()
    {
        return $this->hasMany(TodoComment::class)->latest();
    }
}