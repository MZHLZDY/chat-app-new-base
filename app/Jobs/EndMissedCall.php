<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\PersonalCall;
use App\Models\CallEvent;
use App\Events\CallMissed;

class EndMissedCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $callId;

    /**
     * Create a new job instance.
     */
    public function __construct($callId)
    {
        $this->callId = $callId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('⏰ [END MISSED CALL] Starting...', [
                'call_id' => $this->callId,
            ]);

            $call = PersonalCall::find($this->callId);

            // jika panggilan tidak ditemukan, maka skip
            if (!$call) {
                Log::warning('⚠️ [END MISSED CALL] Panggilan tidak ditemukan', [
                    'call_id' => $this->callId,
                ]);
                return;
            }

            // Hanya end call jika status masih 'ringing'
            if ($call->status !== 'ringing') {
                Log::info('ℹ️ [END MISSED CALL] Panggilan sudah dijawab atau diakhiri', [
                    'call_id' => $this->callId,
                    'status' => $call->status,
                ]);
                return;
            }

            // Update status ke 'missed
            $call->update([
                'status' => 'missed',
                'ended_at' => now(),
            ]);

            // Log event: missed
            CallEvent::create([
                'call_id' => $call->id,
                'user_id' => $call->callee_id,
                'event_type' => 'missed',
                'created_at' => now(),
            ]);

            // Broadcast ke caller dan callee
            broadcast(new CallMissed(
                $call->id,
                $call->caller_id,
                $call->callee_id,
                $call->call_type
            ));

            Log::info('✅ [END MISSED CALL] Success', [
                'call_id' => $call->id,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [END MISSED CALL] Error: ' . $e->getMessage(), [
                'call_id' => $this->callId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    // Handle job failure
    public function failed(\Throwable $exception): void
    {
        Log::error('❌ [END MISSED CALL] Job failed: ', [
            'call_id' => $this->callId,
            'trace' => $exception->getMessage(),
        ]);
    }
}
