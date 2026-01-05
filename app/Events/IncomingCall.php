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
              'avatar' => $this->caller->avatar_url ?? null,
          ],
          'callee' => [  // ✅ Tambahkan data callee
              'id' => $this->callee->id,
              'name' => $this->callee->name,
              'avatar' => $this->callee->avatar_url ?? null,
          ],
          'call_type' => $this->callType,
          'channel_name' => $this->channel,  // ✅ Ubah ke 'channel_name'
          'agora_token' => $this->token,     // ✅ Ubah ke 'agora_token'
          'timestamp' => now()->toISOString()
      ];
  }  
}