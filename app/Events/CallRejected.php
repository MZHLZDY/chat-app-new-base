<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\ChatMessage;

class CallRejected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $callId;
    public $callerId;
    public $reason;
    public $calleeId;
    public $callType;
    public $message; // ✅ Tambahkan property message

    public function __construct(
        int $callId,
        int $callerId,
        int $calleeId,
        string $callType,
        ?string $reason = null, 
    ) {
        $this->callId = $callId;
        $this->callerId = $callerId;
        $this->calleeId = $calleeId;
        $this->callType = $callType;
        $this->reason = $reason;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->callerId),
            new PrivateChannel('user.' . $this->calleeId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call-rejected';
    }

    // ✅ PERBAIKAN: Sertakan message dalam broadcast data
    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->callId,
            'caller_id' => $this->callerId,
            'callee_id' => $this->calleeId,
            'call_type' => $this->callType,
            'reason' => $this->reason,
            'message' => $this->message ? [
                'id' => $this->message->id,
                'message' => $this->message->message,
                'type' => $this->message->type,
                'call_event' => $this->message->callEvent,
                'created_at' => $this->message->created_at,
                'sender_id' => $this->message->sender_id,
                'receiver_id' => $this->message->receiver_id,
            ] : null
        ];
    }
}