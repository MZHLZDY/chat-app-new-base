<script setup lang="ts">
import { ref, onMounted, watch, computed } from "vue";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import { themeMode } from "@/layouts/default-layout/config/helper"; 
import {
    CheckCircle2,
    Circle,
    Trash2,
    ListTodo,
    Plus,
    Search,
    ClipboardList,
    X,
    AlertTriangle,
    Pencil,
    Trophy, // Ikon untuk progress bar
} from "lucide-vue-next";
import TodoForm from "./TodoForm.vue";
import EditTodo from "./EditTodo.vue";

// --- TIPE DATA ---
interface Todo {
    id: number;
    user_id: number;
    title: string;
    description?: string;
    is_completed: boolean;
}

type FilterType = "all" | "pending" | "completed";

// --- PROPS ---
const props = withDefaults(
    defineProps<{
        todos?: Todo[];
    }>(),
    {
        todos: () => [],
    }
);

// --- STATE ---
const currentThemeMode = computed(() => themeMode.value);
const todoList = ref<Todo[]>([]);
const searchQuery = ref("");
const activeFilter = ref<FilterType>("all");
const isLoading = ref(false);

// Modal States
const showModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);

// Data Selection
const todoToEdit = ref<Todo | null>(null);
const todoIdToDelete = ref<number | null>(null);
const isDeleting = ref(false);

// --- INITIAL LOAD ---
onMounted(() => {
    if (props.todos && props.todos.length > 0) {
        todoList.value = [...props.todos];
    }
    if (todoList.value.length === 0) {
        handleRefresh();
    }
});

watch(
    () => props.todos,
    (newVal) => {
        todoList.value = [...newVal];
    }
);

// --- COMPUTED ---
const filteredTodos = computed(() => {
    let result = todoList.value;

    if (activeFilter.value === "pending") {
        result = result.filter((t) => !t.is_completed);
    } else if (activeFilter.value === "completed") {
        result = result.filter((t) => t.is_completed);
    }

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (todo) =>
                todo.title.toLowerCase().includes(query) ||
                (todo.description &&
                    todo.description.toLowerCase().includes(query))
        );
    }

    // Sort: Belum selesai di atas, Selesai di bawah
    return result.sort(
        (a, b) => Number(a.is_completed) - Number(b.is_completed)
    );
});

const counts = computed(() => {
    const total = todoList.value.length;
    const completed = todoList.value.filter((t) => t.is_completed).length;
    const pending = total - completed;
    return { total, completed, pending };
});

// Hitung Progress Persentase
const progressPercentage = computed(() => {
    if (counts.value.total === 0) return 0;
    return Math.round((counts.value.completed / counts.value.total) * 100);
});

// --- ACTIONS ---

const handleRefresh = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get("/chat/todos");
        todoList.value = response.data.data
            ? response.data.data
            : response.data;
    } catch (error) {
        console.error("Gagal refresh:", error);
    } finally {
        isLoading.value = false;
    }
};

const toggleTodo = async (todo: Todo) => {
    const previousState = todo.is_completed;
    todo.is_completed = !todo.is_completed;
    try {
        await axios.put(`/chat/todos/${todo.id}`);
    } catch (err) {
        todo.is_completed = previousState;
        toast.error("Gagal update status");
    }
};

const openEditModal = (todo: Todo) => {
    todoToEdit.value = todo;
    showEditModal.value = true;
};

const confirmDelete = (id: number) => {
    todoIdToDelete.value = id;
    showDeleteModal.value = true;
};

const executeDelete = async () => {
    if (!todoIdToDelete.value) return;
    isDeleting.value = true;
    try {
        await axios.delete(`/chat/todos/${todoIdToDelete.value}`);
        todoList.value = todoList.value.filter(
            (t) => t.id !== todoIdToDelete.value
        );
        toast.success("Tugas dihapus");
        showDeleteModal.value = false;
    } catch (err) {
        toast.error("Gagal menghapus");
    } finally {
        isDeleting.value = false;
        todoIdToDelete.value = null;
    }
};

const clearSearch = () => {
    searchQuery.value = "";
};
</script>

<template>
    <div class="d-flex flex-column flex-column-fluid" :class="{ 'dark-mode': currentThemeMode === 'dark' }">
        <div id="kt_app_content" class="app-content flex-column-fluid">
            <div
                id="kt_app_content_container"
                class="app-container container-xxl"
            >
                <div class="card card-flush main-card shadow-sm">
                    <div class="card-header pt-7 pb-3">
                        <div class="card-title d-flex flex-column">
                            <div class="d-flex align-items-center gap-2">
                                <div class="symbol symbol-40px me-2">
                                    <div
                                        class="symbol-label bg-light-primary text-primary"
                                    >
                                        <ClipboardList class="w-6 h-6" />
                                    </div>
                                </div>
                                <span class="fs-2 fw-bold text-adaptive"
                                    >Daftar Tugas</span
                                >
                            </div>
                            <span
                                class="text-gray-700 mt-1 fw-semibold fs-7 ms-12"
                            >
                                {{ counts.pending }} tugas tersisa hari ini
                            </span>
                        </div>

                        <div class="card-toolbar gap-3">
                            <div class="search-box position-relative">
                                <Search class="search-icon text-gray-800" />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    class="form-control form-control-solid search-input"
                                    placeholder="Cari..."
                                />
                                <X
                                    v-if="searchQuery"
                                    @click="clearSearch"
                                    class="clear-icon text-gray-400 hover-text-danger"
                                />
                            </div>
                            <button
                                @click="showModal = true"
                                class="btn btn-primary btn-add fw-bold"
                            >
                                <Plus class="w-5 h-5" />
                                <span class="d-none d-sm-inline ms-1"
                                    >Baru</span
                                >
                            </button>
                        </div>
                    </div>

                    <div class="px-9 mb-6" v-if="counts.total > 0">
                        <div
                            class="d-flex justify-content-between align-items-center mb-2"
                        >
                            <span class="text-gray-500 fs-7 fw-bold"
                                >Progress Harian</span
                            >
                            <span class="text-primary fs-7 fw-bold"
                                >{{ progressPercentage }}%</span
                            >
                        </div>
                        <div
                            class="progress h-6px w-100 bg-light-primary rounded"
                        >
                            <div
                                class="progress-bar bg-primary rounded transition-all"
                                role="progressbar"
                                :style="{ width: progressPercentage + '%' }"
                            ></div>
                        </div>
                    </div>

                    <div class="card-body pt-0">
                        <div
                            class="d-flex gap-3 mb-6 pb-2 overflow-auto no-scrollbar"
                        >
                            <button
                                v-for="filter in ['all', 'pending', 'completed'] as const"
                                :key="filter"
                                @click="activeFilter = filter"
                                class="tab-btn"
                                :class="{ active: activeFilter === filter }"
                            >
                                {{
                                    filter === "all"
                                        ? "Semua"
                                        : filter === "pending"
                                        ? "Belum Selesai"
                                        : "Selesai"
                                }}
                                <span class="badge-count ms-1">{{
                                    filter === "all"
                                        ? counts.total
                                        : filter === "pending"
                                        ? counts.pending
                                        : counts.completed
                                }}</span>
                            </button>
                        </div>

                        <Transition name="fade" mode="out-in">
                            <div
                                v-if="isLoading && todoList.length === 0"
                                class="text-center py-10"
                            >
                                <div
                                    class="spinner-border text-primary"
                                    role="status"
                                ></div>
                            </div>

                            <div
                                v-else-if="todoList.length === 0"
                                class="empty-state"
                            >
                                <div class="icon-wrapper mb-4">
                                    <ListTodo class="w-12 h-12 text-gray-300" />
                                </div>
                                <h5>Belum ada tugas</h5>
                                <p>Yuk, mulai catat produktivitasmu!</p>
                                <button
                                    @click="showModal = true"
                                    class="btn btn-sm btn-light-primary mt-3"
                                >
                                    Buat Tugas Pertama
                                </button>
                            </div>

                            <div
                                v-else-if="filteredTodos.length === 0"
                                class="empty-state"
                            >
                                <Search class="w-10 h-10 text-gray-300 mb-3" />
                                <p>Tidak ditemukan hasil pencarian.</p>
                                <button
                                    @click="clearSearch"
                                    class="btn btn-sm btn-light mt-2"
                                >
                                    Reset
                                </button>
                            </div>

                            <div v-else>
                                <TransitionGroup
                                    name="list"
                                    tag="div"
                                    class="d-flex flex-column gap-3"
                                >
                                    <div
                                        v-for="todo in filteredTodos"
                                        :key="todo.id"
                                        class="todo-card"
                                        :class="{
                                            completed: todo.is_completed,
                                        }"
                                    >
                                        <div class="accent-border"></div>

                                        <div
                                            class="card-content d-flex align-items-start w-100 p-4"
                                        >
                                            <div
                                                class="checkbox-area mt-1 me-4 cursor-pointer"
                                                @click="toggleTodo(todo)"
                                            >
                                                <div
                                                    class="check-circle"
                                                    :class="{
                                                        checked:
                                                            todo.is_completed,
                                                    }"
                                                >
                                                    <CheckCircle2
                                                        v-if="todo.is_completed"
                                                        class="w-5 h-5 text-white"
                                                    />
                                                </div>
                                            </div>

                                            <div
                                                class="flex-grow-1 cursor-pointer"
                                                @click="toggleTodo(todo)"
                                            >
                                                <div
                                                    class="todo-title"
                                                    :class="{
                                                        'text-crossed':
                                                            todo.is_completed,
                                                    }"
                                                >
                                                    {{ todo.title }}
                                                </div>
                                                <div
                                                    v-if="todo.description"
                                                    class="todo-desc mt-1"
                                                    :class="{
                                                        'text-crossed-light':
                                                            todo.is_completed,
                                                    }"
                                                >
                                                    {{ todo.description }}
                                                </div>
                                            </div>

                                            <div class="actions-group ms-2">
                                                <button
                                                    @click.stop="
                                                        openEditModal(todo)
                                                    "
                                                    class="btn-icon-action edit"
                                                    title="Edit"
                                                >
                                                    <Pencil class="w-4 h-4" />
                                                </button>
                                                <button
                                                    @click.stop="
                                                        confirmDelete(todo.id)
                                                    "
                                                    class="btn-icon-action delete"
                                                    title="Hapus"
                                                >
                                                    <Trash2 class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </TransitionGroup>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </div>

        <TodoForm
            :show="showModal"
            @close="showModal = false"
            @refresh="handleRefresh"
        />
        <EditTodo
            :show="showEditModal"
            :todo="todoToEdit"
            @close="showEditModal = false"
            @refresh="handleRefresh"
        />

        <Teleport to="body">
            <div class="modal-wrapper-delete">
                <Transition name="fade">
                    <div
                        v-if="showDeleteModal"
                        class="modal-backdrop-delete"
                        @click="showDeleteModal = false"
                    ></div>
                </Transition>
                <Transition name="pop">
                    <div
                        v-if="showDeleteModal"
                        class="modal-content-delete shadow-lg"
                    >
                        <div class="text-center p-6">
                            <div class="warning-icon-wrapper mb-4">
                                <AlertTriangle class="w-10 h-10 text-danger" />
                            </div>
                            <h3 class="fw-bold text-dark mb-2">Hapus Tugas?</h3>
                            <p class="text-gray-500 fs-6 mb-6">
                                Tugas yang dihapus tidak bisa dikembalikan.
                            </p>
                            <div class="d-flex justify-content-center gap-3">
                                <button
                                    class="btn btn-light w-100 fw-bold"
                                    @click="showDeleteModal = false"
                                    :disabled="isDeleting"
                                >
                                    Batal
                                </button>
                                <button
                                    class="btn btn-danger w-100 fw-bold"
                                    @click="executeDelete"
                                    :disabled="isDeleting"
                                >
                                    {{ isDeleting ? "..." : "Hapus" }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
/* --- 1. GENERAL LAYOUT & CARD --- */
.main-card {
    border-radius: 20px;
    border: 1px solid rgba(0, 0, 0, 0.04);
    background: #ffffff;
    overflow: hidden;
}

.dark-mode .main-card {
    background: #1e1e2d;
}

/* --- 2. HEADER ELEMENTS --- */
.search-box {
    width: 220px;
    transition: width 0.3s;
}
.search-box:focus-within {
    width: 280px;
}

.search-input {
    padding-left: 38px;
    padding-right: 32px;
    border-radius: 12px;
    height: 40px;
    background-color: #f9f9f9;
    border: 1px solid transparent;
}
.search-input:focus {
    background-color: #fff;
    border-color: #009ef7;
}

.dark-mode .search-input {
    background: #3c3c41;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
}
.clear-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    cursor: pointer;
}
.btn-add {
    border-radius: 12px;
    height: 40px;
    display: flex;
    align-items: center;
    padding: 0 16px;
}

/* --- 3. TABS FILTER (PILL STYLE) --- */
.tab-btn {
    border: none;
    background: transparent;
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #7e8299;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    white-space: nowrap;
}
.tab-btn:hover {
    background-color: #f5f8fa;
    color: #009ef7;
}

.dark-mode .tab-btn:hover {
    background-color: #4a4f53;
}

.tab-btn.active {
    background-color: #009ef7;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(0, 158, 247, 0.3);
}
.badge-count {
    font-size: 0.75rem;
    background: rgba(255, 255, 255, 0.2);
    padding: 1px 6px;
    border-radius: 6px;
    margin-left: 6px;
}
.tab-btn:not(.active) .badge-count {
    background: #eee;
    color: #555;
}

/* --- 4. TODO CARD ITEM --- */
.todo-card {
    background: #ffffff;
    border-radius: 12px;
    position: relative;
    overflow: hidden;
    border: 1px solid #f1f1f4;
    transition: all 0.2s ease;
    display: flex;
}
.todo-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
    border-color: #e4e6ef;
}
.dark-mode .todo-card {
    background: #3c3c41;
    border: 1px solid #3c3c41;
}

.accent-border {
    width: 4px;
    background-color: #e4e6ef;
    transition: background-color 0.3s;
}
.dark-mode .accent-border{
    background-color: #8f9094;
}
.todo-card:hover .accent-border {
    background-color: #009ef7;
}
.todo-card.completed .accent-border {
    background-color: #50cd89;
}

.check-circle {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid #d1d5db;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.check-circle.checked {
    background-color: #50cd89;
    border-color: #50cd89;
    transform: scale(1.1);
}

.todo-title {
    font-weight: 600;
    font-size: 1rem;
    color: #3f4254;
    transition: color 0.2s;
}
.dark-mode .todo-title {
    color: #a4aacf;
}

.todo-desc {
    font-size: 0.85rem;
    color: #7e8299;
    line-height: 1.4;
}

.text-crossed {
    text-decoration: line-through;
    color: #a1a5b7;
}
.text-crossed-light {
    text-decoration: line-through;
    color: #b5b5c3;
}

/* Actions Buttons */
.actions-group {
    display: flex;
    gap: 8px;
    opacity: 0; /* Hidden by default */
    transform: translateX(10px);
    transition: all 0.2s ease;
}
.todo-card:hover .actions-group {
    opacity: 1;
    transform: translateX(0);
}
.btn-icon-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    cursor: pointer;
}
.btn-icon-action.edit {
    background: #fff8dd;
    color: #f6c000;
}
.dark-mode .btn-icon-action.edit {
    background: #3c3c41;
}
.btn-icon-action.edit:hover {
    background: #f6c000;
    color: #fff;
}
.btn-icon-action.delete {
    background: #fff5f8;
    color: #f1416c;
}
.dark-mode .btn-icon-action.delete {
    background: #3c3c41;
}
.btn-icon-action.delete:hover {
    background: #f1416c;
    color: #fff;
}

/* Mobile Support: Always show actions */
@media (max-width: 768px) {
    .actions-group {
        opacity: 1;
        transform: translateX(0);
    }
    .search-box {
        width: 100% !important;
    }
}

/* --- 5. EMPTY STATES --- */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}
.empty-state h5 {
    font-weight: 700;
    color: #3f4254;
    margin-bottom: 0.5rem;
}
.empty-state p {
    color: #7e8299;
    font-size: 0.95rem;
}
.icon-wrapper {
    background: #f9f9f9;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

/* --- 6. DARK MODE --- */
@media (prefers-color-scheme: dark) {
    .main-card,
    .todo-card {
        background-color: #1e1e2d;
        border-color: #2b2b40;
    }
    .text-adaptive {
        color: #ffffff !important;
    }
    .todo-title {
        color: #ffffff;
    }
    .todo-desc {
        color: #7272a0;
    }
    .search-input {
        background-color: #151521;
        color: #fff;
        border-color: #2b2b40;
    }
    .search-input:focus {
        background-color: #1b1b29;
    }
    .accent-border {
        background-color: #2b2b40;
    }
    .check-circle {
        border-color: #474761;
    }
    .modal-content-delete {
        background-color: #1e1e2d !important;
    }
    .warning-icon-wrapper {
        background-color: #2a121d !important;
    }
    .text-dark {
        color: #fff !important;
    }
    .tab-btn:hover {
        background-color: #2b2b40;
        color: #fff;
    }
}
:global(.dark) .main-card,
:global(.dark) .todo-card {
    background-color: #1e1e2d;
}
:global(.dark) .text-adaptive,
:global(.dark) .todo-title {
    color: #ffffff !important;
}

/* --- 7. TRANSITIONS --- */
.list-enter-active,
.list-leave-active {
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.list-enter-from {
    opacity: 0;
    transform: translateY(20px);
}
.list-leave-to {
    opacity: 0;
    transform: translateX(50px);
}
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.pop-enter-active {
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.pop-enter-from {
    opacity: 0;
    transform: scale(0.8);
}
.pop-leave-to {
    opacity: 0;
    transform: scale(0.9);
}

/* --- MODAL DELETE STYLES --- */
.modal-wrapper-delete {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9990;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}
.modal-backdrop-delete {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
    pointer-events: auto;
    z-index: 9991;
}
.modal-content-delete {
    z-index: 9995;
    background: #ffffff;
    width: 100%;
    max-width: 350px;
    border-radius: 20px;
    overflow: hidden;
    pointer-events: auto;
}
.warning-icon-wrapper {
    width: 70px;
    height: 70px;
    background-color: #fff5f8;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}
</style>
