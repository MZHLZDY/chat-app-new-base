<?php

namespace App\Http\Controllers;

use App\Models\AiMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class AiChatController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $messages = AiMessage::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->simplePaginate(20);

        $formattedData = collect($messages->items())->reverse()->values()->map(function ($msg) {
            return [
                'id' => $msg->id,
                'message' => $msg->message,
                'sender_type' => $msg->sender,
                'created_at' => $msg->created_at,
            ];
        });

        return response()->json([
            'data' => $formattedData,
            'has_more' => $messages->hasMorePages()
        ]);
    }

    // 2. Kirim Pesan & Minta Jawaban AI
    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $user = Auth::user();
        $userMessageText = $request->message;
        $userMsg = AiMessage::create([
            'user_id' => $user->id,
            'message' => $userMessageText,
            'sender' => 'user'
        ]);

        $botReplyText = $this->askGemini($userMessageText);
        $botMsg = AiMessage::create([
            'user_id' => $user->id,
            'message' => $botReplyText,
            'sender' => 'bot'
        ]);

        return response()->json([
            'user_message' => [
                'id' => $userMsg->id,
                'message' => $userMsg->message,
                'sender_type' => 'user',
                'created_at' => $userMsg->created_at
            ],
            'bot_message' => [
                'id' => $botMsg->id,
                'message' => $botMsg->message,
                'sender_type' => 'bot',
                'created_at' => $botMsg->created_at
            ]
        ]);
    }

    // --- FUNGSI INTEGRASI GEMINI ---
    private function askGemini($text)
    {
        $apiKey = env('GEMINI_API_KEY');

        $model = "gemini-2.5-flash";

        $systemInstruction = "Kamu adalah asisten AI yang adaptif dan seru. Tugasmu adalah menganalisis nada bicara pengguna terlebih dahulu:

            1. JIKA PERTANYAAN SERIUS (Contoh: Coding, Ilmu Pengetahuan, Berita, Tips Bisnis): 
               Jawablah dengan profesional, rinci, terstruktur, dan faktual. Gunakan bahasa Indonesia yang baik dan benar. Berikan solusi yang tuntas.

            2. JIKA PERTANYAAN SANTAI / BERCANDA (Contoh: Sapaan, Curhat, Tebak-tebakan, Ngeluh): 
               Jawablah dengan gaya santai, gaul, lucu, dan bersahabat. Boleh pakai emoji, bahasa slang (lo-gue), atau sarkas tipis yang menghibur. Jangan kaku seperti robot.

            Ingat, prioritas utamamu adalah menyesuaikan 'mood' dengan pengguna. Kalau ada yang bertanya tentang siapa pengembangmu, jawab saja 'Aku dikembangkan oleh tim yang keren!'. Sekarang, berikan jawaban terbaikmu sesuai instruksi di atas.";

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->withoutVerifying()->post($url, [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $systemInstruction . "\n\nUser: " . $text]
                                ]
                            ]
                        ]
                    ]);

            if ($response->successful()) {
                return $response->json()['candidates'][0]['content']['parts'][0]['text'];
            } else {
                // Log error untuk debugging yang lebih mudah
                \Log::error("Gemini Error: " . $response->body());
                return "Maaf, terjadi kesalahan pada AI. (Kode: {$response->status()})";
            }
        } catch (\Exception $e) {
            \Log::error("Server Error: " . $e->getMessage());
            return "Error Server: " . $e->getMessage();
        }
    }
}