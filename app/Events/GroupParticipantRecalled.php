<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Group;

class GroupParticipantRecalled implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $callId,
        public Group $group,
        public User $user // User yang sedang dipanggil ulang
    ) {}

    public function broadcastOn(): array
    {
        // Broadcast ke channel grup agar semua peserta yang di dalam call tahu
        return [ new PrivateChannel('group.' . $this->group->id) ];
    }

    public function broadcastAs(): string
    {
        return 'group-participant-recalled';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->callId,
            'status' => 'ringing', // Kita tegaskan statusnya kembali ringing
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name
            ],
            'message' => $this->user->name . ' is being recalled...'
        ];
    }
}