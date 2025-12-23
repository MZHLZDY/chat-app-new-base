<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'call_id',
        'user_id',
        'event_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationship: Personal Call
    public function call(): BelongsTo
    {
        return $this->belongsTo(PersonalCall::class, 'call_id');
    }

    // Relationship: User yang trigger event
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Boot method untuk auto-set created_at
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }
}