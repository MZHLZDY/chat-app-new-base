<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\User; // Tambahan
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Database; // <--- INI YG PENTING (DARI PRIVATE CHAT)

class GroupController extends Controller
{
    protected $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function index()
    {
        $userId = Auth::id();
        $groups = Group::whereHas('members', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->withCount('members') 
        ->with(['latestMessage.sender']) 
        ->get()
        ->map(function ($group) use ($userId) {
            $latestMsg = $group->latestMessage;
            return [
                'id' => $group->id,
                'name' => $group->name,
                'photo' => $group->photo, 
                'members_count' => $group->members_count,
                'unread_count' => 0, 
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

        Group::where('id', $request->group_id)->update(['updated_at' => now()]);
        
        $message->load(['sender', 'replyTo.sender']);
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
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'photo' => $message->sender->photo,
                ],
                'reply_to' => $message->replyTo ? [
                    'id' => $message->replyTo->id,
                    'message' => $message->replyTo->message,
                    'sender' => [
                        'name' => $message->replyTo->sender->name ?? 'Unknown'
                    ]
                ] : null
            ]);

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
}