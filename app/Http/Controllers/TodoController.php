<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TodoController extends Controller
{
    /**
     * Menampilkan daftar tugas.
     * Bisa return HTML (Inertia) atau JSON (Axios) tergantung request.
     */
    public function index(Request $request)
    {
        $todos = Todo::where('user_id', Auth::id())
            ->latest()
            ->get();

        if ($request->wantsJson()) {
            return response()->json($todos);
        }

        return Inertia::render('Todo/Index', [
            'todos' => $todos
        ]);
    }

    /**
     * Menyimpan tugas baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $todo = Todo::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
        ]);

        return response()->json([
            'message' => 'Tugas berhasil disimpan',
            'data' => $todo
        ], 201);
    }

    /**
     * Mengupdate status (checklist) tugas.
     */
    public function update(Request $request, Todo $todo)
    {
        if ($todo->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ($request->has('title')) {
            $request->validate(['title' => 'required|string|max:255', 'description' => 'nullable|string']);
            $todo->update(['title' => $request->title, 'description' => $request->description]);
        } else {
            $todo->update(['is_completed' => !$todo->is_completed]);
        }

        return response()->json([
            'message' => 'Tugas berhasil diperbarui',
            'data' => $todo
        ]);
    }

    /**
     * Menghapus tugas.
     */
    public function destroy(Todo $todo)
    {
        if ($todo->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $todo->delete();

        return response()->json([
            'message' => 'Tugas dihapus'
        ]);
    }
}