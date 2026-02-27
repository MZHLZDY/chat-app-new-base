<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupParticipant extends Model
{
    protected $fillable = [
        'call_id', 'user_id', 'status', 'joined_at', 'left_at'
    ];

    public function call() {
        return $this->belongsTo(GroupCall::class, 'call_id');
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}