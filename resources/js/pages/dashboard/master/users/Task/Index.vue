<script setup lang="ts">
import { ref, onMounted, computed, watch } from "vue";
import axios from "@/libs/axios";
import draggable from "vuedraggable";
import { toast } from "vue3-toastify";
import {
    Plus,
    Search,
    X,
    LayoutDashboard,
    Bell,
    Clock,
    CheckCircle2,
    Circle,
    Paperclip,
    Users,
    RefreshCw,
} from "lucide-vue-next";
import TodoForm from "./TodoForm.vue";
import TodoDetailModal from "./TodoDetailModal.vue";

// ─── TYPES ───────────────────────────────────────────────────────────────────
type ColumnKey = "todo" | "in_progress" | "done";

interface Assignee {
    id: number;
    name: string;
    email?: string;
    profile_photo_url?: string;
    pivot?: { role: string };
}

interface Attachment {
    id: number;
    type: "file" | "link";
    name: string;
    url?: string;
    mime_type?: string;
}

interface Todo {
    id: number;
    user_id: number;
    board_id: number;
    title: string;
    description?: string;
    status: ColumnKey;
    due_date?: string;
    priority?: "low" | "medium" | "high";
    assignees?: Assignee[];
    attachments?: Attachment[];
}

interface Board {
    id: number;
    user_id: number;
    name: string;
    description?: string;
    color: string;
    icon: string;
}

// ─── PROPS ───────────────────────────────────────────────────────────────────
const props = withDefaults(
    defineProps<{
        board?: Board;
    }>(),
    {
        board: () => ({
            id: 0,
            user_id: 0,
            name: "Board Tugas",
            color: "#5e6ad2",
            icon: "📋",
        }),
    }
);
const emit = defineEmits(["back"]);

// --- STATE ---
const allTodos = ref<Todo[]>([]);
const columns = ref<Record<ColumnKey, Todo[]>>({
    todo: [],
    in_progress: [],
    done: [],
});
const isLoading = ref(false);
const searchQuery = ref("");
const showCreateModal = ref(false);
const showDetailModal = ref(false);
const selectedTodo = ref<Todo | null>(null);
const createInColumn = ref<"todo" | "in_progress" | "done">("todo");
const isDragging = ref(false);

// --- KOLOM CONFIG ---
const columnConfig = [
    {
        key: "todo",
        label: "To Do",
        color: "#5e6ad2",
        lightColor: "#eef0ff",
        dotColor: "#5e6ad2",
        emptyIcon: "📋",
        emptyText: "Belum ada tugas",
    },
    {
        key: "in_progress",
        label: "In Progress",
        color: "#f59e0b",
        lightColor: "#fffbeb",
        dotColor: "#f59e0b",
        emptyIcon: "⚡",
        emptyText: "Tidak ada yang sedang dikerjakan",
    },
    {
        key: "done",
        label: "Done",
        color: "#10b981",
        lightColor: "#ecfdf5",
        dotColor: "#10b981",
        emptyIcon: "🎉",
        emptyText: "Belum ada yang selesai",
    },
];

// --- DATA LOADING ---
const fetchTodos = async () => {
    // Jika tidak ada board (standalone), jangan fetch
    if (!props.board?.id) return;
    isLoading.value = true;
    try {
        const res = await axios.get("/chat/todos", {
            params: { board_id: props.board.id },
        });
        allTodos.value = res.data.data ?? res.data;
        distributeTodos();
    } catch {
        toast.error("Gagal memuat tugas");
    } finally {
        isLoading.value = false;
    }
};

const distributeTodos = () => {
    columns.value = { todo: [], in_progress: [], done: [] };
    const q = searchQuery.value.toLowerCase();
    const filtered = q
        ? allTodos.value.filter(
              (t) =>
                  t.title.toLowerCase().includes(q) ||
                  t.description?.toLowerCase().includes(q)
          )
        : allTodos.value;
    filtered.forEach((t) => {
        const key = t.status as ColumnKey;
        if (columns.value[key]) columns.value[key].push(t);
        else columns.value["todo"].push(t);
    });
};

onMounted(fetchTodos);

watch(searchQuery, () => distributeTodos());

// --- DRAG & DROP ---
const onDragChange = async (
    evt: any,
    newStatus: "todo" | "in_progress" | "done"
) => {
    if (!evt.added) return;
    const todo = evt.added.element as Todo;
    if (todo.status === newStatus) return;

    const oldStatus = todo.status;
    todo.status = newStatus;

    try {
        await axios.put(`/chat/todos/${todo.id}`, { status: newStatus });
        toast.success(
            `Dipindah ke "${
                columnConfig.find((c) => c.key === newStatus)?.label
            }"`
        );
    } catch {
        todo.status = oldStatus;
        // kembalikan ke kolom lama
        (columns.value[oldStatus] as Todo[]).push(todo);
        columns.value[newStatus] = (columns.value[newStatus] as Todo[]).filter(
            (t) => t.id !== todo.id
        );
        toast.error("Gagal pindah status");
    }
};

// --- DEADLINE HELPERS ---
const deadlineInfo = (
    todo: Todo
): { status: string; label: string; color: string; bg: string } | null => {
    if (!todo.due_date) return null;
    const now = new Date();
    const due = new Date(todo.due_date);
    const diff = due.getTime() - now.getTime();

    if (todo.status === "done") {
        return {
            status: "done",
            label: "Selesai",
            color: "#10b981",
            bg: "#ecfdf5",
        };
    }
    if (diff < 0)
        return {
            status: "overdue",
            label: "Terlambat!",
            color: "#ef4444",
            bg: "#fef2f2",
        };
    if (diff < 3600000)
        return {
            status: "urgent",
            label: `${Math.floor(diff / 60000)}m lagi`,
            color: "#f97316",
            bg: "#fff7ed",
        };
    if (diff < 86400000) {
        const h = Math.floor(diff / 3600000);
        return {
            status: "soon",
            label: `${h}j lagi`,
            color: "#f59e0b",
            bg: "#fffbeb",
        };
    }
    return {
        status: "ok",
        label: due.toLocaleDateString("id-ID", {
            day: "numeric",
            month: "short",
        }),
        color: "#6b7280",
        bg: "#f3f4f6",
    };
};

// --- ACTIONS ---
const openCreate = (col: "todo" | "in_progress" | "done") => {
    createInColumn.value = col;
    showCreateModal.value = true;
};

const openDetail = (todo: Todo) => {
    selectedTodo.value = { ...todo };
    showDetailModal.value = true;
};

const onCreated = () => {
    showCreateModal.value = false;
    fetchTodos();
};

const onUpdated = () => {
    fetchTodos();
    if (selectedTodo.value) {
        // refresh selectedTodo
        const updated = allTodos.value.find(
            (t) => t.id === selectedTodo.value?.id
        );
        if (updated) selectedTodo.value = { ...updated };
    }
};

const onDeleted = () => {
    showDetailModal.value = false;
    selectedTodo.value = null;
    fetchTodos();
};

// --- STATS ---
const totalTodos = computed(() => allTodos.value.length);
const doneTodos = computed(
    () => allTodos.value.filter((t) => t.status === "done").length
);
const progressPercent = computed(() =>
    totalTodos.value
        ? Math.round((doneTodos.value / totalTodos.value) * 100)
        : 0
);
const overdueTodos = computed(
    () =>
        allTodos.value.filter((t) => {
            if (!t.due_date || t.status === "done") return false;
            return new Date(t.due_date) < new Date();
        }).length
);
</script>

<template>
    <div class="kanban-page">
        <!-- HEADER -->
        <div class="kanban-header">
            <div class="header-left">
                <!-- Tombol kembali ke daftar board -->
                <button
                    class="btn-back"
                    @click="emit('back')"
                    title="Kembali ke daftar board"
                >
                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                </button>
                <div
                    class="header-icon"
                    :style="{
                        background: `linear-gradient(135deg, ${props.board.color}, ${props.board.color}aa)`,
                    }"
                >
                    <span style="font-size: 1.4rem">{{
                        props.board.icon
                    }}</span>
                </div>
                <div>
                    <h1 class="header-title">{{ props.board.name }}</h1>
                    <p class="header-sub">
                        {{ doneTodos }}/{{ totalTodos }} tugas selesai
                        <span v-if="overdueTodos > 0" class="overdue-badge">
                            <Bell class="w-3 h-3" />
                            {{ overdueTodos }} terlambat
                        </span>
                    </p>
                </div>
            </div>

            <div class="header-right">
                <!-- Progress Bar -->
                <div class="progress-area" v-if="totalTodos > 0">
                    <span class="progress-label">{{ progressPercent }}%</span>
                    <div class="progress-track">
                        <div
                            class="progress-fill"
                            :style="{ width: progressPercent + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- Search -->
                <div class="search-wrap">
                    <Search class="search-ico" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="search-field"
                        placeholder="Cari tugas..."
                    />
                    <X
                        v-if="searchQuery"
                        class="search-clear"
                        @click="searchQuery = ''"
                    />
                </div>

                <!-- Refresh -->
                <button
                    class="btn-icon"
                    @click="fetchTodos"
                    :class="{ spinning: isLoading }"
                >
                    <RefreshCw class="w-4 h-4" />
                </button>

                <!-- Add Button -->
                <button class="btn-add-main" @click="openCreate('todo')">
                    <Plus class="w-4 h-4" />
                    <span>Tugas Baru</span>
                </button>
            </div>
        </div>

        <!-- LOADING -->
        <div v-if="isLoading && !allTodos.length" class="loading-state">
            <div class="spinner-border text-primary" role="status"></div>
            <p>Memuat tugas...</p>
        </div>

        <!-- KANBAN BOARD -->
        <div v-else class="kanban-board">
            <div
                v-for="col in columnConfig"
                :key="col.key"
                class="kanban-column"
            >
                <!-- Column Header -->
                <div
                    class="col-header"
                    :style="{ borderTop: `3px solid ${col.color}` }"
                >
                    <div class="col-header-left">
                        <span
                            class="col-dot"
                            :style="{ background: col.color }"
                        ></span>
                        <span class="col-title">{{ col.label }}</span>
                        <span
                            class="col-count"
                            :style="{
                                background: col.lightColor,
                                color: col.color,
                            }"
                        >
                            {{ columns[col.key]?.length ?? 0 }}
                        </span>
                    </div>
                    <button
                        class="col-add-btn"
                        @click="openCreate(col.key as any)"
                        :style="{ color: col.color }"
                        title="Tambah tugas"
                    >
                        <Plus class="w-4 h-4" />
                    </button>
                </div>

                <!-- Draggable Cards -->
                <draggable
                    :list="columns[col.key]"
                    group="todos"
                    item-key="id"
                    class="col-body"
                    :class="{ 'drag-over': isDragging }"
                    ghost-class="ghost-card"
                    drag-class="dragging-card"
                    @change="onDragChange($event, col.key as any)"
                    @start="isDragging = true"
                    @end="isDragging = false"
                >
                    <template #item="{ element: todo }">
                        <div class="todo-card" @click="openDetail(todo)">
                            <!-- Priority Stripe -->
                            <div
                                v-if="todo.priority"
                                class="priority-stripe"
                                :class="todo.priority"
                            ></div>

                            <!-- Card Body -->
                            <div class="card-inner">
                                <!-- Title -->
                                <p
                                    class="card-title"
                                    :class="{
                                        'done-text': todo.status === 'done',
                                    }"
                                >
                                    {{ todo.title }}
                                </p>

                                <!-- Description -->
                                <p v-if="todo.description" class="card-desc">
                                    {{ todo.description.slice(0, 80)
                                    }}{{
                                        todo.description.length > 80
                                            ? "..."
                                            : ""
                                    }}
                                </p>

                                <!-- Deadline Badge -->
                                <div
                                    v-if="todo.due_date && deadlineInfo(todo)"
                                    class="deadline-badge"
                                    :style="{
                                        color: deadlineInfo(todo)!.color,
                                        background: deadlineInfo(todo)!.bg,
                                    }"
                                >
                                    <Clock class="w-3 h-3" />
                                    <span>{{ deadlineInfo(todo)!.label }}</span>
                                </div>

                                <!-- Card Footer -->
                                <div class="card-footer">
                                    <!-- Assignees -->
                                    <div
                                        class="assignees-stack"
                                        v-if="todo.assignees?.length"
                                    >
                                        <div
                                            v-for="(
                                                a, i
                                            ) in todo.assignees.slice(0, 3)"
                                            :key="a.id"
                                            class="avatar-mini"
                                            :style="{ zIndex: 10 - Number(i) }"
                                            :title="a.name"
                                        >
                                            <img
                                                v-if="a.profile_photo_url"
                                                :src="a.profile_photo_url"
                                                :alt="a.name"
                                            />
                                            <span v-else>{{
                                                a.name[0].toUpperCase()
                                            }}</span>
                                        </div>
                                        <div
                                            v-if="todo.assignees.length > 3"
                                            class="avatar-mini avatar-more"
                                        >
                                            +{{ todo.assignees.length - 3 }}
                                        </div>
                                    </div>
                                    <div
                                        v-else
                                        class="placeholder-spacer"
                                    ></div>

                                    <!-- Attachment count -->
                                    <div
                                        v-if="todo.attachments?.length"
                                        class="attach-count"
                                        title="Lampiran"
                                    >
                                        <Paperclip class="w-3 h-3" />
                                        <span>{{
                                            todo.attachments.length
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty Column -->
                    <template #footer>
                        <div
                            v-if="!columns[col.key]?.length"
                            class="empty-col"
                            :style="{ color: col.color + '80' }"
                        >
                            <span class="empty-emoji">{{ col.emptyIcon }}</span>
                            <span>{{ col.emptyText }}</span>
                        </div>
                    </template>
                </draggable>

                <!-- Add Card Button at bottom -->
                <button
                    class="add-card-bottom"
                    @click="openCreate(col.key as any)"
                >
                    <Plus class="w-3 h-3" />
                    Tambah tugas
                </button>
            </div>
        </div>

        <!-- MODALS -->
        <TodoForm
            :show="showCreateModal"
            :board-id="props.board.id"
            :default-status="createInColumn"
            @close="showCreateModal = false"
            @created="onCreated"
        />

        <TodoDetailModal
            v-if="selectedTodo"
            :show="showDetailModal"
            :todo="selectedTodo"
            @close="showDetailModal = false"
            @updated="onUpdated"
            @deleted="onDeleted"
        />
    </div>
</template>

<style scoped>
/* ===================== BASE ===================== */
.kanban-page {
    padding: 24px;
    min-height: 100vh;
    background: #f8f9fc;
    display: flex;
    flex-direction: column;
}

/* ===================== HEADER ===================== */
.kanban-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    gap: 16px;
    flex-wrap: wrap;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #5e6ad2, #8b5cf6);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    box-shadow: 0 4px 14px rgba(94, 106, 210, 0.4);
}

.icon-svg {
    width: 22px;
    height: 22px;
}

.header-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a2e;
    margin: 0;
}

.header-sub {
    font-size: 0.85rem;
    color: #6b7280;
    margin: 2px 0 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.overdue-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: #fef2f2;
    color: #ef4444;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 600;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.progress-area {
    display: flex;
    align-items: center;
    gap: 8px;
}

.progress-label {
    font-size: 0.85rem;
    font-weight: 700;
    color: #5e6ad2;
    min-width: 36px;
}

.progress-track {
    width: 100px;
    height: 6px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #5e6ad2, #10b981);
    border-radius: 10px;
    transition: width 0.5s ease;
}

.search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.search-ico {
    position: absolute;
    left: 10px;
    width: 16px;
    height: 16px;
    color: #9ca3af;
}

.search-field {
    background: #fff;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    padding: 8px 36px 8px 34px;
    font-size: 0.9rem;
    width: 200px;
    outline: none;
    transition: border-color 0.2s;
}

.search-field:focus {
    border-color: #5e6ad2;
}

.search-clear {
    position: absolute;
    right: 8px;
    width: 16px;
    height: 16px;
    color: #9ca3af;
    cursor: pointer;
}

.search-clear:hover {
    color: #ef4444;
}

.btn-back {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.15s;
    flex-shrink: 0;
}
.btn-back:hover {
    border-color: #5e6ad2;
    color: #5e6ad2;
    background: #eef0ff;
}
:global(.dark) .btn-back {
    background: #1e1e2d;
    border-color: #2b2b40;
    color: #9ca3af;
}
:global(.dark) .btn-back:hover {
    border-color: #5e6ad2;
    color: #818cf8;
    background: #16162a;
}

.btn-icon {
    width: 38px;
    height: 38px;
    border: 1.5px solid #e5e7eb;
    background: #fff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.2s;
}

.btn-icon:hover {
    border-color: #5e6ad2;
    color: #5e6ad2;
}

.btn-icon.spinning svg {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.btn-add-main {
    display: flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #5e6ad2, #8b5cf6);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 9px 18px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(94, 106, 210, 0.35);
}

.btn-add-main:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(94, 106, 210, 0.45);
}

/* ===================== LOADING ===================== */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 0;
    gap: 16px;
    color: #6b7280;
}

/* ===================== KANBAN BOARD ===================== */
.kanban-board {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    flex: 1;
    align-items: start;
}

/* ===================== COLUMN ===================== */
.kanban-column {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    display: flex;
    flex-direction: column;
    min-height: 400px;
    overflow: hidden;
    border: 1.5px solid #f1f5f9;
}

.col-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 16px 12px;
    background: #fafafa;
}

.col-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.col-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.col-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: #1a1a2e;
}

.col-count {
    padding: 1px 8px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 700;
}

.col-add-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    transition: background 0.2s;
}

.col-add-btn:hover {
    background: #f1f5f9;
}

/* ===================== DRAGGABLE AREA ===================== */
.col-body {
    flex: 1;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-height: 200px;
    transition: background 0.2s;
}

.col-body.drag-over {
    background: #f8faff;
}

/* ===================== TODO CARD ===================== */
.todo-card {
    background: #fff;
    border: 1.5px solid #f1f5f9;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.todo-card:hover {
    border-color: #5e6ad2;
    box-shadow: 0 4px 16px rgba(94, 106, 210, 0.12);
    transform: translateY(-2px);
}

.priority-stripe {
    width: 4px;
    flex-shrink: 0;
}

.priority-stripe.high {
    background: #ef4444;
}
.priority-stripe.medium {
    background: #f59e0b;
}
.priority-stripe.low {
    background: #10b981;
}

.card-inner {
    flex: 1;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.card-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: #1a1a2e;
    margin: 0;
    line-height: 1.4;
}

.card-title.done-text {
    text-decoration: line-through;
    color: #9ca3af;
}

.card-desc {
    font-size: 0.8rem;
    color: #9ca3af;
    margin: 0;
    line-height: 1.4;
}

.deadline-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    align-self: flex-start;
}

.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 4px;
}

.assignees-stack {
    display: flex;
    align-items: center;
}

.avatar-mini {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, #5e6ad2, #8b5cf6);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    margin-left: -6px;
    overflow: hidden;
    position: relative;
}

.avatar-mini:first-child {
    margin-left: 0;
}

.avatar-mini img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-more {
    background: #e5e7eb;
    color: #6b7280;
    font-size: 0.6rem;
}

.placeholder-spacer {
    height: 24px;
}

.attach-count {
    display: flex;
    align-items: center;
    gap: 3px;
    font-size: 0.78rem;
    color: #9ca3af;
}

/* ===================== EMPTY STATE ===================== */
.empty-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px 16px;
    gap: 6px;
    font-size: 0.82rem;
    font-weight: 500;
    text-align: center;
    border: 2px dashed currentColor;
    border-radius: 12px;
    opacity: 0.6;
}

.empty-emoji {
    font-size: 1.5rem;
}

/* ===================== ADD BOTTOM BUTTON ===================== */
.add-card-bottom {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 12px;
    border: none;
    background: transparent;
    color: #9ca3af;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border-top: 1.5px solid #f1f5f9;
}

.add-card-bottom:hover {
    background: #f8faff;
    color: #5e6ad2;
}

/* ===================== DRAG GHOST ===================== */
.ghost-card {
    opacity: 0.4;
    background: #eef0ff;
    border: 2px dashed #5e6ad2 !important;
}

.dragging-card {
    box-shadow: 0 10px 30px rgba(94, 106, 210, 0.25) !important;
    transform: rotate(2deg) !important;
}

/* ===================== RESPONSIVE ===================== */
@media (max-width: 1024px) {
    .kanban-board {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .kanban-page {
        padding: 12px;
    }
    .kanban-board {
        grid-template-columns: 1fr;
    }
    .kanban-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .progress-area {
        display: none;
    }
    .search-field {
        width: 140px;
    }
}

/* ===================== DARK MODE (DIPERBAIKI) ===================== */
:global([data-bs-theme="dark"]) .kanban-page {
    background: #0f0f1a;
}
:global([data-bs-theme="dark"]) .kanban-column {
    background: #1e1e2d;
    border-color: #2b2b40;
}
:global([data-bs-theme="dark"]) .col-header {
    background: #1a1a2e;
}
:global([data-bs-theme="dark"]) .col-title,
:global([data-bs-theme="dark"]) .header-title,
:global([data-bs-theme="dark"]) .card-title {
    color: #e5e7eb;
}
:global([data-bs-theme="dark"]) .todo-card {
    background: #1e1e2d;
    border-color: #2b2b40;
}
:global([data-bs-theme="dark"]) .todo-card:hover {
    border-color: #5e6ad2;
}
:global([data-bs-theme="dark"]) .search-field {
    background: #1e1e2d;
    border-color: #2b2b40;
    color: #e5e7eb;
}
:global([data-bs-theme="dark"]) .btn-icon {
    background: #1e1e2d;
    border-color: #2b2b40;
    color: #9ca3af;
}
:global([data-bs-theme="dark"]) .add-card-bottom {
    border-color: #2b2b40;
}
:global([data-bs-theme="dark"]) .add-card-bottom:hover {
    background: #2b2b40;
}
</style>
