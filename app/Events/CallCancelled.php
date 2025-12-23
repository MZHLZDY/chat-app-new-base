<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public $callId;
    public $callerId;
    public $calleeId;
    public $callType;

    public function __construct(int $callId, int $callerId,  int $calleeId, string $callType)
    {
        $this->callId = $callId;
        $this->callerId = $callerId;
        $this->calleeId = $calleeId;
        $this->callType = $callType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->callerId),
            new PrivateChannel('user.' . $this->calleeId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call-cancelled';
    }

    public function broadcastWith(): array
    {
        return[
            'call_id' => $this->callId,
            'caller_id' => $this->callerId,
            'callee_id' => $this->calleeId,
            'call_type' => $this->callType,
            'cancelled_at' => now()->toISOString(),
        ];
    }
}
