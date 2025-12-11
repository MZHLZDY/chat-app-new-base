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

class ChatController extends Controller
{
    /**
     * 1. GET CONTACTS LIST
     */
    public function getContacts()
    {
        $authId = Auth::id();

        $contacts = User::join('contacts', 'users.id', '=', 'contacts.friend_id')
            ->where('contacts.user_id', $authId)
            ->select('users.*', 'contacts.alias', 'contacts.created_at as contact_added_at')
            ->get();

        foreach ($contacts as $contact) {
            if ($contact->alias) {
                $contact->name = $contact->alias;
            }

            $lastMsg = ChatMessage::where(function ($q) use ($authId, $contact) {
                $q->where('sender_id', $authId)->where('receiver_id', $contact->id);
            })->orWhere(function ($q) use ($authId, $contact) {
                $q->where('sender_id', $contact->id)->where('receiver_id', $authId);
            })->latest()->first();

            $contact->latest_message = $lastMsg;
            
            $contact->unread_count = ChatMessage::where('sender_id', $contact->id)
                ->where('receiver_id', $authId)
                ->whereNull('read_at')
                ->count();
        }

        $sortedContacts = $contacts->sortByDesc(function ($contact) {
            return $contact->latest_message 
                ? $contact->latest_message->created_at 
                : $contact->contact_added_at;
        })->values();

        return response()->json(['data' => $sortedContacts]);
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
        $isNewUser = false;

        if (!$targetUser) {
            $targetUser = User::create([
                'name'     => $request->name, 
                'phone'    => $request->phone,
                'email'    => $request->phone . '@chat.com', 
                'password' => Hash::make('12345678'), 
                'uuid'     => (string) Str::uuid(),
            ]);
            $isNewUser = true;
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

        $msg = $isNewUser 
            ? 'User baru dibuat & ditambahkan! Password default: 12345678' 
            : 'Kontak berhasil ditemukan & disimpan.';

        return response()->json(['message' => $msg]);
    }

    /**
     * 3. GET MESSAGES
     */
    public function getMessages($friendId)
    {
        $myId = Auth::id();

        ChatMessage::where('sender_id', $friendId)
            ->where('receiver_id', $myId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = ChatMessage::where(function ($q) use ($myId, $friendId) {
            $q->where('sender_id', $myId)->where('receiver_id', $friendId);
        })->orWhere(function ($q) use ($myId, $friendId) {
            $q->where('sender_id', $friendId)->where('receiver_id', $myId);
        })
        ->orderBy('created_at', 'asc')
        ->get();

        return response()->json($messages);
    }

    /**
     * 4. SEND MESSAGE
     */
    public function sendMessage(Request $request)
    {
        // log request untuk lihat apa yang dikirim
        \Log::info('Chat send request:', $request->all());

        $validator = Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'message'     => 'nullable|string', 
            'text'        => 'nullable|string', 
            'file'        => 'nullable|file|max:10240',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json($validator->errors(), 422);
        }

        // ambil message dari 'message' atau 'text'
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
            $path = $file->store('chat_files', 'public');
            $mime = $file->getMimeType();
            
            $type = str_starts_with($mime, 'image/') ? 'image' : 'file';

            $messageData['type']           = $type;
            $messageData['file_path']      = $path;
            $messageData['file_name']      = $file->getClientOriginalName();
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
     * 5. DELETE MESSAGE
     */
    public function deleteMessage($id)
    {
        $message = ChatMessage::find($id);

        if (!$message) {
            return response()->json(['message' => 'Pesan tidak ditemukan'], 404);
        }

        if ($message->sender_id !== Auth::id()) {
            return response()->json(['message' => 'Tidak punya izin menghapus pesan ini'], 403);
        }

        if ($message->file_path) {
            Storage::disk('public')->delete($message->file_path);
        }
        
        broadcast(new MessageDeleted($message))->toOthers();

        return response()->json(['message' => 'Pesan berhasil dihapus']);
    }
}