<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Database;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;

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
        if ($messageType === 'image')
            $previewMessage = '📷 Mengirim gambar';
        if ($messageType === 'file')
            $previewMessage = '📁 Mengirim berkas';

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
            'name' => 'required|string|max:100',
            'member_ids' => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $group = Group::create([
                'name' => $request->name,
                'admin_id' => Auth::id(),
                'photo' => null,
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
            ->with([
                'members' => function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                }
            ])
            ->withCount('members')
            ->with(['latestMessage.sender'])
            ->get()
            ->map(function ($group) use ($userId) {
                $latestMsg = $group->latestMessage;
                $currentUser = $group->members->first();
                $lastReadAt = $currentUser ? $currentUser->pivot->last_read_at : null;
                $lastClearedAt = $currentUser ? $currentUser->pivot->last_cleared_at : null;
                $cutoffTime = $lastReadAt;
                if ($lastClearedAt && (!$lastReadAt || $lastClearedAt > $lastReadAt)) {
                    $cutoffTime = $lastClearedAt;
                }
                $unreadQuery = GroupMessage::where('group_id', $group->id)
                    ->where('sender_id', '!=', $userId);

                if ($cutoffTime) {
                    $unreadQuery->where('created_at', '>', $cutoffTime);
                }
                $unreadQuery->where(function ($q) use ($userId) {
                    $q->whereNull('deleted_by')
                        ->orWhereJsonDoesntContain('deleted_by', $userId);
                });

                $unreadCount = $unreadQuery->count();

                return [
                    'id' => $group->id,
                    'last_cleared_at' => $lastClearedAt,
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
    public function getMessages(Request $request, $groupId)
    {
        $userId = Auth::id();
        $memberData = DB::table('group_user')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->first();

        if (!$memberData) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = GroupMessage::where('group_id', $groupId)
            ->with(['sender', 'replyTo.sender']);
        if (!empty($memberData->last_cleared_at)) {
            $query->where('created_at', '>', $memberData->last_cleared_at);
        }

        $query->orderBy('created_at', 'desc');
        $messages = $query->simplePaginate(20);
        $data = collect($messages->items())->reverse()->values();

        return response()->json([
            'data' => $data,
            'has_more' => $messages->hasMorePages(),
            'next_page_url' => $messages->nextPageUrl()
        ]);
    }

    // 2. SEND MESSAGE DENGAN FIREBASE PUSH (Realtime)
    public function sendMessage(Request $request)
    {
        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
            'message' => 'nullable|string',
            // Max 100MB (102400KB) agar video agak panjang bisa masuk
            'file' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,mp4,mov,avi,mkv|max:102400',
            'reply_to_id' => 'nullable|exists:group_messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }
        if (!$request->message && !$request->hasFile('file')) {
            return response()->json(['message' => 'Pesan atau file tidak boleh kosong'], 422);
        }

        try {
            DB::beginTransaction();

            $sender = Auth::user();
            $group = Group::find($request->group_id);

            // Cek Keanggotaan
            if (!$group->members()->where('users.id', $sender->id)->exists()) {
                return response()->json(['message' => 'Anda bukan anggota grup ini'], 403);
            }

            $filePath = null;
            $fileName = null;
            $fileMime = null;
            $fileSize = null;
            $msgType = 'text';

            // 2. PROSES FILE
            if ($request->hasFile('file')) {
                $file = $request->file('file');

                if (!$file->isValid()) {
                    throw new \Exception('File korup atau gagal upload');
                }

                $originalName = $file->getClientOriginalName();
                $fileName = $originalName;
                $fileMime = $file->getMimeType();
                $fileSize = $file->getSize();
                $extension = strtolower($file->getClientOriginalExtension());

                // --- PERBAIKAN UTAMA DI SINI (VIDEO) ---
                if (str_starts_with($fileMime, 'video/')) {
                    // HAPUS FFMPEG COMPRESSION.
                    // Langsung simpan file asli agar orientasi HP (Portrait) aman & upload cepat.
                    set_time_limit(0);

                    $physicalName = 'grp_vid_' . time() . '_' . Str::random(10) . '.' . $extension;

                    // Simpan path relatif ke storage public
                    $filePath = $file->storeAs('group_files', $physicalName, 'public');

                    $msgType = 'video';
                }
                // --- LOGIC IMAGE (Mempertahankan logic WebP Anda) ---
                elseif (str_starts_with($fileMime, 'image/')) {
                    $physicalName = 'grp_img_' . time() . '_' . Str::random(10) . '.webp';
                    $path = 'group_files/' . $physicalName;

                    try {
                        // Gunakan Intervention Image (sesuai kode Anda)
                        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                        $image = $manager->read($file);

                        // Scale down aman, tidak mengubah rasio aspek gepeng
                        $image->scaleDown(width: 1280);

                        Storage::disk('public')->put($path, (string) $image->toWebp(quality: 80));

                        $filePath = $path;
                        $fileMime = 'image/webp';
                        $fileSize = Storage::disk('public')->size($path);
                    } catch (\Exception $e) {
                        // Fallback jika gagal convert, simpan asli
                        $physicalName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
                        $filePath = $file->storeAs('group_files', $physicalName, 'public');
                    }
                    $msgType = 'image';
                }
                // --- LOGIC FILE LAINNYA ---
                else {
                    $physicalName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
                    $filePath = $file->storeAs('group_files', $physicalName, 'public');
                    $msgType = 'file';
                }

                // Update filesize final jika file tersimpan
                if ($filePath && Storage::disk('public')->exists($filePath)) {
                    $fileSize = Storage::disk('public')->size($filePath);
                }
            }

            // 3. SIMPAN KE DATABASE
            $message = GroupMessage::create([
                'group_id' => $group->id,
                'sender_id' => $sender->id,
                'message' => $request->message,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_mime_type' => $fileMime,
                'file_size' => $fileSize,
                'type' => $msgType,
                'created_at' => now(),
                'reply_to_id' => $request->reply_to_id,
            ]);

            $group->touch(); // Update updated_at grup

            DB::commit();

            // 4. PREPARE DATA RELASI
            // Load data sender & reply sender agar lengkap untuk Firebase
            $message->load(['sender', 'replyTo.sender']);

            // 5. PUSH KE FIREBASE
            $this->database->getReference('group_messages/' . $group->id)->push([
                'id' => $message->id,
                'group_id' => $message->group_id,
                'sender_id' => $message->sender_id,
                'message' => $message->message,
                'file_path' => $message->file_path,
                'file_name' => $message->file_name,
                'file_size' => $message->file_size,
                'type' => $message->type,
                'created_at' => $message->created_at->toIso8601String(),
                'reply_to_id' => $message->reply_to_id,
                'reply_to' => $message->replyTo ? [
                    'id' => $message->replyTo->id,
                    'message' => $message->replyTo->message,
                    'sender_id' => $message->replyTo->sender_id,
                    'sender' => [
                        'id' => $message->replyTo->sender->id ?? null,
                        'name' => $message->replyTo->sender->name ?? 'Unknown',
                    ]
                ] : null,
                'sender' => [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'photo' => $sender->avatar ?? $sender->photo ?? null, // Sesuaikan kolom DB Anda
                ]
            ]);

            // 6. NOTIFIKASI
            $notifMessage = $request->message;
            if (empty($notifMessage)) {
                if ($msgType === 'video')
                    $notifMessage = '🎥 Mengirim video';
                else if ($msgType === 'image')
                    $notifMessage = '📷 Mengirim foto';
                else if ($msgType === 'file')
                    $notifMessage = '📁 Mengirim berkas';
            }

            // Ambil member selain pengirim
            $members = $group->members()->where('users.id', '!=', $sender->id)->get();

            foreach ($members as $member) {
                $this->sendGroupNotification(
                    $member->id,
                    $group,
                    $sender,
                    $notifMessage,
                    $msgType
                );
            }

            return response()->json([
                'status' => 'success',
                'data' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    // 2. LEAVE GROUP
    public function leaveGroup($id)
    {
        $deleted = DB::table('group_user')
            ->where('group_id', $id)
            ->where('user_id', Auth::id())
            ->delete();

        if ($deleted)
            return response()->json(['message' => 'Berhasil keluar']);
        return response()->json(['message' => 'Gagal'], 400);
    }

    // 3. DOWNLOAD ATTACHMENT
    public function downloadAttachment($msgId)
    {
        $message = GroupMessage::findOrFail($msgId);
        if (!Storage::disk('public')->exists($message->file_path)) {
            return response()->json(['message' => 'File tidak ditemukan di server'], 404);
        }
        $fullPath = Storage::disk('public')->path($message->file_path);
        return response()->download($fullPath, $message->file_name);
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
        $group = Group::with([
            'members' => function ($query) {
                $query->select('users.id', 'users.name', 'users.email', 'users.photo', 'users.phone')
                    ->withPivot('is_admin');
            }
        ])->find($id);

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
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $group = Group::find($id);
        if (!$group)
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);

        $is_admin = $group->members()
            ->where('user_id', Auth::id())
            ->wherePivot('is_admin', true)
            ->exists();

        if (!$is_admin) {
            return response()->json(['message' => 'Hanya admin yang dapat mengubah info grup'], 403);
        }

        if ($request->has('name')) {
            $group->name = $request->name;
        }

        if ($request->hasFile('photo')) {
            if ($group->photo && $group->photo !== 'groups/default.png') {
                Storage::disk('public')->delete($group->photo);
            }

            $path = $request->file('photo')->store('groups', 'public');
            $group->photo = $path;
        }

        $group->save();

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
        if (!$group)
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);

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
        if (!$group)
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);

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
        $authId = Auth::id();

        $query = User::select('users.*', 'contacts.alias')
            ->leftJoin('contacts', function ($join) use ($authId) {
                $join->on('users.id', '=', 'contacts.friend_id')
                    ->where('contacts.user_id', '=', $authId);
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'LIKE', "%{$search}%")
                    ->orWhere('users.email', 'LIKE', "%{$search}%")
                    ->orWhere('contacts.alias', 'LIKE', "%{$search}%");
            });
        }
        if ($excludeGroupId) {
            $query->whereDoesntHave('groups', function ($q) use ($excludeGroupId) {
                $q->where('groups.id', $excludeGroupId);
            });
        }

        $query->where('users.id', '!=', $authId);

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

    // CLEAR GROUP CHAT
    public function clearChat($groupId)
    {
        $user = Auth::user();

        $isMember = DB::table('group_user')
            ->where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            DB::table('group_user')
                ->where('group_id', $groupId)
                ->where('user_id', $user->id)
                ->update(['last_cleared_at' => now()]);

            return response()->json([
                'status' => true,
                'message' => 'Riwayat chat berhasil dibersihkan (untuk Anda)'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal: ' . $e->getMessage()
            ], 500);
        }
    }
}