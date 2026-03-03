<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\GroupCall;
use App\Models\GroupParticipant;
use App\Events\GroupParticipantLeft;

class EndMissedGroupCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $callId;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($callId, $userId)
    {
        $this->callId = $callId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Cari data participant
            $participant = GroupParticipant::with('user')
                ->where('call_id', $this->callId)
                ->where('user_id', $this->userId)
                ->first();

            // Jika status masih ringing setelah delay, ubah jadi missed
            if ($participant && $participant->status === 'ringing') {
                $participant->update(['status' => 'missed']);

                $call = GroupCall::with('group')->find($this->callId);
                
                if ($call) {
                    // Broadcast ke peserta lain di room bahwa user ini missed call
                    broadcast(new GroupParticipantLeft($call->id, $call->group, $participant->user, 'missed'));
                    
                    Log::info('⏰ [END MISSED GROUP CALL] Diubah ke missed', [
                        'call_id' => $this->callId,
                        'user_id' => $this->userId
                    ]);
                }
            } else {
                 Log::info('ℹ️ [END MISSED GROUP CALL] Batal karena user sudah jawab/decline', [
                    'call_id' => $this->callId,
                    'user_id' => $this->userId
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('❌ [END MISSED GROUP CALL] Error: ' . $e->getMessage(), [
                'call_id' => $this->callId,
                'user_id' => $this->userId,
            ]);
        }
    }
}
