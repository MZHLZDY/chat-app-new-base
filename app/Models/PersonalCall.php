<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalCall extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'channel_name',
        'caller_id',
        'callee_id',
        'status',
        'call_type',
        'started_at',
        'answered_at',
        'ended_at',
        'duration',
        'ended_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration' => 'integer',
    ];

    // Relationship: User yang menelpon
    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    // Relationship: User yang ditelpon
    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    // Relationship: User yang mengakhiri call
    public function endedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    // Relationship: Call events (logs)
    public function events(): HasMany
    {
        return $this->hasMany(CallEvent::class, 'call_id');
    }

    // Scope: Mendapatkan pangggian untuk user tertentu
    public function scopeForUser($query, int $userId)
    {
        return $query->where('caller_id', $userId)
                    ->orWhere('callee_id', $userId);
    }

    // Scope: Mendapatkan panggilan yang sedang berlangsung
    public function scopeOngoing($query)
    {
        return $query->whereIn('status', 'ongoing');
    }

    // Scope: Mendapatkan panggilan yang belum dijawab
    public function scopeMissed($query)
    {
        return $query->where('status', 'missed');
    }

    // Cek jika panggilan itu video call
    public function isVideo(): bool
    {
        return $this->call_type === 'video';
    }

    // Cek jika panggilan itu voice call
    public function isVoice(): bool
    {
        return $this->call_type === 'voice';
    }

    // Mendapatkan format durasi (JJ:MM:DD atau MM:DD)
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration) {
            return '00:00';
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    // Mendapatkan informasi panggilan dalam bentuk teks pesan (didalam UI chat)
    public function getCallMessageText(): string
    {
        $text = $this->call_type === 'video' ? 'Panggilan Video' : 'Panggilan Suara';

        switch ($this->status) {
            case 'ringing':
                $text .= ' • Memanggil';
                break;
            case 'ongoing':
                $text .= ' • Sedang Berlangsung';
                break;
            case 'cancelled':
                $text .= ' • Dibatalkan';
                break;
            case 'rejected':
                $text .= ' • Ditolak';
                break;
            case 'missed':
                $text .= ' • Tidak Terjawab';
                break;
            case 'ended':
                if ($this->duration > 0) {
                    $text .= ' • ' . $this->formatted_duration;
                } else {
                    $text .= ' • Selesai';
                }
                break;
            default:
                $text .= ' • Selesai';
        }

        return $text;
    }
}