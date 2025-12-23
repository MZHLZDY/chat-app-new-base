<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            
            \Log::info('Dashboard Stats - User ID: ' . $userId);
            
            // 1. Hitung total pesan belum dibaca (Personal Chat)
            $unreadPersonalMessages = ChatMessage::where('receiver_id', $userId)
                ->whereNull('read_at')
                ->when(Schema::hasColumn('chat_messages', 'deleted_at'), function($query) {
                    return $query->whereNull('deleted_at');
                })
                ->count();
            
            \Log::info('Unread Personal Messages: ' . $unreadPersonalMessages);
            
            // 2. Hitung pesan grup yang belum dibaca
            $unreadGroupMessages = 0;
            
            // Cek apakah ada tabel group_user atau groups
            if (Schema::hasTable('groups')) {
                // Ambil semua grup
                // OPTION 1: Jika grup punya relasi members
                if (method_exists(Group::class, 'members')) {
                    $userGroupIds = Group::whereHas('members', function($query) use ($userId) {
                        $query->where('user_id', $userId);
                    })->pluck('id');
                    
                    \Log::info('User Group IDs (from members relation): ' . $userGroupIds->toJson());
                }
                // OPTION 2: Jika grup punya kolom participants (JSON)
                elseif (Schema::hasColumn('groups', 'participants')) {
                    $userGroupIds = Group::whereJsonContains('participants', $userId)
                        ->orWhere('owner_id', $userId)
                        ->pluck('id');
                    
                    \Log::info('User Group IDs (from participants): ' . $userGroupIds->toJson());
                }
                // OPTION 3: Jika grup punya owner saja
                else {
                    $userGroupIds = Group::where('owner_id', $userId)->pluck('id');
                    
                    \Log::info('User Group IDs (owned only): ' . $userGroupIds->toJson());
                }
                
                // Hitung pesan grup yang belum dibaca (exclude pesan sendiri)
                if (isset($userGroupIds) && $userGroupIds->isNotEmpty()) {
                    $unreadGroupMessages = GroupMessage::whereIn('group_id', $userGroupIds)
                        ->where('sender_id', '!=', $userId)
                        // Cek apakah kolom deleted_at ada
                        ->when(Schema::hasColumn('group_messages', 'deleted_at'), function($query) {
                            return $query->whereNull('deleted_at');
                        })
                        ->count();
                }
            } else {
                \Log::info('Groups table not found, skipping group messages count');
            }
            
            \Log::info('Unread Group Messages: ' . $unreadGroupMessages);
            
            $totalUnreadMessages = $unreadPersonalMessages + $unreadGroupMessages;
            
            // 3. Hitung total kontak personal
            $totalContacts = 0;
            
            // OPTION 1: Jika menggunakan tabel contacts
            if (Schema::hasTable('contacts')) {
                $totalContacts = DB::table('contacts')
                    ->where('user_id', $userId)
                    ->count();
                    
                \Log::info('Total Contacts (from contacts table): ' . $totalContacts);
            } 
            // OPTION 2: Jika menggunakan user_contacts pivot table
            elseif (Schema::hasTable('user_contacts')) {
                $totalContacts = DB::table('user_contacts')
                    ->where('user_id', $userId)
                    ->count();
                    
                \Log::info('Total Contacts (from user_contacts table): ' . $totalContacts);
            }
            // OPTION 3: Fallback ke perhitungan dari chat messages
            else {
                $contactIds = collect();
                
                // User yang pernah mengirim pesan ke kita
                $sendersQuery = ChatMessage::where('receiver_id', $userId);
                if (Schema::hasColumn('chat_messages', 'deleted_at')) {
                    $sendersQuery->whereNull('deleted_at');
                }
                $sendersToMe = $sendersQuery->distinct()->pluck('sender_id');
                
                // User yang pernah kita kirimi pesan
                $receiversQuery = ChatMessage::where('sender_id', $userId);
                if (Schema::hasColumn('chat_messages', 'deleted_at')) {
                    $receiversQuery->whereNull('deleted_at');
                }
                $receiversFromMe = $receiversQuery->distinct()->pluck('receiver_id');
                
                // Gabungkan dan unique
                $contactIds = $sendersToMe->merge($receiversFromMe)->unique();
                
                $totalContacts = $contactIds->count();
                
                \Log::info('Total Contacts (from chat messages): ' . $totalContacts);
                \Log::info('Contact IDs: ' . $contactIds->toJson());
            }
            
            // 4. Hitung total grup yang user ikuti
            $totalGroups = 0;
            
            if (Schema::hasTable('groups')) {
                if (method_exists(Group::class, 'members')) {
                    $totalGroups = Group::whereHas('members', function($query) use ($userId) {
                        $query->where('user_id', $userId);
                    })->count();
                } elseif (Schema::hasColumn('groups', 'participants')) {
                    $totalGroups = Group::whereJsonContains('participants', $userId)
                        ->orWhere('owner_id', $userId)
                        ->count();
                } else {
                    $totalGroups = Group::where('owner_id', $userId)->count();
                }
            }
            
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
                    ->when(Schema::hasColumn('chat_messages', 'deleted_at'), function($query) {
                        return $query->whereNull('deleted_at');
                    })
                    ->count(),
                'total_received' => ChatMessage::where('receiver_id', $userId)
                    ->when(Schema::hasColumn('chat_messages', 'deleted_at'), function($query) {
                        return $query->whereNull('deleted_at');
                    })
                    ->count(),
                'total_sent' => ChatMessage::where('sender_id', $userId)
                    ->when(Schema::hasColumn('chat_messages', 'deleted_at'), function($query) {
                        return $query->whereNull('deleted_at');
                    })
                    ->count(),
            ];
            
            // Group stats
            $groupStats = [
                'total_groups' => 0,
                'owned_groups' => 0,
                'total_messages' => 0,
                'unread_messages' => 0,
            ];
            
            if (Schema::hasTable('groups')) {
                // Get user groups
                if (method_exists(Group::class, 'members')) {
                    $userGroupIds = Group::whereHas('members', function($query) use ($userId) {
                        $query->where('user_id', $userId);
                    })->pluck('id');
                } elseif (Schema::hasColumn('groups', 'participants')) {
                    $userGroupIds = Group::whereJsonContains('participants', $userId)
                        ->orWhere('owner_id', $userId)
                        ->pluck('id');
                } else {
                    $userGroupIds = Group::where('owner_id', $userId)->pluck('id');
                }
                
                $groupStats = [
                    'total_groups' => $userGroupIds->count(),
                    'owned_groups' => Group::where('owner_id', $userId)->count(),
                    'total_messages' => GroupMessage::whereIn('group_id', $userGroupIds)
                        ->when(Schema::hasColumn('group_messages', 'deleted_at'), function($query) {
                            return $query->whereNull('deleted_at');
                        })
                        ->count(),
                    'unread_messages' => GroupMessage::whereIn('group_id', $userGroupIds)
                        ->where('sender_id', '!=', $userId)
                        ->when(Schema::hasColumn('group_messages', 'deleted_at'), function($query) {
                            return $query->whereNull('deleted_at');
                        })
                        ->count(),
                ];
            }
            
            // Contacts
            $contactIds = collect();
            
            $sendersQuery = ChatMessage::where('receiver_id', $userId);
            if (Schema::hasColumn('chat_messages', 'deleted_at')) {
                $sendersQuery->whereNull('deleted_at');
            }
            $sendersToMe = $sendersQuery->distinct()->pluck('sender_id');
            
            $receiversQuery = ChatMessage::where('sender_id', $userId);
            if (Schema::hasColumn('chat_messages', 'deleted_at')) {
                $receiversQuery->whereNull('deleted_at');
            }
            $receiversFromMe = $receiversQuery->distinct()->pluck('receiver_id');
            
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