<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\ChatMessage;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;
use Kreait\Firebase\Contract\Database;

class ChatController extends Controller
{

    protected $database;

    // Inject Firebase Database via Constructor
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     *  SEND NOTIFICATION TO FIREBASE
     */
    protected function sendNotification($receiverId, $senderId, $senderName, $message, $messageType = 'text')
    {
        $this->database->getReference('notifications/' . $receiverId)->push([
            'type' => 'new_message',
            'sender_id' => $senderId,
            'sender_name' => $senderName,
            'message' => $message,
            'message_type' => $messageType,
            'chat_id' => ($senderId < $receiverId) ? $senderId . '_' . $receiverId : $receiverId . '_' . $senderId, // Helper untuk redirect nanti
            'created_at' => now()->toIso8601String()
        ]);
    }

    /**
     * 1. GET CONTACTS LIST
     */
    public function getContacts()
    {
        $authId = Auth::id();

        // Update last_seen saya
        User::where('id', $authId)->update(['last_seen' => now()]);

        $contacts = User::leftJoin('contacts', function ($join) use ($authId) {
            $join->on('users.id', '=', 'contacts.friend_id')
                ->where('contacts.user_id', '=', $authId);
        })
            ->where(function ($query) use ($authId) {
                $query->whereNotNull('contacts.id')
                    ->orWhereIn('users.id', function ($sub) use ($authId) {
                        $sub->select('sender_id')->from('chat_messages')->where('receiver_id', $authId);
                    })
                    ->orWhereIn('users.id', function ($sub) use ($authId) {
                        $sub->select('receiver_id')->from('chat_messages')->where('sender_id', $authId);
                    });
            })
            ->where('users.id', '!=', $authId)
            ->select(
                'users.*',
                'contacts.alias',
                'contacts.created_at as contact_added_at'
            )
            ->get();

        foreach ($contacts as $contact) {
            if ($contact->alias) {
                $contact->display_name = $contact->alias;
                $contact->is_saved = true;
            } else {
                $contact->display_name = $contact->phone ? $contact->phone : "(Nomor Tidak Dikenal)";
                $contact->is_saved = false;
            }
            // Ambil pesan terakhir dari MySQL
            $lastMsg = ChatMessage::where(function ($q) use ($authId, $contact) {
                $q->where('sender_id', $authId)->where('receiver_id', $contact->id);
            })->orWhere(function ($q) use ($authId, $contact) {
                $q->where('sender_id', $contact->id)->where('receiver_id', $authId);
            })->orderBy('created_at', 'desc')->first();

            if ($lastMsg) {
                $contact->last_message = $lastMsg->message;
                $contact->last_message_time = $lastMsg->created_at;

                if ($lastMsg->sender_id !== $authId && !$lastMsg->read_at) {
                    $contact->unread_count = ChatMessage::where('sender_id', $contact->id)
                        ->where('receiver_id', $authId)
                        ->whereNull('read_at')
                        ->count();
                } else {
                    $contact->unread_count = 0;
                }
            } else {
                $contact->last_message = null;
                $contact->last_message_time = null;
                $contact->unread_count = 0;
            }
        }
        $contacts = $contacts->sortByDesc('last_message_time')->values();

        return response()->json($contacts);
    }

    /**
     * HEARTBEAT 
     */
    public function heartbeat()
    {
        if (Auth::check()) {
            User::where('id', Auth::id())->update(['last_seen' => now()]);
        }
        return response()->json(['status' => 'online']);
    }

    /**
     * 2. ADD CONTACT
     */
    public function addContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $myId = Auth::id();
        $targetUser = User::where('phone', $request->phone)->first();

        if (!$targetUser) {
            return response()->json(['message' => 'User dengan nomor ini belum terdaftar.'], 404);
        }
        if ($targetUser->id == $myId) {
            return response()->json(['message' => 'Tidak bisa menyimpan nomor sendiri.'], 422);
        }

        $exists = Contact::where('user_id', $myId)->where('friend_id', $targetUser->id)->exists();

        if ($exists) {
            return response()->json(['message' => 'Kontak ini sudah ada.'], 422);
        }

        Contact::create([
            'user_id' => $myId,
            'friend_id' => $targetUser->id,
            'alias' => $request->name
        ]);

        return response()->json(['message' => 'Kontak berhasil disimpan.']);
    }

    /**
     * 3. GET MESSAGES (Load History dari MySQL)
     */
    public function getMessages($friendId)
    {
        $myId = Auth::id();

        User::where('id', $myId)->update(['last_seen' => now()]);

        ChatMessage::where('sender_id', $friendId)
            ->where('receiver_id', $myId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $this->database->getReference('notifications/' . $friendId)->push([
            'type' => 'read_receipt',
            'reader_id' => $myId,
            'read_at' => now()->toIso8601String(),
        ]);

        $messages = ChatMessage::with(['replyTo.sender'])
            ->where(function ($q) use ($myId, $friendId) {
                $q->where('sender_id', $myId)->where('receiver_id', $friendId);
            })->orWhere(function ($q) use ($myId, $friendId) {
                $q->where('sender_id', $friendId)->where('receiver_id', $myId);
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->filter(function ($message) use ($myId) {
                $deletedBy = $message->deleted_by_users ?? [];
                return !in_array($myId, $deletedBy);
            })
            ->values();

        return response()->json($messages);
    }

    /**
     * 4. SEND MESSAGE (TEXT & FILE)
     */
    public function sendMessage(Request $request)
    {
        // 1. Validasi
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'message' => 'nullable|string',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,mp4,mov,avi,mkv|max:51200',
            'reply_to_id' => 'nullable|exists:chat_messages,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }
        if (!$request->message && !$request->hasFile('file')) {
            return response()->json(['message' => 'Pesan atau file tidak boleh kosong'], 422);
        }

        try {
            DB::beginTransaction();

            $senderId = Auth::id();
            $sender = Auth::user();

            $filePath = null;
            $fileName = null;
            $fileMime = null;
            $fileSize = null;
            $msgType = 'text';

            if ($request->hasFile('file')) {
                $file = $request->file('file');

                if (!$file->isValid()) {
                    return response()->json(['message' => 'File korup atau gagal upload'], 400);
                }

                $originalName = $file->getClientOriginalName();
                $fileName = $originalName;
                $fileMime = $file->getMimeType();
                $fileSize = $file->getSize();
                if (str_starts_with($fileMime, 'video/')) {
                    set_time_limit(0);
                    $ext = $file->getClientOriginalExtension();
                    $physicalName = 'vid_' . time() . '_' . Str::random(10) . '.' . $ext;
                    $filePath = $file->storeAs('chat_files', $physicalName, 'public');

                    if (Storage::disk('public')->exists($filePath)) {
                        $fileSize = Storage::disk('public')->size($filePath);
                    }

                    $msgType = 'video';
                }
                else {
                    $physicalName = time() . '_' . str_replace(' ', '_', $originalName);
                    $filePath = $file->storeAs('chat_files', $physicalName, 'public');

                    if (str_starts_with($fileMime, 'image/')) {
                        $msgType = 'image';
                    } else {
                        $msgType = 'file';
                    }
                }
            }

            // SIMPAN KE DB
            $message = ChatMessage::create([
                'sender_id' => $senderId,
                'receiver_id' => $request->receiver_id,
                'message' => $request->message,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_mime_type' => $fileMime,
                'file_size' => $fileSize,
                'type' => $msgType,
                'reply_to_id' => $request->reply_to_id,
                'created_at' => now(),
            ]);

            DB::commit();

            $message->load(['sender', 'replyTo.sender']);

            // KIRIM KE FIREBASE
            $firebaseData = [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'message' => $message->message,
                'file_path' => $message->file_path,
                'file_name' => $message->file_name,
                'file_size' => $message->file_size,
                'type' => $message->type,
                'created_at' => $message->created_at->toIso8601String(),
                'reply_to' => $message->replyTo ? [
                    'id' => $message->replyTo->id,
                    'message' => $message->replyTo->message,
                    'sender_id' => $message->replyTo->sender_id,
                    'type' => $message->replyTo->type
                ] : null,
            ];
            $this->database->getReference('chats/' . $request->receiver_id)->push($firebaseData);

            // NOTIFIKASI (TETAP SAMA)
            $notifMessage = $request->message;
            if (empty($notifMessage)) {
                if ($msgType === 'video') {
                    $notifMessage = '🎥 Mengirim video';
                } elseif ($msgType === 'image') {
                    $notifMessage = '📷 Mengirim foto';
                } elseif ($msgType === 'file') {
                    $notifMessage = '📁 Mengirim berkas';
                }
            }
            $this->sendNotification(
                $request->receiver_id,
                $sender->id,
                $sender->name,
                $notifMessage,
                $msgType
            );

            return response()->json([
                'status' => 'success',
                'data' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Server Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 4. DOWNLOAD FILE
     */
    public function download($id)
    {
        $message = ChatMessage::findOrFail($id);

        if (Auth::id() !== $message->sender_id && Auth::id() !== $message->receiver_id) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $relativePath = $message->file_path;
        $relativePath = ltrim($relativePath, '/');

        $fullPath = storage_path("app/public/{$relativePath}");

        if (!file_exists($fullPath)) {
            return response()->json([
                'message' => 'File fisik tidak ditemukan',
                'searched_path' => $fullPath
            ], 404);
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        return response()->download($fullPath, $message->file_name, [
            'Content-Type' => $message->file_mime_type ?? 'application/octet-stream',
            'Content-Length' => filesize($fullPath),
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * 5. DELETE MESSAGE
     */
    public function deleteMessage(Request $request, $id)
    {
        $userId = Auth::id();
        $message = ChatMessage::find($id);

        if (!$message) {
            return response()->json(['message' => 'Pesan tidak ditemukan'], 404);
        }
        $isDeleteForEveryone = $request->input('delete_for_everyone', false);

        if ($isDeleteForEveryone) {
            // --- LOGIC HAPUS UNTUK SEMUA ORANG ---
            if ($message->sender_id !== $userId) {
                return response()->json(['message' => 'Anda bukan pengirim pesan ini'], 403);
            }
            $message->delete();
            $this->database->getReference('notifications/' . $message->receiver_id)->push([
                'type' => 'message_deleted',
                'message_id' => (int) $id,
                'deleted_by' => $userId,
                'timestamp' => now()->timestamp
            ]);

        } else {
            // --- LOGIC HAPUS UNTUK DIRI SENDIRI ---
            $deletedBy = $message->deleted_by_users ?? [];
            if (!in_array($userId, $deletedBy)) {
                $deletedBy[] = $userId;
                $message->deleted_by_users = $deletedBy;
                $message->save();
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * 6. SHOW CONTACT
     */
    public function showContact($friendId)
    {
        $myId = Auth::id();
        $friend = User::findOrFail($friendId);
        $contact = Contact::where('user_id', $myId)->where('friend_id', $friendId)->first();

        return response()->json([
            'id' => $friend->id,
            'name' => $friend->name,
            'alias' => $contact ? $contact->alias : null,
            'email' => $friend->email,
            'phone' => $friend->phone,
            'photo' => $friend->photo,
        ]);
    }

    /**
     * 7. UPDATE CONTACT
     */
    public function updateContact(Request $request, $friendId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        Contact::updateOrCreate(
            ['user_id' => Auth::id(), 'friend_id' => $friendId],
            ['alias' => $request->name, 'updated_at' => now()]
        );

        return response()->json(['message' => 'Kontak berhasil diperbarui']);
    }

    /**
     * 8. MARK MESSAGE AS READ
     */
    public function markAsRead($id)
    {
        $msg = ChatMessage::find($id);
        if ($msg && !$msg->read_at) {
            $msg->update(['read_at' => now()]);

            $this->database->getReference('notifications/' . $msg->sender_id)->push([
                'type' => 'read_receipt',
                'reader_id' => Auth::id(),
                'message_id' => $id,
                'read_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * 9. CLEAR CHAT (Hapus Riwayat untuk Diri Sendiri)
     */
    public function clearChat($friendId)
    {
        $myId = Auth::id();

        $messages = ChatMessage::where(function ($q) use ($myId, $friendId) {
            $q->where('sender_id', $myId)->where('receiver_id', $friendId);
        })->orWhere(function ($q) use ($myId, $friendId) {
            $q->where('sender_id', $friendId)->where('receiver_id', $myId);
        })->get();

        foreach ($messages as $message) {
            $deletedBy = $message->deleted_by_users ?? [];
            if (!in_array($myId, $deletedBy)) {
                $deletedBy[] = $myId;
                $message->deleted_by_users = $deletedBy;
                $message->save();
            }
        }

        return response()->json(['message' => 'Chat berhasil dibersihkan']);
    }
}