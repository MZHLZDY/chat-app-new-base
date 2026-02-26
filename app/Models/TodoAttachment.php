<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TodoAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'todo_id',
        'user_id',
        'type',
        'name',
        'path',
        'url',
        'mime_type',
        'size',
    ];

    protected $appends = ['download_url'];

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->type === 'link') return $this->url;
        if ($this->path) return asset('storage/' . $this->path);
        return null;
    }

    public function todo()
    {
        return $this->belongsTo(Todo::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}