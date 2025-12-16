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
            
            // Debug log
            \Log::info('Dashboard Stats - User ID: ' . $userId);
            
            // 1. Hitung total pesan belum dibaca (Personal Chat + Group Chat)
            
            // Personal messages yang belum dibaca
            $unreadPersonalMessages = ChatMessage::where('receiver_id', $userId)
                ->whereNull('read_at')
                ->whereNull('deleted_at')
                ->count();
            
            \Log::info('Unread Personal Messages: ' . $unreadPersonalMessages);
            
            // Group messages belum dibaca
            // Ambil semua grup yang user ikuti
            $userGroupIds = DB::table('group_user')
                ->where('user_id', $userId)
                ->pluck('group_id');
            
            \Log::info('User Group IDs: ' . $userGroupIds->toJson());
            
            // Hitung pesan grup yang belum dibaca (exclude pesan sendiri)
            $unreadGroupMessages = GroupMessage::whereIn('group_id', $userGroupIds)
                ->where('sender_id', '!=', $userId)
                ->whereNull('deleted_at')
                ->count();
            
            \Log::info('Unread Group Messages: ' . $unreadGroupMessages);
            
            $totalUnreadMessages = $unreadPersonalMessages + $unreadGroupMessages;
            
            // 2. Hitung total kontak personal
            // PERBAIKAN: Gunakan contacts table atau user relationship jika ada
            
            // Cek apakah ada tabel contacts atau user_contacts
            $totalContacts = 0;
            
            // OPTION 1: Jika menggunakan tabel contacts/user_contacts
            if (DB::getSchemaBuilder()->hasTable('contacts')) {
                $totalContacts = DB::table('contacts')
                    ->where('user_id', $userId)
                    ->count();
                    
                \Log::info('Total Contacts from contacts table: ' . $totalContacts);
            } 
            // OPTION 2: Jika menggunakan user_contacts pivot table
            elseif (DB::getSchemaBuilder()->hasTable('user_contacts')) {
                $totalContacts = DB::table('user_contacts')
                    ->where('user_id', $userId)
                    ->count();
                    
                \Log::info('Total Contacts from user_contacts table: ' . $totalContacts);
            }
            // OPTION 3: Fallback ke perhitungan dari chat messages
            else {
                $contactIds = collect();
                
                // User yang pernah mengirim pesan ke kita
                $sendersToMe = ChatMessage::where('receiver_id', $userId)
                    ->whereNull('deleted_at')
                    ->distinct()
                    ->pluck('sender_id');
                
                // User yang pernah kita kirimi pesan
                $receiversFromMe = ChatMessage::where('sender_id', $userId)
                    ->whereNull('deleted_at')
                    ->distinct()
                    ->pluck('receiver_id');
                
                // Gabungkan dan unique
                $contactIds = $sendersToMe->merge($receiversFromMe)->unique();
                
                $totalContacts = $contactIds->count();
                
                \Log::info('Total Contacts from chat messages: ' . $totalContacts);
                \Log::info('Contact IDs: ' . $contactIds->toJson());
            }
            
            // 3. Hitung total grup yang user ikuti
            $totalGroups = $userGroupIds->count();
            
            \Log::info('Total Groups: ' . $totalGroups);
            
            $result = [
                'success' => true,
                'data' => [
                    'unread_messages' => $totalUnreadMessages,
                    'total_contacts' => $totalContacts,
                    'total_groups' => $totalGroups,
                ]
            ];
            
            \Log::info('Dashboard Stats Result: ' . json_encode($result));
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            \Log::error('Dashboard Stats Error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get detailed stats with breakdown
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
                'owned_groups' => Group::where('owner_id', $userId)->count(),
                'total_messages' => GroupMessage::whereIn('group_id', $userGroupIds)
                    ->whereNull('deleted_at')
                    ->count(),
                'unread_messages' => GroupMessage::whereIn('group_id', $userGroupIds)
                    ->where('sender_id', '!=', $userId)
                    ->whereNull('deleted_at')
                    ->count(),
            ];
            
            // Contacts
            $contactIds = collect();
            
            $sendersToMe = ChatMessage::where('receiver_id', $userId)
                ->whereNull('deleted_at')
                ->distinct()
                ->pluck('sender_id');
            
            $receiversFromMe = ChatMessage::where('sender_id', $userId)
                ->whereNull('deleted_at')
                ->distinct()
                ->pluck('receiver_id');
            
            $contactIds = $sendersToMe->merge($receiversFromMe)->unique();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'personal' => $personalStats,
                    'groups' => $groupStats,
                    'contacts' => $contactIds->count(),
                    'summary' => [
                        'unread_messages' => $personalStats['unread'] + $groupStats['unread_messages'],
                        'total_contacts' => $contactIds->count(),
                        'total_groups' => $groupStats['total_groups'],
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Dashboard Detailed Stats Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load detailed statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}