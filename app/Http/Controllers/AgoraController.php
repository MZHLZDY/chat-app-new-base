<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Jobs\EndMissedCall;
use App\Models\CallEvent;
use App\Models\PersonalCall;
use App\Models\ChatMessage;
use App\Services\AgoraTokenService;
use App\Events\IncomingCall;
use App\Events\CallAccepted;
use App\Events\CallCancelled;
use App\Events\CallRejected;
use App\Events\CallEnded;
use Kreait\Firebase\Factory;

class AgoraController extends Controller
{
    private AgoraTokenService $agoraService;

    public function __construct(AgoraTokenService $agoraService)
    {
        $this->agoraService = $agoraService;
    }

    // ==== Personal Call Methods =====

    // Generate Agora RTC Token
    public function generateToken(Request $request)
    {
        $request->validate([
            'channel_name' => 'required|string',
            'uid' => 'nullable|integer',
        ]);

        $uid = $request->uid ?? auth()->id();

        try {
            $token = $this->agoraService->generateRtcToken(
                $request->channel_name,
                $uid,
                'publisher',
                3600
            );

            return response()->json([
                'token' => $token,
                'channel_name' => $request->channel_name,
                'uid' => $uid,
                'app_id' => $this->agoraService->getAppId(),
            ]);
        } catch (\Exception $e) {
            Log::error('❌ [GENERATE TOKEN] ERROR: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate token'], 500);
        }
    }

    // Invite user untuk telepon (voice / video)
    public function inviteCall(Request $request)
    {
        DB::beginTransaction();
        try {
            Log::info('📞 [INVITE CALL] Starting...', [
                'caller_id' => auth()->id(),
                'callee_id' => $request->callee_id,
                'call_type' => $request->call_type,
            ]);

            $request->validate([
                'callee_id' => 'required|exists:users,id',
                'call_type' => 'required|in:voice,video',
            ]);

            $caller = auth()->user();
            $callee = User::findOrFail($request->callee_id);

            // Validate: Tidak bisa menelpon diri sendiri
            if ($callee->id === $caller->id) {
                return response()->json(['error' => 'Kamu tidak dapat menelpon diri sendiri'], 400);
            }

            // Generate Agora channel name dan token
            $channelName = 'call_' . Str::random(16);

            // Buat catatan panggilan
            $call = PersonalCall::create([
                'caller_id' => $caller->id,
                'callee_id' => $callee->id,
                'call_type' => $request->call_type,
                'status' => 'ringing',
                'channel_name' => $channelName,
                'started_at' => now(),
            ]);

            // broadcast(new \App\Events\IncomingCall($call));

            // Log Event: Initiated
            CallEvent::create([
                'call_id' => $call->id,
                'user_id' => $caller->id,
                'event_type' => 'initiated',
                'created_at' => now(),
            ]);

            // Generate Token untuk penelpon (caller)
            $callerToken = $this->agoraService->generateRtcToken($channelName, $caller->id);

            // Generate Token untuk penerima telepon (callee)
            $calleeToken = $this->agoraService->generateRtcToken($channelName, $callee->id);

            // Dispatch Job: otomatis mengakhiri panggilan jika tidak dijawab selama 30 detik
            EndMissedCall::dispatch($call->id)->delay(now()->addSeconds(30)); // Uncommand jika sudah ada jobs

            // Broadcast untuk penerima telepon (callee)
            broadcast(new IncomingCall(
                $caller,
                $callee,
                $request->call_type,
                $channelName,
                $call->id,
                $calleeToken,
                $call,
            ));

            // Tulis ke Firebase
            try {
                $firebase = (new Factory)
                    ->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')))
                    ->withDatabaseUri(env('FIREBASE_DATABASE_URL'))
                    ->createDatabase();

                $firebase->getReference("calls/{$callee->id}/incoming")
                    ->set([
                        'call_id' => $call->id,
                        'call_type' => $request->call_type,
                        'caller' => [
                            'id' => $caller->id,
                            'name' => $caller->name,
                            'email' => $caller->email,
                            'avatar' => $caller->avatar_url ?? null,
                        ],
                        'agora_token' => $calleeToken,
                        'channel_name' => $channelName,
                        'status' => 'ringing',
                        'timestamp' => now()->timestamp,
                    ]);

                Log::info('✅ Firebase: Panggilan masuk ditulis ke Firebase', [
                    'callee_id' => $callee->id,
                    'call_id' => $call->id,
                ]);

            } catch (\Exception $e) {
                Log::error('❌ Firebase: Gagal menulis panggilan masuk ke firebaase:' . $e->getMessage());
            }

            DB::commit();

            // Log Success
            Log::info('✅ [INVITE CALL] Success', [
                'call_id' => $call->id,
                'channel_name' => $channelName
            ]);

           return response()->json([
    'call_id' => $call->id,
    'channel_name' => $channelName,
    'agora_token' => $callerToken,
    'app_id' => $this->agoraService->getAppId(),
    'status' => 'ringing',
    'call' => [
        'id' => $call->id,
        'caller_id' => $caller->id,
        'callee_id' => $callee->id,
        'call_type' => $request->call_type,
        'status' => 'ringing',
        'channel_name' => $channelName,
        'caller' => [
            'id' => $caller->id,
            'name' => $caller->name,
            'email' => $caller->email,
            'photo' => $caller->photo,
            'profile_photo_url' => $caller->profile_photo_url,
        ],
        'callee' => [
            'id' => $callee->id,
            'name' => $callee->name,
            'email' => $callee->email,
            'photo' => $callee->photo,
            'profile_photo_url' => $callee->profile_photo_url,
        ],
    ],
]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('❌ [INVITE CALL] Error:' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Gagal memulai panggilan'], 500);
        }
    }
    
    // Answer panggilan masuk
    public function answerCall(Request $request)
    {
        DB::beginTransaction();
        try {
            Log::info('📞 [ANSWER CALL] Starting...', [
                'user_id' => auth()->id(),
                'call_id' => $request->call_id,
            ]);

            $request->validate([
                'call_id' => 'required|exists:personal_calls,id',
            ]);

            $call = PersonalCall::findOrFail($request->call_id);

            // Validate: Hanya callee yang bisa menjawab panggilan
            if ($call->callee_id !== auth()->id()) {
                Log::warning('⚠️ [ANSWER CALL] Unauthorized', [
                    'user_id' => auth()->id(),
                    'callee_id' => $call->callee_id,
                ]);
                return response()->json(['error' => 'Kamu tidak diizinkan untuk menjawab panggilan ini'], 403);
            }

            // Validate: Pastikan panggilan masih berstatus 'ringing' atau 'missed'
            if ($call->status !== 'ringing' && $call->status !== 'missed') {
                Log::warning('⚠️ [ANSWER CALL] Invalid Call Status', [
                    'status' => $call->status
                ]);
                return response()->json(['error' => 'Panggilan tidak berdering'], 400);
            }

            // Update Status
            $call->update([
                'status' => 'ongoing',
                'answered_at' => now(),
            ]);

            // Log Event: Answered
            CallEvent::create([
                'call_id' => $call->id,
                'user_id' => auth()->id(),
                'event_type' => 'answered',
                'created_at' => now(),
            ]);

            // Generate Token untuk callee
            $calleeToken = $this->agoraService->generateRtcToken(
                $call->channel_name,
                auth()->id(),
                'publisher',
                3600
            );

            // Broadcast ke caller
            broadcast(new CallAccepted(
                $call->caller_id,
                auth()->user(),
                $call->channel_name,
                $call->id,
                $call->call_type
            ));

            // Tulis ke firebase untuk notifikasi panggilan masuk dari penelpon
            try {
                $firebase = (new Factory)
                    ->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')))
                    ->withDatabaseUri(env('FIREBASE_DATABASE_URL'))
                    ->createDatabase();

                $firebase->getReference("calls/{$call->caller_id}/status")
                    ->set([
                        'call_id' => $call->id,
                        'status' => 'accepted',
                        'call_type' => $call->call_type,
                        'timestamp' => now()->timestamp,
                    ]);

                Log::info('✅ Firebase: Panggilan diterima', [
                    'caller_id' => $call->caller_id,
                    'call_id' => $call->id,
                ]);

            } catch (\Exception $e) {
                Log::error('❌ Firebase: Gagal menulis notifikasi panggilan masuk ke firebase: ' . $e->getMessage());
            }

            DB::commit();

            Log::info('✅ [ANSWER CALL] Success', ['call_id' => $call->id]);

            return response()->json([
                'call_id' => $call->id,
                'channel_name' => $call->channel_name,
                'agora_token' => $calleeToken,
                'app_id' => $this->agoraService->getAppId(),
                'status' => 'ongoing',
                'caller' => [
                    'id' => $call->caller->id,
                    'name' => $call->caller->name,
                    'avatar' => $call->caller->avatar_url ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('❌ [ANSWER CALL] Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Gagal menjawab panggilan'], 500);
        }
    }

    // Reject panggilan masuk
    public function rejectCall(Request $request)
    {
        DB::beginTransaction();
        try {
            Log::info('📞 [REJECT CALL] Starting...', [
                'user_id' => auth()->id(),
                'call_id' => $request->call_id,
            ]);

            $request->validate([
                'call_id' => 'required|exists:personal_calls,id',
                'reason' => 'nullable|string',
            ]);

            $call = PersonalCall::findOrFail($request->call_id);

            // Validate: Hanya callee yang bisa menolak panggilan
            if ($call->callee_id !== auth()->id()) {
                Log::warning('⚠️ [REJECT CALL] Unauthorized', [
                    'user_id' => auth()->id(),
                    'callee_id' => $call->callee_id,
                ]);
                return response()->json(['error' => 'Kamu tidak diizinkan untuk menolak panggilan ini'], 403);
            }

            // Validate: Pastikan panggilan masih berstatus 'ringing' atau 'missed'
            if ($call->status !== 'ringing' && $call->status !== 'missed') {
                Log::warning('⚠️ [REJECT CALL] Invalid Call Status', [
                    'status' => $call->status
                ]);
                return response()->json(['error' => 'Panggilan tidak berdering'], 400);
            }

            // Update Status hanya jika masih ringing
           if ($call->status === 'ringing') {
              $call->update([
                'status' => 'rejected',
                'ended_at' => now(),
              ]);
        
              // (Opsional) Buat pesan chat otomatis "Panggilan Ditolak"
              // $chatMessage = ChatMessage::create([...]); 
            }

            // Log Event: Rejected
            CallEvent::create([
                'call_id' => $call->id,
                'user_id' => auth()->id(),
                'event_type' => 'rejected',
                'metadata' => $request->reason ? ['reason' => $request->reason] : null,
                'created_at' => now(),
            ]);

            // Broadcast ke caller
            broadcast(new CallRejected(
                $call->id,
                $call->caller_id,
                $call->callee_id,
                $call->call_type,
                $request->reason ?? null,
            ));

            //  Tulis firebase untuk notifikasi panggilan ditolak dari callee untuk penelpon
            try {
                $firebase = (new Factory)
                    ->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')))
                    ->withDatabaseUri(env('FIREBASE_DATABASE_URL'))
                    ->createDatabase();

                $firebase->getReference("calls/{$call->caller_id}/status")
                    ->set([
                        'call_id' => $call->id,
                        'status' => 'rejected',
                        'call_type' => $call->call_type,
                        'timestamp' => now()->timestamp,
                    ]);

                Log::info('✅ Firebase: Panggilan ditolak', [
                    'caller_id' => $call->caller_id,
                    'call_id' => $call->id,
                ]);

            } catch (\Exception $e) {
                Log::error('❌ Firebase: Gagal menulis status panggilan ditolak ke firebase: ' . $e->getMessage());
            }

            DB::commit();

            Log::info('✅ [REJECT CALL] Success', ['call_id' => $call->id]);

            return response()->json([
                'message' => 'Panggilan ditolak',
                'call_id' => $call->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ [REJECT CALL] Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Gagal menolak panggilan'], 500);
        }
    }

    // Cancel outgoing call
    public function cancelCall(Request $request)
    {
        DB::beginTransaction();
        try {
            Log::info('📞[CANCEL CALL] Starting...', [
                'user_id' => auth()->id(),
                'call_id' => $request->call_id,
            ]);

            $request->validate([
                'call_id' => 'required|exists:personal_calls,id',
            ]);
            
            $call = PersonalCall::findOrFail($request->call_id);
            $targetUserId = ($call->caller_id === auth()->id()) ? $call->callee_id : $call->caller_id;

            // --- PERBAIKAN VALIDASI ---
            // Kita izinkan lanjut jika statusnya 'ringing' ATAU 'missed'
            // Jika 'missed', berarti job timeout sudah jalan duluan, tapi kita tetap perlu kirim notifikasi ke lawan agar dering berhenti.
            if ($call->status !== 'ringing' && $call->status !== 'missed') {
                Log::warning('⚠️ [CANCEL CALL] Invalid Call Status', [
                    'status' => $call->status
                ]);
                return response()->json(['error' => 'Panggilan tidak berdering atau sudah berakhir'], 400);
            }

            // Update Status dan Log Event HANYA jika status sebelumnya masih 'ringing'
            // Jika sudah 'missed', biarkan saja statusnya missed di DB.
            if ($call->status === 'ringing') {
                $call->update([
                    'status' => 'cancelled',
                    'ended_at' => now(),
                ]);

                // Log Event: Cancelled
                CallEvent::create([
                    'call_id' => $call->id,
                    'user_id' => auth()->id(),
                    'event_type' => 'cancelled',
                    'created_at' => now(),
                ]);
            }

            // Broadcast ke callee (Laravel Echo)
            broadcast(new CallCancelled(
                $call->id,
                $call->caller_id,
                $call->callee_id,
                $call->call_type,
            ));

            // --- BAGIAN PENTING: NOTIFIKASI FIREBASE ---
            // Kode ini sekarang akan tetap dijalankan meskipun status sudah 'missed'
            try {
                $firebase = (new Factory)
                    ->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')))
                    ->withDatabaseUri(env('FIREBASE_DATABASE_URL'))
                    ->createDatabase();

                // 1. Kirim notifikasi 'cancel_call' agar modal tertutup
                $firebase->getReference("notifications/{$targetUserId}")
                    ->push([
                        'type' => 'cancel_call', // Pastikan tipe ini sama dengan yang dicek di Index.vue
                        'call_id' => $call->id,
                        'caller_id' => auth()->id(),
                        'title' => 'Panggilan Berakhir',
                        'body' => 'Panggilan dibatalkan atau tidak terjawab',
                        'timestamp' => now()->timestamp * 1000,
                    ]);
                    
                // 2. HAPUS node incoming call agar HP lawan berhenti berdering (PENTING)
                $firebase->getReference("calls/{$targetUserId}/incoming")->remove();

                Log::info('✅ Firebase: Notifikasi cancel dikirim ke target', ['target' => $targetUserId]);

            } catch (\Exception $e) {
                Log::error('❌ Gagal kirim notifikasi cancel ke Firebase: ' . $e->getMessage());
            }

            DB::commit();

            Log::info('✅ [CANCEL CALL] Success', ['call_id' => $call->id]);

            return response()->json([
                'message' => 'Panggilan dibatalkan',
                'call_id' => $call->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ [CANCEL CALL] Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Gagal membatalkan panggilan'], 500);
        }
    }

    // End ongoing call
    public function endCall(Request $request)
    {
        DB::beginTransaction();
        try {
            Log::info('📞 [END CALL] Starting...', [
                'user_id' => auth()->id(),
                'call_id' => $request->call_id,
            ]);

            $request->validate([
                'call_id' => 'required|exists:personal_calls,id',
            ]);

            $call = PersonalCall::findOrFail($request->call_id);

            // Validate: Hanya caller atau callee yang bisa mengakhiri panggilan
            if ($call->caller_id !== auth()->id() && $call->callee_id !== auth()->id()) {
                Log::warning('⚠️ [END CALL] Unauthorized', [
                    'user_id' => auth()->id(),
                    'caller_id' => $call->caller_id,
                    'callee_id' => $call->callee_id,
                ]);
                return response()->json(['error' => 'Kamu tidak diizinkan untuk mengakhiri panggilan ini'], 403);
            }

            // Validate: status 'ringing' atau 'ongoing (bisa diakhiri sebelum dijawab)'
            if (!in_array($call->status, ['ringing', 'ongoing'])) {
                Log::warning('⚠️ [END CALL] Invalid Call Status', [
                    'status' => $call->status
                ]);
                return response()->json(['error' => 'Panggilan sudah berakhir atau tidak valid'], 400);
            }

            // Kalkulasi durasi panggilan (jika sudah dijawab)
            $duration = null;
            if ($call->answered_at) {
                $duration = now()->diffInSeconds($call->answered_at);
            }

            // Update Status
            $call->update([
                'status' => 'ended',
                'ended_at' => now(),
                'duration' => $duration,
                'ended_by' => auth()->id(),
            ]);

            // Log Event: Ended
            CallEvent::create([
                'call_id' => $call->id,
                'user_id' => auth()->id(),
                'event_type' => 'ended',
                'metadata' => $duration ? ['duration' => $duration] : null,
                'created_at' => now(),
            ]);

            // Broadcast kedua user
            broadcast(new CallEnded(
                $call->id,
                $call->caller_id,
                $call->callee_id,
                auth()->id(),
                $duration ?? 0,
                $call->call_type,
            ));

            // Tulis ke firebase untuk notifikasi panggilan diakhiri
            try {
                // Deteksi user lawan bicara
                $otherUserId = ($call->caller_id === auth()->id())
                    ? $call->callee_id
                    : $call->caller_id;

                $firebase = (new Factory)
                    ->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')))
                    ->withDatabaseUri(env('FIREBASE_DATABASE_URL'))
                    ->createDatabase();

                $firebase->getReference("calls/{$otherUserId}/status")
                    ->set([
                        'call_id' => $call->id,
                        'status' => 'ended',
                        'call_type' => $call->call_type,
                        'timestamp' => now()->timestamp,
                    ]);

                Log::info('✅ Firebase: Panggilan diakhiri', [
                    'other_user_id' => $otherUserId,
                    'call_id' => $call->id,
                    'duration' => $duration,
                ]);

            } catch (\Exception $e) {
                Log::error('❌ Firebase: Gagal menulis status panggilan diakhiri ke firebase: ' . $e->getMessage());
            }

            DB::commit();

            Log::info('✅ [END CALL] Success', [
                'call_id' => $call->id,
                'duration' => $duration
            ]);

            return response()->json([
                'message' => 'Panggilan diakhiri',
                'call_id' => $call->id,
                'duration' => $duration,
                'duration_formatted' => $duration ? $this->formatDuration($duration) : null,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ [END CALL] Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Gagal mengakhiri panggilan'], 500);
        }
    }

    // Dapatkan histori panggilan untuk user yang terverifikasi
    public function getCallHistory(Request $request)
    {
        try {
            $user = auth()->user();

            $calls = PersonalCall::forUser($user->id)
                ->with([
                    'caller:id,name,avatar',
                    'callee:id,name,avatar',
                    'endedByUser:id,name'
                    ])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json($calls);

        } catch (\Exception $e) {
            Log::error('❌ [GET CALL HISTORY] Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Gagal mendapatkan histori panggilan'], 500);
        }
    }

    // Format durasi dalam detik agar bisa dibaca oleh user
    private function formatDuration($seconds)
    {
        if ($seconds <60) { 
            return $seconds . ' detik';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes <60) { 
            return $minutes . ' menit' . ($remainingSeconds > 0 ? ' ' . $remainingSeconds . ' detik' : '');
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return $hours . ' jam' . ($remainingMinutes > 0 ? ' ' . $remainingMinutes . ' menit' : '');
    }
}