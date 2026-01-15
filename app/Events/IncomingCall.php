<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\PersonalCall;

class IncomingCall implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $caller;
    public $callee;
    public $callType;
    public $channel;
    public $callId;
    public $token;
    public PersonalCall $call;

    public function __construct($caller, $callee, $callType, $channel, $callId, $token, PersonalCall $call)
    {
        $this->caller = $caller;
        $this->callee = $callee;
        $this->callType = $callType;
        $this->channel = $channel;
        $this->callId = $callId;
        $this->token = $token;
        $this->call = $call;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->callee->id);
    }

    public function broadcastAs()
    {
        return 'incoming-call';
    }

    public function broadcastWith()
{
    return [
        'call_id' => $this->callId,
        'caller' => [
            'id' => $this->caller->id,
            'name' => $this->caller->name,
            'email' => $this->caller->email,
            // ✅ KIRIM SEMUA FIELD FOTO UNTUK KOMPATIBILITAS
            'photo' => $this->caller->photo,
            'avatar' => $this->caller->photo, // Gunakan field 'photo' langsung
            'profile_photo_url' => $this->caller->profile_photo_url,
            'avatar_url' => $this->caller->profile_photo_url, // Alias
        ],
        'callee' => [
            'id' => $this->callee->id,
            'name' => $this->callee->name,
            'email' => $this->callee->email,
            'photo' => $this->callee->photo,
            'avatar' => $this->callee->photo,
            'profile_photo_url' => $this->callee->profile_photo_url,
            'avatar_url' => $this->callee->profile_photo_url,
        ],
        'call_type' => $this->callType,
        'channel_name' => $this->channel,
        'agora_token' => $this->token,
        'timestamp' => now()->toISOString()
    ];
  } 
}