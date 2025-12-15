<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ChatMessage;
use App\Models\Contact;
use App\Events\MessageSent;
use App\Events\MessageRead;
use App\Events\MessageDeleted;
use App\Events\FileMessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;             
use Illuminate\Support\Facades\Hash;   
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver; 
use Carbon\Carbon;

class ChatController extends Controller
{
    /**
     * 1. GET CONTACTS LIST
     */
    public function getContacts()
    {
        $authId = Auth::id();
        
        // Update last_seen saya saat load kontak
        User::where('id', $authId)->update(['last_seen' => now()]);

        $contacts = User::join('contacts', 'users.id', '=', 'contacts.friend_id')
            ->where('contacts.user_id', $authId)
            ->select('users.*', 'contacts.alias', 'contacts.created_at as contact_added_at', 'users.last_seen')
            ->get();

        foreach ($contacts as $contact) {
            if ($contact->alias) {
                $contact->name = $contact->alias;
            }

            // Logic Last Message
            $lastMsg = ChatMessage::where(function ($q) use ($authId, $contact) {
                $q->where('sender_id', $authId)->where('receiver_id', $contact->id);
            })->orWhere(function ($q) use ($authId, $contact) {
                $q->where('sender_id', $contact->id)->where('receiver_id', $authId);
            })
            ->orderBy('created_at', 'desc')
            ->first();

            $contact->last_message = $lastMsg ? $lastMsg->message : null;
            $contact->last_message_time = $lastMsg ? $lastMsg->created_at : null;
            $isOnline = false;
            if ($contact->last_seen) {
                $lastSeen = Carbon::parse($contact->last_seen);
                if ($lastSeen->diffInMinutes(now()) < 2) {
                    $isOnline = true;
                }
            }
            $contact->is_online = $isOnline; 

            $unreadCount = ChatMessage::where('sender_id', $contact->id)
                ->where('receiver_id', $authId)
                ->whereNull('read_at')
                ->count();
            
            $contact->unread_count = $unreadCount;
        }
        $contacts = $contacts->sortByDesc(function ($contact) {
            if ($contact->unread_count > 0) return 2;
            if ($contact->last_message_time) return 1;
            return 0;
        })->values();

        return response()->json($contacts);
    }

    /**
     * HEARTBEAT (Update status online user setiap interval tertentu)
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
            return response()->json([
                'message' => 'User dengan nomor ini belum terdaftar di aplikasi.'
            ], 404);
        }
        if ($targetUser->id == $myId) {
            return response()->json(['message' => 'Tidak bisa menyimpan nomor sendiri.'], 422);
        }

        $exists = Contact::where('user_id', $myId)
                         ->where('friend_id', $targetUser->id)
                         ->exists();

        if ($exists) {
            return response()->json(['message' => 'Kontak ini sudah ada di daftar Anda.'], 422);
        }

        Contact::create([
            'user_id'   => $myId,
            'friend_id' => $targetUser->id,
            'alias'     => $request->name
        ]);

        return response()->json(['message' => 'Kontak berhasil ditemukan & disimpan.']);
    }

    /**
     * 3. GET MESSAGES
     */
    public function getMessages($friendId)
    {
        $myId = Auth::id();

        User::where('id', $myId)->update(['last_seen' => now()]);

        ChatMessage::where('sender_id', $friendId)
            ->where('receiver_id', $myId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        broadcast(new MessageRead($myId, $friendId))->toOthers();

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
     * 4. SEND MESSAGE
     */
    public function sendMessage(Request $request)
    {
        \Log::info('Chat send request:', $request->all());

        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'message'     => 'nullable|string', 
            'text'        => 'nullable|string', 
            'file'        => 'nullable|file',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
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
            $type = str_starts_with($mime, 'image/') ? 'image' : 'file';

            $messageData['type']           = $type;
            $messageData['file_path']      = $path;
            $messageData['file_name']      = $originalName;
            $messageData['file_mime_type'] = $mime;
            $messageData['file_size']      = $file->getSize();
        }
        $message = ChatMessage::create($messageData);
        
        $message->load('sender');

        if ($request->hasFile('file')) {
            broadcast(new FileMessageSent($message))->toOthers();
        } else {
            broadcast(new MessageSent($message))->toOthers();
        }

        return response()->json($message, 201);
    }

    /**
     * 4a. DOWNLOAD FILE MESSAGE
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
                return response()->json(['error' => 'Hanya pengirim yang bisa menghapus untuk semua'], 403);
            }

            if ($message->file_path && Storage::disk('public')->exists($message->file_path)) {
                Storage::disk('public')->delete($message->file_path);
            }
            $message->delete();
            
            broadcast(new MessageDeleted($message))->toOthers();

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
     * 6. SHOW CONTACT (Untuk mengisi Form saat Edit)
     */
    public function showContact($friendId)
    {
        $myId = Auth::id();

        $friend = User::findOrFail($friendId);
        $contact = Contact::where('user_id', $myId)
            ->where('friend_id', $friendId)
            ->first();

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
     * 7. UPDATE CONTACT (Simpan Perubahan Alias)
     */
    public function updateContact(Request $request, $friendId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $myId = Auth::id();

        User::findOrFail($friendId);
        Contact::updateOrCreate(
            [
                'user_id' => $myId,
                'friend_id' => $friendId
            ],
            [
                'alias' => $request->name, 
                'updated_at' => now()
            ]
        );

        return response()->json([
            'message' => 'Kontak berhasil diperbarui',
        ]);
    }
    public function markAsRead($id) 
    {
        $msg = ChatMessage::find($id);
        if($msg && !$msg->read_at) {
            $msg->update(['read_at' => now()]);
        }
        return response()->json(['success' => true]);
    }
}