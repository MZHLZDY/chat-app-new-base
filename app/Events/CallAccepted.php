<?php
// app/Events/CallAccepted.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $callerId;
    public $callee;
    public $channel;
    public $callId;
    public $callType;
    public $message;

    public function __construct($callerId, $callee, $channel, $callId, $callType = 'voice', $message = null)
    {
        $this->callerId = $callerId;
        $this->callee = $callee;
        $this->channel = $channel;
        $this->callId = $callId;
        $this->callType = $callType;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('user.' . $this->callerId),
            new PrivateChannel('user.' . $this->callee->id)
        ];
    }

    public function broadcastAs()
    {
        return 'call-accepted';
    }

    public function broadcastWith()
    {
        return [
            'call_id' => $this->callId,
            'channel' => $this->channel,
            'call_type' => $this->callType,
            'callee' => [
                'id' => $this->callee->id,
                'name' => $this->callee->name
            ],
            'caller_id' => $this->callerId,
            'message' => $this->message,
            'timestamp' => now()->toISOString()
        ];
    }
}