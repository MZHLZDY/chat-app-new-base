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
     * 1. GET CONTACTS LIST
     */
    public function getContacts()
    {
        $authId = Auth::id();
        
        // Update last_seen saya
        User::where('id', $authId)->update(['last_seen' => now()]);

        $contacts = User::leftJoin('contacts', function($join) use ($authId) {
            $join->on('users.id', '=', 'contacts.friend_id')
                 ->where('contacts.user_id', '=', $authId); 
        })
        ->where(function($query) use ($authId) {
            $query->whereNotNull('contacts.id') 
            ->orWhereIn('users.id', function($sub) use ($authId) {
                $sub->select('sender_id')->from('chat_messages')->where('receiver_id', $authId);
            })
            ->orWhereIn('users.id', function($sub) use ($authId) {
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
            'name'  => 'required|string',
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
            'user_id'   => $myId,
            'friend_id' => $targetUser->id,
            'alias'     => $request->name
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

        $messages = ChatMessage::where(function ($q) use ($myId, $friendId) {
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
     * 4. SEND MESSAGE (TEXT & FILE) - FIREBASE INTEGRATION
     */
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'message'     => 'nullable|string', 
            'text'        => 'nullable|string', 
            'file'        => 'nullable|file',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $messageText = $request->message ?? $request->text;

        if (!$messageText && !$request->hasFile('file')) {
            return response()->json(['error' => 'Pesan atau file tidak boleh kosong.'], 422);
        }

        $senderId = Auth::id();
        $messageData = [
            'sender_id'   => $senderId,
            'receiver_id' => $request->receiver_id,
            'message'     => $messageText,
            'type'        => 'text',
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName(); 
            $safeName = str_replace(' ', '_', $originalName);
            $path = $file->storeAs('chat_files', $safeName, 'public');
            $mime = $file->getMimeType();
            $type = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : 'file');

            $messageData['type']           = $type;
            $messageData['file_path']      = $path;
            $messageData['file_name']      = $originalName;
            $messageData['file_mime_type'] = $mime;
            $messageData['file_size']      = $file->getSize();
        }

        $message = ChatMessage::create($messageData);
        $message->load('sender');

        try {
            $payload = [
                'id' => (int) $message->id,
                'sender_id' => (int) $message->sender_id,
                'receiver_id' => (int) $message->receiver_id,
                'message' => $message->message ?? '',
                'type' => $message->type ?? 'text',
                'file_path' => $message->file_path ?? null,
                'file_name' => $message->file_name ?? null,
                'file_size' => $message->file_size ? (int) $message->file_size : null,
                'created_at' => $message->created_at->toIso8601String(),
                'read_at' => null,
                'sender' => [
                    'name' => $message->sender->name ?? '',
                    'photo' => $message->sender->photo ?? null,
                ]
            ];
            $this->database
            ->getReference("chats/{$request->receiver_id}")
            ->push($payload);
            
            \Log::info("Firebase message sent: {$uniqueKey}");
            
        } catch (\Exception $e) {
            \Log::error("Firebase error: " . $e->getMessage());
        }

        return response()->json($message, 201);
    }

    /**
     * 4. DOWNLOAD FILE
     */
    public function downloadFile($id)
    {
        $message = ChatMessage::findOrFail($id);
        if (!$message->file_path || !Storage::disk('public')->exists($message->file_path)) {
            return response()->json(['message' => 'File tidak ditemukan'], 404);
        }
        return Storage::disk('public')->download($message->file_path, $message->file_name);
    }

    /**
     * 5. DELETE MESSAGE
     */
    public function deleteMessage(Request $request, $id)
    {
        $user = Auth::user();
        $message = ChatMessage::findOrFail($id);
        $type = $request->input('type', 'me');

        if ($type === 'everyone') {
            if ($message->sender_id !== $user->id) {
                return response()->json(['error' => 'Hanya pengirim yang bisa menghapus.'], 403);
            }

            if ($message->file_path && Storage::disk('public')->exists($message->file_path)) {
                Storage::disk('public')->delete($message->file_path);
            }
            $message->delete();
            $this->database->getReference('notifications/' . $message->receiver_id)->push([
                'type' => 'message_deleted',
                'message_id' => $id
            ]);

            return response()->json(['message' => 'Pesan dihapus untuk semua orang']);

        } else {
            $deletedBy = $message->deleted_by_users ?? [];
            if (!in_array($user->id, $deletedBy)) {
                $deletedBy[] = $user->id;
                $message->deleted_by_users = $deletedBy;
                $message->save();
            }
            return response()->json(['message' => 'Pesan dihapus untuk saya']);
        }
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
        if($msg && !$msg->read_at) {
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
}