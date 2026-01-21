<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Group;
use App\Models\ChatMessage;
use App\Models\GroupMessage;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     * * @return \Illuminate\\Http\\JsonResponse
     */
    public function getStats(Request $request)
    {
        try {
            $userId = Auth::id();
            
            // Log::info('Dashboard Stats Request - User ID: ' . $userId);
            
            // ==========================================
            // 1. HITUNG UNREAD PERSONAL MESSAGES
            // ==========================================
            $unreadPersonalMessages = ChatMessage::where('receiver_id', $userId)
                ->whereNull('read_at')
                ->when(Schema::hasColumn('chat_messages', 'deleted_at'), function($query) {
                    return $query->whereNull('deleted_at');
                })
                ->count();
            
            // ==========================================
            // 2. HITUNG UNREAD GROUP MESSAGES (FIXED)
            // ==========================================
            // Logika: Ambil semua pesan di grup user, dimana waktu kirim > waktu user terakhir baca
            
            $unreadGroupMessages = DB::table('group_messages')
                // Join ke tabel pivot 'group_user' untuk dapat 'last_read_at' user yang login
                ->join('group_user', function ($join) use ($userId) {
                    $join->on('group_messages.group_id', '=', 'group_user.group_id')
                         ->where('group_user.user_id', '=', $userId);
                })
                // Jangan hitung pesan yang dikirim oleh diri sendiri
                ->where('group_messages.sender_id', '!=', $userId)
                // Filter utama: Pesan baru vs Waktu Baca Terakhir
                ->where(function ($query) {
                    $query->whereColumn('group_messages.created_at', '>', 'group_user.last_read_at')
                          ->orWhereNull('group_user.last_read_at'); // Jika user belum pernah buka grup sama sekali
                })
                // Cek soft delete jika ada
                ->when(Schema::hasColumn('group_messages', 'deleted_at'), function($query) {
                    $query->whereNull('group_messages.deleted_at');
                })
                ->count();

            // ==========================================
            // 3. HITUNG TOTAL KONTAK (LOGIKA LAMA)
            // ==========================================
            // Ambil ID user yang pernah kirim pesan ke saya
            $sendersQuery = ChatMessage::where('receiver_id', $userId);
            if (Schema::hasColumn('chat_messages', 'deleted_at')) {
                $sendersQuery->whereNull('deleted_at');
            }
            $sendersToMe = $sendersQuery->distinct()->pluck('sender_id');
            
            // Ambil ID user yang pernah saya kirimi pesan
            $receiversQuery = ChatMessage::where('sender_id', $userId);
            if (Schema::hasColumn('chat_messages', 'deleted_at')) {
                $receiversQuery->whereNull('deleted_at');
            }
            $receiversFromMe = $receiversQuery->distinct()->pluck('receiver_id');
            
            // Gabungkan dan hitung unik
            $totalContacts = $sendersToMe->merge($receiversFromMe)->unique()->count();

            // ==========================================
            // 4. HITUNG TOTAL GRUP
            // ==========================================
            $totalGroups = DB::table('group_user')->where('user_id', $userId)->count();

            // ==========================================
            // RETURN RESPONSE
            // ==========================================
            return response()->json([
                'success' => true,
                'data' => [
                    // Struktur detail untuk kompatibilitas frontend lama
                    'personal' => [
                        'unread' => $unreadPersonalMessages
                    ],
                    'groups' => [
                        'unread_messages' => $unreadGroupMessages,
                        'total_groups' => $totalGroups
                    ],
                    'contacts' => $totalContacts,
                    
                    // Summary utama yang dipakai di Dashboard card
                    'summary' => [
                        'unread_messages' => $unreadPersonalMessages + $unreadGroupMessages, // Total digabung
                        'total_contacts' => $totalContacts,
                        'total_groups' => $totalGroups,
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Dashboard Stats Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}