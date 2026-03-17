<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallUpgradedToGroup implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $originalCallId;
    public $groupCall;
    public $participantIds;

    /**
     * Create a new event instance.
     */
    public function __construct($originalCallId, $groupCall, $participantIds)
    {
        $this->originalCallId = $originalCallId;
        $this->groupCall = $groupCall;
        $this->participantIds = $participantIds;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        $channels = [];
        
        // Memancarkan event ke semua peserta yang terlibat (termasuk host dan yang baru diundang)
        foreach ($this->participantIds as $userId) {
            $channels[] = new PrivateChannel('user.' . $userId);
        }

        return $channels;
    }

    /**
     * Tentukan nama event yang akan didengarkan oleh frontend.
     */
    public function broadcastAs()
    {
        return 'CallUpgradedToGroup';
    }
}