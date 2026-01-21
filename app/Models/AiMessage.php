<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    use HasFactory;

    protected $table = 'ai_messages';

    protected $fillable = [
        'user_id',
        'message',
        'sender',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
