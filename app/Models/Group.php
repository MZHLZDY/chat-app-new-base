<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = ['name', 'admin_id', 'description', 'photo'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')
                ->withPivot('is_admin', 'last_read_at', 'last_cleared_at') 
                ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(GroupMessage::class)->latest();
    }

}
