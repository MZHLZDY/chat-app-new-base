<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\TodoComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TodoCommentController extends Controller
{
    /** Ambil semua komentar sebuah tugas */
    public function index(Todo $todo)
    {
        $this->authorizeAccess($todo);

        $comments = $todo->comments()
            ->with('user:id,name,profile_photo_path')
            ->latest()
            ->get()
            ->map(function ($c) {
                $c->user->profile_photo_url = $c->user->profile_photo_path
                    ? asset('storage/' . $c->user->profile_photo_path)
                    : null;
                return $c;
            });

        return response()->json($comments);
    }

    /** Tambah komentar baru */
    public function store(Request $request, Todo $todo)
    {
        $this->authorizeAccess($todo);

        $request->validate(['content' => 'required|string|max:2000']);

        $comment = TodoComment::create([
            'todo_id' => $todo->id,
            'user_id' => Auth::id(),
            'content' => $request->content,
        ]);

        $comment->load('user:id,name,profile_photo_path');
        $comment->user->profile_photo_url = $comment->user->profile_photo_path
            ? asset('storage/' . $comment->user->profile_photo_path)
            : null;

        return response()->json(['message' => 'Komentar ditambahkan', 'data' => $comment], 201);
    }

    /** Hapus komentar (hanya pemilik komentar) */
    public function destroy(Todo $todo, TodoComment $comment)
    {
        $this->authorizeAccess($todo);

        if ($comment->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $comment->delete();

        return response()->json(['message' => 'Komentar dihapus']);
    }

    private function authorizeAccess(Todo $todo): void
    {
        $userId = Auth::id();
        $isAssigned = $todo->assignees()->where('users.id', $userId)->exists();
        if ($todo->user_id !== $userId && !$isAssigned) {
            abort(403, 'Unauthorized');
        }
    }
}