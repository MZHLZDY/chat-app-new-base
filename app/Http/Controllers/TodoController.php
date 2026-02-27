<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\TodoAttachment;
use App\Models\User;
use App\Notifications\TodoDeadlineNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TodoController extends Controller
{
    // ─── INDEX ────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $userId  = Auth::id();
        $boardId = $request->query('board_id');

        // board_id wajib disertakan
        if (!$boardId) {
            return response()->json(['message' => 'board_id diperlukan'], 422);
        }

        // Pastikan user adalah member atau owner board ini
        $board = \App\Models\TaskBoard::findOrFail($boardId);
        $isMember = $board->members()->where('users.id', $userId)->exists();
        if ($board->user_id !== $userId && !$isMember) {
            abort(403, 'Unauthorized');
        }

        $todos = Todo::where('board_id', $boardId)
            ->with(['assignees:id,name,email,profile_photo_path', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->get();

        $todos->each(function ($todo) {
            $todo->assignees->each(function ($user) {
                $user->profile_photo_url = $user->profile_photo_path
                    ? asset('storage/' . $user->profile_photo_path)
                    : null;
            });
        });

        return response()->json($todos);
    }

    // ─── STORE ────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'board_id'     => 'required|exists:task_boards,id',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'status'       => 'sometimes|in:todo,in_progress,done',
            'priority'     => 'sometimes|in:low,medium,high',
            'due_date'     => 'nullable|date',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        // Pastikan user adalah member board
        $board = \App\Models\TaskBoard::findOrFail($validated['board_id']);
        $isMember = $board->members()->where('users.id', Auth::id())->exists();
        if ($board->user_id !== Auth::id() && !$isMember) {
            abort(403, 'Unauthorized');
        }

        $todo = Todo::create([
            'board_id'    => $validated['board_id'],
            'user_id'     => Auth::id(),
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status'      => $validated['status'] ?? 'todo',
            'priority'    => $validated['priority'] ?? 'medium',
            'due_date'    => $validated['due_date'] ?? null,
        ]);

        // Sync assignees — owner selalu masuk
        $syncData = [Auth::id() => ['role' => 'owner']];
        if (!empty($validated['assignee_ids'])) {
            foreach ($validated['assignee_ids'] as $uid) {
                if ($uid != Auth::id()) {
                    $syncData[$uid] = ['role' => 'member'];
                }
            }
        }
        $todo->assignees()->sync($syncData);
        $todo->load(['assignees:id,name,email,profile_photo_path', 'attachments']);

        return response()->json(['message' => 'Tugas berhasil disimpan', 'data' => $todo], 201);
    }

    // ─── UPDATE ───────────────────────────────────────────────────────────────
    public function update(Request $request, Todo $todo)
    {
        $this->authorizeAccess($todo);

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:todo,in_progress,done',
            'priority'    => 'sometimes|in:low,medium,high',
            'due_date'    => 'nullable|date',
        ]);

        // Reset reminder_sent jika due_date berubah
        if (array_key_exists('due_date', $validated)) {
            $validated['reminder_sent'] = false;
        }

        $todo->update($validated);
        $todo->load(['assignees:id,name,email,profile_photo_path', 'attachments']);

        return response()->json(['message' => 'Tugas diperbarui', 'data' => $todo]);
    }

    // ─── DESTROY ──────────────────────────────────────────────────────────────
    public function destroy(Todo $todo)
    {
        $this->authorizeAccess($todo, ownerOnly: true);

        // Hapus semua file attachment dari storage
        foreach ($todo->attachments as $att) {
            if ($att->path) {
                Storage::disk('public')->delete($att->path);
            }
        }

        $todo->delete();

        return response()->json(['message' => 'Tugas dihapus']);
    }

    // ─── ASSIGNEES ────────────────────────────────────────────────────────────

    /** Tambah anggota ke tugas */
    public function addAssignee(Request $request, Todo $todo)
    {
        $this->authorizeAccess($todo, ownerOnly: true);

        $request->validate(['user_id' => 'required|exists:users,id']);

        $todo->assignees()->syncWithoutDetaching([
            $request->user_id => ['role' => 'member']
        ]);

        // Notifikasi via email ke user yang diundang
        $invitedUser = User::find($request->user_id);
        if ($invitedUser) {
            try {
                $invitedUser->notify(new TodoDeadlineNotification($todo, 'invite'));
            } catch (\Exception $e) {
                // Jangan gagalkan request jika notif error
                logger()->warning('Invite notification failed: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Anggota ditambahkan']);
    }

    /** Hapus anggota dari tugas */
    public function removeAssignee(Todo $todo, User $user)
    {
        $this->authorizeAccess($todo, ownerOnly: true);

        $todo->assignees()->detach($user->id);

        return response()->json(['message' => 'Anggota dihapus']);
    }

    /** Upload file atau tambah link */
    public function storeAttachment(Request $request)
    {
        $request->validate([
            'todo_id' => 'required|exists:todos,id',
            'type'    => 'required|in:file,link',
            'file'    => 'required_if:type,file|file|max:20480',
            'url'     => 'required_if:type,link|url',
            'name'    => 'nullable|string|max:255',
        ]);

        $todo = Todo::findOrFail($request->todo_id);
        $this->authorizeAccess($todo);

        $attachment = null;

        if ($request->type === 'file') {
            $file   = $request->file('file');
            $path   = $file->store("todo-attachments/{$todo->id}", 'public');
            $attachment = TodoAttachment::create([
                'todo_id'   => $todo->id,
                'user_id'   => Auth::id(),
                'type'      => 'file',
                'name'      => $request->name ?? $file->getClientOriginalName(),
                'path'      => $path,
                'url'       => asset('storage/' . $path),
                'mime_type' => $file->getMimeType(),
                'size'      => $file->getSize(),
            ]);
        } else {
            $attachment = TodoAttachment::create([
                'todo_id' => $todo->id,
                'user_id' => Auth::id(),
                'type'    => 'link',
                'name'    => $request->name ?? $request->url,
                'url'     => $request->url,
            ]);
        }

        return response()->json(['message' => 'Lampiran ditambahkan', 'data' => $attachment], 201);
    }

    /** Hapus lampiran */
    public function destroyAttachment(TodoAttachment $attachment)
    {
        $todo = $attachment->todo;
        $this->authorizeAccess($todo);

        if ($attachment->path) {
            Storage::disk('public')->delete($attachment->path);
        }
        $attachment->delete();

        return response()->json(['message' => 'Lampiran dihapus']);
    }

    // ─── HELPER ───────────────────────────────────────────────────────────────
    private function authorizeAccess(Todo $todo, bool $ownerOnly = false): void
    {
        $userId = Auth::id();

        if ($ownerOnly) {
            if ($todo->user_id !== $userId) {
                abort(403, 'Unauthorized');
            }
            return;
        }

        // owner atau assignee boleh akses
        $isAssigned = $todo->assignees()->where('users.id', $userId)->exists();
        if ($todo->user_id !== $userId && !$isAssigned) {
            abort(403, 'Unauthorized');
        }
    }
}