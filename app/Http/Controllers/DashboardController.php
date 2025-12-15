<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Group;
use App\Models\ChatMessage;
use App\Models\GroupMessage;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats(Request $request)
    {
        try {
            $userId = Auth::id();
            
            // 1. Hitung total pesan belum dibaca (Personal Chat + Group Chat)
            $unreadPersonalMessages = ChatMessage::where('receiver_id', $userId)
                ->whereNull('read_at')
                ->whereNull('deleted_at')
                ->count();
            
            // Untuk group messages, kita perlu cek grup yang user ikuti
            // dan hitung pesan yang belum dibaca
            $userGroupIds = DB::table('group_user')
                ->where('user_id', $userId)
                ->pluck('group_id');
            
            // Hitung total pesan grup sejak user terakhir membaca
            // Untuk simplifikasi, kita ambil semua pesan grup yang belum dibaca
            // (Anda bisa membuat tabel read_status untuk tracking per user per message)
            $unreadGroupMessages = GroupMessage::whereIn('group_id', $userGroupIds)
                ->where('sender_id', '!=', $userId) // Tidak hitung pesan sendiri
                ->where('created_at', '>', DB::raw('(
                    SELECT COALESCE(MAX(created_at), "1970-01-01")
                    FROM group_messages 
                    WHERE sender_id = ' . $userId . ' 
                    AND group_id = group_messages.group_id
                )'))
                ->count();
            
            $totalUnreadMessages = $unreadPersonalMessages + $unreadGroupMessages;
            
            // 2. Hitung total kontak personal (user yang pernah chat dengan kita)
            $totalContacts = User::where('id', '!=', $userId)
                ->where(function($query) use ($userId) {
                    // User yang pernah mengirim pesan ke kita
                    $query->whereExists(function($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
                            ->from('chat_messages')
                            ->whereColumn('chat_messages.sender_id', 'users.id')
                            ->where('chat_messages.receiver_id', $userId)
                            ->whereNull('chat_messages.deleted_at');
                    })
                    // Atau user yang pernah kita kirimi pesan
                    ->orWhereExists(function($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
                            ->from('chat_messages')
                            ->whereColumn('chat_messages.receiver_id', 'users.id')
                            ->where('chat_messages.sender_id', $userId)
                            ->whereNull('chat_messages.deleted_at');
                    });
                })
                ->count();
            
            // 3. Hitung total grup yang user ikuti
            $totalGroups = Group::whereExists(function($query) use ($userId) {
                $query->select(DB::raw(1))
                    ->from('group_user')
                    ->whereColumn('group_user.group_id', 'groups.id')
                    ->where('group_user.user_id', $userId);
            })->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'unread_messages' => $totalUnreadMessages,
                    'total_contacts' => $totalContacts,
                    'total_groups' => $totalGroups,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Alternative: Get detailed stats with breakdown
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetailedStats(Request $request)
    {
        try {
            $userId = Auth::id();
            
            // Personal chat stats
            $personalStats = [
                'unread' => ChatMessage::where('receiver_id', $userId)
                    ->whereNull('read_at')
                    ->whereNull('deleted_at')
                    ->count(),
                'total_received' => ChatMessage::where('receiver_id', $userId)
                    ->whereNull('deleted_at')
                    ->count(),
                'total_sent' => ChatMessage::where('sender_id', $userId)
                    ->whereNull('deleted_at')
                    ->count(),
            ];
            
            // Group stats
            $userGroupIds = DB::table('group_user')
                ->where('user_id', $userId)
                ->pluck('group_id');
            
            $groupStats = [
                'total_groups' => $userGroupIds->count(),
                'admin_groups' => Group::where('admin_id', $userId)->count(),
                'total_messages' => GroupMessage::whereIn('group_id', $userGroupIds)->count(),
            ];
            
            // Contacts
            $contacts = User::where('id', '!=', $userId)
                ->whereExists(function($query) use ($userId) {
                    $query->select(DB::raw(1))
                        ->from('chat_messages')
                        ->where(function($q) use ($userId) {
                            $q->where(function($subQ) use ($userId) {
                                $subQ->whereColumn('chat_messages.sender_id', 'users.id')
                                    ->where('chat_messages.receiver_id', $userId);
                            })
                            ->orWhere(function($subQ) use ($userId) {
                                $subQ->whereColumn('chat_messages.receiver_id', 'users.id')
                                    ->where('chat_messages.sender_id', $userId);
                            });
                        })
                        ->whereNull('chat_messages.deleted_at');
                })
                ->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'personal' => $personalStats,
                    'groups' => $groupStats,
                    'contacts' => $contacts,
                    'summary' => [
                        'unread_messages' => $personalStats['unread'],
                        'total_contacts' => $contacts,
                        'total_groups' => $groupStats['total_groups'],
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load detailed statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}