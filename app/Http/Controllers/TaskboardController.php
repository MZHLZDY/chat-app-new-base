<?php

namespace App\Http\Controllers;

use App\Models\TaskBoard;
use App\Models\User;
use App\Notifications\TodoDeadlineNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskBoardController extends Controller
{
    // ─── INDEX ────────────────────────────────────────────────────────────────
    /** Ambil semua board milik user atau yang user jadi member */
    public function index()
    {
        $userId = Auth::id();

        $boards = TaskBoard::where('user_id', $userId)
            ->orWhereHas('members', fn($q) => $q->where('users.id', $userId))
            ->with(['members:id,name,profile_photo_path'])
            ->withCount('todos')
            ->get();

        $boards->each(function ($board) {
            $board->members->each(function ($user) {
                $user->profile_photo_url = $user->profile_photo_path
                    ? asset('storage/' . $user->profile_photo_path)
                    : null;
            });
        });

        return response()->json($boards);
    }

    // ─── STORE ────────────────────────────────────────────────────────────────
    /** Buat board baru */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:10',
        ]);

        $board = TaskBoard::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#5e6ad2',
            'icon' => $validated['icon'] ?? '📋',
        ]);

        // Daftarkan pembuat sebagai owner
        $board->members()->attach(Auth::id(), ['role' => 'owner']);

        $board->load('members:id,name,profile_photo_path');

        return response()->json(['message' => 'Board berhasil dibuat', 'data' => $board], 201);
    }

    // ─── SHOW ─────────────────────────────────────────────────────────────────
    /** Detail board beserta todos-nya */
    public function show(TaskBoard $taskBoard)
    {
        $this->authorizeAccess($taskBoard);

        $taskBoard->load([
            'members:id,name,profile_photo_path',
            'todos.assignees:id,name,profile_photo_path',
            'todos.attachments',
        ]);

        return response()->json($taskBoard);
    }

    // ─── UPDATE ───────────────────────────────────────────────────────────────
    /** Edit nama/deskripsi/warna board */
    public function update(Request $request, TaskBoard $taskBoard)
    {
        $this->authorizeAccess($taskBoard, ownerOnly: true);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:10',
        ]);

        $taskBoard->update($validated);

        return response()->json(['message' => 'Board diperbarui', 'data' => $taskBoard]);
    }

    // ─── DESTROY ──────────────────────────────────────────────────────────────
    /** Hapus board beserta semua todo di dalamnya */
    public function destroy(TaskBoard $taskBoard)
    {
        $this->authorizeAccess($taskBoard, ownerOnly: true);

        $taskBoard->delete();

        return response()->json(['message' => 'Board dihapus']);
    }

    // ─── MEMBERS ──────────────────────────────────────────────────────────────
    /** Tambah member ke board */
    public function addMember(Request $request, TaskBoard $taskBoard)
    {
        $this->authorizeAccess($taskBoard, ownerOnly: true);

        $request->validate(['user_id' => 'required|exists:users,id']);

        $taskBoard->members()->syncWithoutDetaching([
            $request->user_id => ['role' => 'member']
        ]);

        // Kirim notifikasi ke user yang diundang
        $invitedUser = User::find($request->user_id);
        if ($invitedUser) {
            try {
                // Buat fake todo object untuk notifikasi
                // Bisa dikembangkan dengan notifikasi khusus board
                $invitedUser->notify(new \App\Notifications\BoardInviteNotification($taskBoard));
            } catch (\Exception $e) {
                logger()->warning('Board invite notification failed: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Member ditambahkan']);
    }

    /** Hapus member dari board */
    public function removeMember(TaskBoard $taskBoard, User $user)
    {
        $this->authorizeAccess($taskBoard, ownerOnly: true);

        // Owner tidak bisa dihapus dari board-nya sendiri
        if ($user->id === $taskBoard->user_id) {
            abort(422, 'Pemilik board tidak bisa dihapus');
        }

        $taskBoard->members()->detach($user->id);

        return response()->json(['message' => 'Member dihapus']);
    }

    // ─── HELPER ───────────────────────────────────────────────────────────────
    private function authorizeAccess(TaskBoard $board, bool $ownerOnly = false): void
    {
        $userId = Auth::id();

        if ($ownerOnly) {
            if ($board->user_id !== $userId) {
                abort(403, 'Hanya pemilik board yang bisa melakukan ini');
            }
            return;
        }

        $isMember = $board->members()->where('users.id', $userId)->exists();
        if ($board->user_id !== $userId && !$isMember) {
            abort(403, 'Unauthorized');
        }
    }
}