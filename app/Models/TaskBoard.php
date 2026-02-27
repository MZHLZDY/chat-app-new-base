<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskBoard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'color',
        'icon',
    ];

    /**
     * Atribut yang otomatis disertakan saat model di-serialize ke JSON.
     * Dengan ini, 'stats' akan selalu muncul tanpa perlu set manual di controller.
     */
    protected $appends = ['stats'];

    // ─── Relationships ────────────────────────────────────────────────────────

    /** Pemilik board */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Semua member board (pivot: role = owner|member) */
    public function members()
    {
        return $this->belongsToMany(User::class, 'task_board_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** Semua todo di dalam board ini */
    public function todos()
    {
        return $this->hasMany(Todo::class, 'board_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Hitung statistik board untuk ditampilkan di list */
    public function getStatsAttribute(): array
    {
        $todos = $this->todos;
        $total = $todos->count();
        $done  = $todos->where('status', 'done')->count();
        return [
            'total'    => $total,
            'done'     => $done,
            'progress' => $total > 0 ? round(($done / $total) * 100) : 0,
        ];
    }
}