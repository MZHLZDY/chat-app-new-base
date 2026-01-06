<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\User; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Database;

class GroupController extends Controller
{
    protected $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    // FUNGSI UNTUK KIRIM NOTIFIKASI KE ANGGOTA GRUP
    protected function sendGroupNotification($receiverId, $group, $sender, $message, $messageType = 'text')
    {
        $previewMessage = $message;
        if ($messageType === 'image') $previewMessage = '📷 Mengirim gambar';
        if ($messageType === 'file') $previewMessage = '📁 Mengirim berkas';

        $this->database->getReference('notifications/' . $receiverId)->push([
            'type' => 'new_group_message',     
            'group_id' => $group->id,            
            'group_name' => $group->name,       
            'sender_id' => $sender->id,
            'sender_name' => $sender->name,      
            'message' => $previewMessage,        
            'message_type' => $messageType,
            'created_at' => now()->timestamp
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'member_ids' => 'required|array|min:1', 
        ]);

        DB::beginTransaction();
        try {
            $group = Group::create([
                'name'     => $request->name,
                'admin_id' => Auth::id(), 
                'photo'    => null,
            ]);

            $group->members()->attach(Auth::id(), ['is_admin' => true]);

            $memberIds = collect($request->member_ids)
                ->diff([Auth::id()])
                ->unique();
            
            $group->members()->attach($memberIds, ['is_admin' => false]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Grup berhasil dibuat',
                'data' => $group
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function index()
    {
        $userId = Auth::id();
        $groups = Group::whereHas('members', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->with(['members' => function($q) use ($userId) {
            $q->where('user_id', $userId);
        }])
        ->withCount('members') 
        ->with(['latestMessage.sender']) 
        ->get()
        ->map(function ($group) use ($userId) {
            $latestMsg = $group->latestMessage;
            // --- LOGIKA UNREAD COUNT ---
            $currentUserPivot = $group->members->first(); 
            $lastReadAt = $currentUserPivot ? $currentUserPivot->pivot->last_read_at : null;
            $unreadCount = 0;
            if ($lastReadAt) {
                $unreadCount = GroupMessage::where('group_id', $group->id)
                    ->where('created_at', '>', $lastReadAt)
                    ->count();
            } else {
                $unreadCount = GroupMessage::where('group_id', $group->id)->count();
            }

            return [
                'id' => $group->id,
                'name' => $group->name,
                'photo' => $group->photo, 
                'members_count' => $group->members_count,
                'unread_count' => $unreadCount, 
                'last_message_sender_id' => $latestMsg ? $latestMsg->sender_id : null,
                'last_message_sender_name' => $latestMsg && $latestMsg->sender ? $latestMsg->sender->name : null,
                'last_message_preview' => $latestMsg ? ($latestMsg->message ?? ($latestMsg->type == 'image' ? 'Foto' : 'File')) : 'Belum ada pesan',
                'updated_at' => $group->updated_at,
            ];
        });

        return response()->json($groups->sortByDesc('updated_at')->values());
    }

    // 1. GET MESSAGES DARI FIREBASE
    public function getMessages($groupId)
    {
        $isMember = DB::table('group_user')
            ->where('group_id', $groupId)
            ->where('user_id', Auth::id())
            ->exists();

        if (!$isMember) return response()->json(['message' => 'Unauthorized'], 403);

        $messages = GroupMessage::where('group_id', $groupId)
            ->with(['sender', 'replyTo.sender'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['data' => $messages]);
    }

    // 2. SEND MESSAGE DENGAN FIREBASE PUSH (Realtime)
    public function sendMessage(Request $request)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'message' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
            'reply_to_id' => 'nullable|exists:group_messages,id'
        ]);

        if (!$request->message && !$request->hasFile('file')) {
            return response()->json(['message' => 'Pesan kosong'], 422);
        }

        $filePath = null;
        $fileName = null;
        $fileMime = null;
        $fileSize = null;
        $type = 'text';

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $fileMime = $file->getMimeType();
            $fileSize = $file->getSize();
            $type = str_starts_with($fileMime, 'image/') ? 'image' : 'file';
            $filePath = $file->store('chat_files', 'public');
        }

        // Simpan ke MySQL
        $message = GroupMessage::create([
            'group_id' => $request->group_id,
            'sender_id' => Auth::id(),
            'message' => $request->message,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_mime_type' => $fileMime,
            'file_size' => $fileSize,
            'type' => $type,
            'reply_to_id' => $request->reply_to_id,
        ]);

        // Load Group & Members
        $group = Group::with('members')->find($request->group_id);
        $group->update(['updated_at' => now()]);
        
        $message->load(['sender', 'replyTo.sender']);
        $sender = Auth::user();

        $this->database->getReference('group_messages/' . $request->group_id)
            ->push([
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'message' => $message->message,
                'type' => $message->type,
                'file_path' => $message->file_path, 
                'file_name' => $message->file_name,
                'file_size' => $message->file_size,
                'created_at' => $message->created_at->toIso8601String(),
                'sender' => [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'photo' => $sender->photo,
                ],
                'reply_to' => $message->replyTo ? [
                    'id' => $message->replyTo->id,
                    'message' => $message->replyTo->message,
                    'sender' => [
                        'name' => $message->replyTo->sender->name ?? 'Unknown'
                    ]
                ] : null
            ]);

        foreach ($group->members as $member) {
            if ($member->id == $sender->id) continue;
            $this->sendGroupNotification(
                $member->id, 
                $group, 
                $sender, 
                $message->message, 
                $type
            );
        }

        return response()->json(['data' => $message]);
    }

    // 2. LEAVE GROUP
    public function leaveGroup($id)
    {
        $deleted = DB::table('group_user')
            ->where('group_id', $id)
            ->where('user_id', Auth::id())
            ->delete();

        if ($deleted) return response()->json(['message' => 'Berhasil keluar']);
        return response()->json(['message' => 'Gagal'], 400);
    }
    
    // 3. DOWNLOAD ATTACHMENT
    public function downloadAttachment($msgId)
    {
        $message = GroupMessage::findOrFail($msgId);
        return Storage::disk('public')->download($message->file_path, $message->file_name);
    }

    // 4. DELETE MESSAGE
    public function deleteMessage(Request $request, $id)
    {
        $userId = Auth::id();
        $message = GroupMessage::find($id);

        if (!$message) {
            return response()->json(['message' => 'Pesan tidak ditemukan'], 404);
        }

        $isDeleteForEveryone = $request->input('delete_for_everyone', false);

        if ($isDeleteForEveryone) {
            // --- LOGIC HAPUS UNTUK SEMUA ORANG ---
            if ($message->sender_id !== $userId) {
                return response()->json(['message' => 'Anda bukan pengirim pesan ini'], 403);
            }

            $groupId = $message->group_id;
            $msgId = $message->id;
            $message->delete(); 
            $this->database->getReference('group_messages/' . $groupId)->push([
                'id' => 'del_' . time(), 
                'type' => 'delete_notify',
                'target_message_id' => $msgId,
                'deleted_by_user_id' => $userId,
                'timestamp' => now()->timestamp
            ]);

            return response()->json(['status' => 'success', 'message' => 'Pesan dihapus untuk semua orang']);

        } else {
            // --- LOGIC HAPUS UNTUK SAYA (LOCAL) ---
            $deletedBy = $message->deleted_by ?? [];
            if (!in_array($userId, $deletedBy)) {
                $deletedBy[] = $userId;
                $message->deleted_by = $deletedBy;
                $message->save();
            }

            return response()->json(['status' => 'success', 'message' => 'Pesan dihapus untuk Anda']);
        }
    }

    // DETAILS GROUP
    public function show($id)
    {
        $group = Group::with(['members' => function($query) {
            $query->select('users.id', 'users.name', 'users.email', 'users.photo', 'users.phone') 
                  ->withPivot('is_admin'); 
        }])->find($id);

        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        if (!$group->members->contains(Auth::id())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $group
        ]);
    }

    // UPDATE GROUP NAME
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $group = Group::find($id);
        if (!$group) return response()->json(['message' => 'Grup tidak ditemukan'], 404);

        $group->update(['name' => $request->name]);

        return response()->json([
            'success' => true,
            'message' => 'Nama grup diperbarui',
            'data' => $group
        ]);
    }

    // ADD MEMBERS (Fitur Tambah Anggota)
    public function addMembers(Request $request, $id)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $group = Group::find($id);
        if (!$group) return response()->json(['message' => 'Grup tidak ditemukan'], 404);

        // Tambahkan user tanpa menghapus member lama (syncWithoutDetaching)
        $group->members()->syncWithoutDetaching($request->user_ids);

        return response()->json([
            'success' => true,
            'message' => 'Anggota berhasil ditambahkan',
            'data' => $group->load('members')
        ]);
    }

    // REMOVE MEMBER (Fitur Kick / Keluar)
    public function removeMember($groupId, $userId)
    {
        $group = Group::find($groupId);
        if (!$group) return response()->json(['message' => 'Grup tidak ditemukan'], 404);

        // Hapus dari pivot table
        $group->members()->detach($userId);

        return response()->json([
            'success' => true,
            'message' => 'Anggota berhasil dikeluarkan'
        ]);
    }

    // SEARCH USERS (Untuk cari orang buat di-invite)
    public function searchUsers(Request $request)
    {
        $search = $request->query('q');
        $excludeGroupId = $request->query('exclude_group');

        $query = User::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Jangan tampilkan user yang SUDAH ada di grup ini
        if ($excludeGroupId) {
            $query->whereDoesntHave('groups', function($q) use ($excludeGroupId) {
                $q->where('groups.id', $excludeGroupId);
            });
        }
        
        // Jangan tampilkan diri sendiri
        $query->where('id', '!=', Auth::id());

        $users = $query->limit(10)->get();

        return response()->json(['data' => $users]);
    }

    // MARK GROUP AS READ
    public function markAsRead($groupId)
    {
        $userId = Auth::id();
        
        DB::table('group_user')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);

        return response()->json(['success' => true]);
    }
}