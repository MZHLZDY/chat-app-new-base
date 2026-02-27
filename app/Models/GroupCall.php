<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupCall extends Model
{
    protected $fillable = [
        'group_id', 'host_id', 'channel_name', 'call_type', 
        'status', 'started_at', 'ended_at'
    ];

    // Relasi ke tabel grup chat Anda
    public function group() {
        return $this->belongsTo(Group::class);
    }

    // Relasi ke host (user yang memulai panggilan)
    public function host() {
        return $this->belongsTo(User::class, 'host_id');
    }

    // Relasi ke tabel partisipan
    public function participants() {
        return $this->hasMany(GroupParticipant::class, 'call_id');
    }
}