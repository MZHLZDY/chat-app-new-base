<?php

namespace App\Http\Controllers;

use App\Models\TaskBoard;
use App\Models\Todo;
use App\Models\TodoActivityLog;
use App\Models\TodoAttachment;
use App\Models\User;
use App\Notifications\TodoDeadlineNotification;
use App\Notifications\TodoEscalationNotification;
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

        if (!$boardId) {
            return response()->json(['message' => 'board_id diperlukan'], 422);
        }

        $board    = TaskBoard::findOrFail($boardId);
        $isMember = $board->members()->where('users.id', $userId)->exists();
        if ($board->user_id !== $userId && !$isMember) {
            abort(403, 'Unauthorized');
        }

        $todos = Todo::where('board_id', $boardId)
            ->with(['assignees:id,name,email,profile_photo_path', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->get();

        $todos->each(fn($todo) => $todo->assignees->each(function ($user) {
            $user->profile_photo_url = $user->profile_photo_path
                ? asset('storage/' . $user->profile_photo_path)
                : null;
        }));

        return response()->json($todos);
    }

    // ─── STORE ────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'board_id'       => 'required|exists:task_boards,id',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'status'         => 'sometimes|in:todo,in_progress,done',
            'priority'       => 'sometimes|in:low,medium,high',
            'due_date'       => 'nullable|date',
            'assignee_ids'   => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        $board    = TaskBoard::findOrFail($validated['board_id']);
        $isMember = $board->members()->where('users.id', Auth::id())->exists();
        if ($board->user_id !== Auth::id() && !$isMember) abort(403, 'Unauthorized');

        $todo = Todo::create([
            'board_id'    => $validated['board_id'],
            'user_id'     => Auth::id(),
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status'      => $validated['status'] ?? 'todo',
            'priority'    => $validated['priority'] ?? 'medium',
            'due_date'    => $validated['due_date'] ?? null,
        ]);

        $syncData = [Auth::id() => ['role' => 'owner']];
        foreach ($validated['assignee_ids'] ?? [] as $uid) {
            if ($uid != Auth::id()) $syncData[$uid] = ['role' => 'member'];
        }
        $todo->assignees()->sync($syncData);
        $todo->load(['assignees:id,name,email,profile_photo_path', 'attachments']);

        TodoActivityLog::create([
            'todo_id' => $todo->id,
            'user_id' => Auth::id(),
            'action'  => 'created',
            'meta'    => ['title' => $todo->title],
        ]);

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

        $oldStatus = $todo->status;

        // Reset eskalasi jika deadline berubah
        if (array_key_exists('due_date', $validated)) {
            $validated['reminder_sent']            = false;
            $validated['overdue_escalation_level'] = 0;
            $validated['last_escalated_at']        = null;
            $validated['owner_notified']           = false;

            TodoActivityLog::create([
                'todo_id' => $todo->id,
                'user_id' => Auth::id(),
                'action'  => 'deadline_updated',
                'meta'    => ['due_date' => $validated['due_date']],
            ]);
        }

        // Reset eskalasi jika mulai dikerjakan / selesai
        if (isset($validated['status']) && in_array($validated['status'], ['in_progress', 'done'])) {
            $validated['overdue_escalation_level'] = 0;
            $validated['last_escalated_at']        = null;
            $validated['owner_notified']           = false;
        }

        $todo->update($validated);
        $todo->load(['assignees:id,name,email,profile_photo_path', 'attachments']);

        if (isset($validated['status']) && $oldStatus !== $validated['status']) {
            TodoActivityLog::create([
                'todo_id' => $todo->id,
                'user_id' => Auth::id(),
                'action'  => 'status_changed',
                'meta'    => ['old_status' => $oldStatus, 'new_status' => $validated['status']],
            ]);
        }

        return response()->json(['message' => 'Tugas diperbarui', 'data' => $todo]);
    }

    // ─── DESTROY ──────────────────────────────────────────────────────────────
    public function destroy(Todo $todo)
    {
        $this->authorizeAccess($todo, ownerOnly: true);

        foreach ($todo->attachments as $att) {
            if ($att->path) Storage::disk('public')->delete($att->path);
        }
        $todo->delete();

        return response()->json(['message' => 'Tugas dihapus']);
    }

    // ─── ASSIGNEES ────────────────────────────────────────────────────────────
    public function addAssignee(Request $request, Todo $todo)
    {
        $this->authorizeAccess($todo, ownerOnly: true);
        $request->validate(['user_id' => 'required|exists:users,id']);

        $todo->assignees()->syncWithoutDetaching([$request->user_id => ['role' => 'member']]);

        $invitedUser = User::find($request->user_id);
        if ($invitedUser) {
            try { $invitedUser->notify(new TodoDeadlineNotification($todo, 'invite')); }
            catch (\Exception $e) { logger()->warning('Invite notif gagal: ' . $e->getMessage()); }
        }

        TodoActivityLog::create([
            'todo_id' => $todo->id,
            'user_id' => Auth::id(),
            'action'  => 'assignee_added',
            'meta'    => ['user_id' => $request->user_id, 'name' => $invitedUser?->name],
        ]);

        return response()->json(['message' => 'Anggota ditambahkan']);
    }

    public function removeAssignee(Todo $todo, User $user)
    {
        $this->authorizeAccess($todo, ownerOnly: true);
        $todo->assignees()->detach($user->id);

        TodoActivityLog::create([
            'todo_id' => $todo->id,
            'user_id' => Auth::id(),
            'action'  => 'assignee_removed',
            'meta'    => ['user_id' => $user->id, 'name' => $user->name],
        ]);

        return response()->json(['message' => 'Anggota dihapus']);
    }

    // ─── REASSIGN ─────────────────────────────────────────────────────────────
    public function reassign(Request $request, Todo $todo)
    {
        $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'remove_old' => 'boolean',
            'note'       => 'nullable|string|max:500',
        ]);

        // Hanya owner board yang bisa reassign
        $board = $todo->board;
        if ($board->user_id !== Auth::id()) {
            abort(403, 'Hanya owner board yang bisa melakukan reassign');
        }

        $toUser    = User::findOrFail($request->to_user_id);
        $fromUsers = $todo->assignees;

        // Tambah assignee baru
        $todo->assignees()->syncWithoutDetaching([$request->to_user_id => ['role' => 'member']]);

        // Hapus assignee lama (kecuali owner todo & penerima baru)
        if ($request->boolean('remove_old', true)) {
            $todo->assignees()
                ->where('users.id', '!=', $todo->user_id)
                ->where('users.id', '!=', $request->to_user_id)
                ->detach();
        }

        // Reset eskalasi karena sudah ditangani
        $todo->update([
            'overdue_escalation_level' => 0,
            'last_escalated_at'        => null,
            'owner_notified'           => false,
        ]);

        // Notif ke penerima baru
        try {
            $toUser->notify(new TodoEscalationNotification(
                $todo, 'reassigned',
                reassignedTo: $toUser,
                reassignedBy: Auth::user(),
            ));
        } catch (\Exception $e) { logger()->warning($e->getMessage()); }

        // Notif ke assignee lama
        foreach ($fromUsers as $oldUser) {
            if ($oldUser->id !== $request->to_user_id && $oldUser->id !== Auth::id()) {
                try {
                    $oldUser->notify(new TodoEscalationNotification(
                        $todo, 'reassigned',
                        reassignedTo: $toUser,
                        reassignedBy: Auth::user(),
                    ));
                } catch (\Exception $e) { logger()->warning($e->getMessage()); }
            }
        }

        TodoActivityLog::create([
            'todo_id' => $todo->id,
            'user_id' => Auth::id(),
            'action'  => 'reassigned',
            'meta'    => [
                'to_user_id' => $toUser->id,
                'to_name'    => $toUser->name,
                'note'       => $request->note,
            ],
        ]);

        $todo->load(['assignees:id,name,email,profile_photo_path', 'attachments']);
        return response()->json(['message' => "Tugas dialihkan ke {$toUser->name}", 'data' => $todo]);
    }

    // ─── ACTIVITY LOG ─────────────────────────────────────────────────────────
    public function activityLog(Todo $todo)
    {
        $this->authorizeAccess($todo);

        $logs = TodoActivityLog::where('todo_id', $todo->id)
            ->with('user:id,name,profile_photo_path')
            ->latest()
            ->get()
            ->map(fn($log) => [
                'id'         => $log->id,
                'action'     => $log->action,
                'label'      => $log->readable_label,
                'meta'       => $log->meta,
                'user'       => $log->user ? [
                    'id'    => $log->user->id,
                    'name'  => $log->user->name,
                    'photo' => $log->user->profile_photo_path
                        ? asset('storage/' . $log->user->profile_photo_path)
                        : null,
                ] : null,
                'created_at' => $log->created_at,
            ]);

        return response()->json($logs);
    }

    // ─── ATTACHMENTS ──────────────────────────────────────────────────────────
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

        if ($request->type === 'file') {
            $file = $request->file('file');
            $path = $file->store("todo-attachments/{$todo->id}", 'public');
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

    public function destroyAttachment(TodoAttachment $attachment)
    {
        $todo = $attachment->todo;
        $this->authorizeAccess($todo);
        if ($attachment->path) Storage::disk('public')->delete($attachment->path);
        $attachment->delete();
        return response()->json(['message' => 'Lampiran dihapus']);
    }

    // ─── HELPER ───────────────────────────────────────────────────────────────
    private function authorizeAccess(Todo $todo, bool $ownerOnly = false): void
    {
        $userId = Auth::id();
        if ($ownerOnly) {
            if ($todo->user_id !== $userId) abort(403, 'Unauthorized');
            return;
        }
        $isAssigned = $todo->assignees()->where('users.id', $userId)->exists();
        if ($todo->user_id !== $userId && !$isAssigned) abort(403, 'Unauthorized');
    }
}