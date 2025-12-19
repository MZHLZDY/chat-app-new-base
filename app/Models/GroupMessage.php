<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\HiddenMessage;

class GroupMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'sender_id',
        'message', 
        'type', 
        'file_path', 
        'file_name', 
        'file_mime_type', 
        'file_size',
        'reply_to_id',
        'deleted_by'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_by' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(GroupMessage::class, 'reply_to_id');
    }
}
