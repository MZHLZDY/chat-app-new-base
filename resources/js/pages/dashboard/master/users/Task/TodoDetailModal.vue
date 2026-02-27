<script setup lang="ts">
import { ref, watch, computed } from "vue";
import {
    X,
    Pencil,
    AlignLeft,
    Calendar,
    Users,
    Paperclip,
    Link2,
    Upload,
    Trash2,
    Download,
    Search,
    Check,
    Clock,
    Flag,
    ChevronDown,
    ExternalLink,
    FileText,
    Image as ImageIcon,
    AlertTriangle,
} from "lucide-vue-next";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";

// --- TYPES ---
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
    path?: string;
    size?: number;
    mime_type?: string;
    created_at?: string;
}
interface Todo {
    id: number;
    user_id: number;
    title: string;
    description?: string;
    status: "todo" | "in_progress" | "done";
    due_date?: string;
    priority?: "low" | "medium" | "high";
    assignees?: Assignee[];
    attachments?: Attachment[];
}

// --- PROPS & EMITS ---
const props = defineProps<{
    show: boolean;
    todo: Todo;
}>();
const emit = defineEmits(["close", "updated", "deleted"]);

// --- STATE ---
const localTodo = ref<Todo>({ ...props.todo });
const isEditingTitle = ref(false);
const editTitle = ref("");
const editDesc = ref("");
const isSaving = ref(false);
const isDeleting = ref(false);
const showDeleteConfirm = ref(false);
const activeTab = ref<"detail" | "attachments" | "members" | "comments">(
    "detail"
);

// Deadline editing
const editDueDate = ref("");
const editDueTime = ref("");
const showDeadlinePicker = ref(false);

// Attachment
const fileInput = ref<HTMLInputElement | null>(null);
const linkUrl = ref("");
const linkName = ref("");
const showLinkForm = ref(false);
const isUploadingFile = ref(false);
const isAddingLink = ref(false);

// Assignees
const contactSearch = ref("");
const contacts = ref<Assignee[]>([]);
const isLoadingContacts = ref(false);
const showContactDropdown = ref(false);

// --- KOMENTAR ---
interface Comment {
    id: number;
    user_id: number;
    user: { id: number; name: string; profile_photo_url?: string };
    content: string;
    created_at: string;
}
const comments = ref<Comment[]>([]);
const newComment = ref("");
const isLoadingComments = ref(false);
const isPostingComment = ref(false);

const fetchComments = async () => {
    isLoadingComments.value = true;
    try {
        const res = await axios.get(
            `/chat/todos/${localTodo.value.id}/comments`
        );
        comments.value = res.data.data ?? res.data;
    } catch {
        comments.value = [];
    } finally {
        isLoadingComments.value = false;
    }
};

const postComment = async () => {
    if (!newComment.value.trim()) return;
    isPostingComment.value = true;
    try {
        const res = await axios.post(
            `/chat/todos/${localTodo.value.id}/comments`,
            {
                content: newComment.value,
            }
        );
        comments.value.unshift(res.data.data ?? res.data);
        newComment.value = "";
    } catch {
        toast.error("Gagal mengirim komentar");
    } finally {
        isPostingComment.value = false;
    }
};

const deleteComment = async (commentId: number) => {
    try {
        await axios.delete(
            `/chat/todos/${localTodo.value.id}/comments/${commentId}`
        );
        comments.value = comments.value.filter((c) => c.id !== commentId);
    } catch {
        toast.error("Gagal menghapus komentar");
    }
};

const formatCommentTime = (dateStr: string): string => {
    const d = parseDueDate(dateStr);
    return d.toLocaleString("id-ID", {
        timeZone: "Asia/Jakarta",
        day: "numeric",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
    });
};

// --- TIMEZONE HELPER ---
// Parse due_date dari server dengan benar sebagai WIB
// Server bisa kirim "2026-02-24 10:00:00" (tanpa tz) atau ISO dengan Z
const parseDueDate = (dateStr: string): Date => {
    // Jika sudah ada info timezone (Z atau +xx:xx), parse langsung
    if (dateStr.includes("Z") || dateStr.match(/[+-]\d{2}:\d{2}$/)) {
        return new Date(dateStr);
    }
    // Tidak ada timezone → anggap sebagai WIB (UTC+7)
    return new Date(dateStr.replace(" ", "T") + "+07:00");
};

// Format tanggal ke WIB 24 jam untuk display
const formatDateWIB = (dateStr: string): string => {
    const d = parseDueDate(dateStr);
    return d.toLocaleString("id-ID", {
        timeZone: "Asia/Jakarta",
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false, // Format 24 jam (WIB, bukan AM/PM)
    });
};

// Ambil date string (YYYY-MM-DD) dan time string (HH:MM) dalam WIB
const getWIBParts = (dateStr: string): { date: string; time: string } => {
    const d = parseDueDate(dateStr);
    const wib = new Date(
        d.toLocaleString("en-US", { timeZone: "Asia/Jakarta" })
    );
    const pad = (n: number) => String(n).padStart(2, "0");
    return {
        date: `${wib.getFullYear()}-${pad(wib.getMonth() + 1)}-${pad(
            wib.getDate()
        )}`,
        time: `${pad(wib.getHours())}:${pad(wib.getMinutes())}`,
    };
};

// --- WATCHERS ---
watch(
    () => props.show,
    (v) => {
        if (v) {
            localTodo.value = { ...props.todo };
            editTitle.value = props.todo.title;
            editDesc.value = props.todo.description ?? "";
            if (props.todo.due_date) {
                const { date, time } = getWIBParts(props.todo.due_date);
                editDueDate.value = date;
                editDueTime.value = time;
            } else {
                editDueDate.value = "";
                editDueTime.value = "23:59";
            }
            activeTab.value = "detail";
            isEditingTitle.value = false;
            showDeleteConfirm.value = false;
        }
    }
);

watch(
    () => props.todo,
    (v) => {
        localTodo.value = { ...v };
    },
    { deep: true }
);

watch(contactSearch, async (q) => {
    if (!q || q.length < 2) {
        contacts.value = [];
        return;
    }
    isLoadingContacts.value = true;
    try {
        const res = await axios.get("/chat/contacts", {
            params: { search: q },
        });
        contacts.value = (res.data.data ?? res.data).filter(
            (c: Assignee) =>
                !localTodo.value.assignees?.find((a) => a.id === c.id)
        );
    } catch {
        contacts.value = [];
    } finally {
        isLoadingContacts.value = false;
    }
});

// --- COMPUTED ---
const minDate = computed(
    (): string => new Date().toISOString().split("T")[0] ?? ""
);

const deadlineInfo = computed(() => {
    if (!localTodo.value.due_date) return null;
    const due = parseDueDate(localTodo.value.due_date);
    const now = new Date();
    const diff = due.getTime() - now.getTime();
    if (localTodo.value.status === "done")
        return {
            label: "Selesai",
            color: "#10b981",
            bg: "#ecfdf5",
            icon: "✅",
        };
    if (diff < 0)
        return {
            label: `Terlambat ${Math.abs(Math.floor(diff / 86400000))} hari`,
            color: "#ef4444",
            bg: "#fef2f2",
            icon: "🔴",
        };
    if (diff < 3600000)
        return {
            label: `${Math.floor(diff / 60000)} menit lagi`,
            color: "#f97316",
            bg: "#fff7ed",
            icon: "🟠",
        };
    if (diff < 86400000)
        return {
            label: `${Math.floor(diff / 3600000)} jam lagi`,
            color: "#f59e0b",
            bg: "#fffbeb",
            icon: "🟡",
        };
    return {
        label: formatDateWIB(localTodo.value.due_date),
        color: "#6b7280",
        bg: "#f3f4f6",
        icon: "📅",
    };
});

const priorityConfig: Record<
    string,
    { label: string; color: string; bg: string }
> = {
    low: { label: "Rendah", color: "#10b981", bg: "#ecfdf5" },
    medium: { label: "Sedang", color: "#f59e0b", bg: "#fffbeb" },
    high: { label: "Tinggi", color: "#ef4444", bg: "#fef2f2" },
};

const statusConfig = [
    { key: "todo", label: "To Do", color: "#5e6ad2" },
    { key: "in_progress", label: "In Progress", color: "#f59e0b" },
    { key: "done", label: "Done", color: "#10b981" },
];

// Helper: delay tutup dropdown agar click item sempat terpanggil dulu
const closeDropdownDelayed = () => {
    setTimeout(() => {
        showContactDropdown.value = false;
    }, 200);
};
const saveTitle = async () => {
    if (!editTitle.value.trim()) return;
    isSaving.value = true;
    try {
        await axios.put(`/chat/todos/${localTodo.value.id}`, {
            title: editTitle.value,
            description: editDesc.value,
            status: localTodo.value.status,
        });
        localTodo.value.title = editTitle.value;
        localTodo.value.description = editDesc.value;
        isEditingTitle.value = false;
        emit("updated");
        toast.success("Disimpan!");
    } catch {
        toast.error("Gagal menyimpan");
    } finally {
        isSaving.value = false;
    }
};

const changeStatus = async (s: string) => {
    const old = localTodo.value.status;
    localTodo.value.status = s as any;
    try {
        await axios.put(`/chat/todos/${localTodo.value.id}`, { status: s });
        emit("updated");
    } catch {
        localTodo.value.status = old;
        toast.error("Gagal ubah status");
    }
};

const changePriority = async (p: string) => {
    const old = localTodo.value.priority;
    localTodo.value.priority = p as any;
    try {
        await axios.put(`/chat/todos/${localTodo.value.id}`, { priority: p });
        emit("updated");
    } catch {
        localTodo.value.priority = old;
    }
};

const saveDeadline = async () => {
    let dueDateTime: string | null = null;
    if (editDueDate.value) {
        dueDateTime = `${editDueDate.value} ${editDueTime.value || "23:59"}:00`;
    }
    try {
        await axios.put(`/chat/todos/${localTodo.value.id}`, {
            due_date: dueDateTime,
        });
        localTodo.value.due_date = dueDateTime ?? undefined;
        showDeadlinePicker.value = false;
        emit("updated");
        toast.success("Deadline disimpan!");
    } catch {
        toast.error("Gagal simpan deadline");
    }
};

const removeDeadline = async () => {
    try {
        await axios.put(`/chat/todos/${localTodo.value.id}`, {
            due_date: null,
        });
        localTodo.value.due_date = undefined;
        editDueDate.value = "";
        showDeadlinePicker.value = false;
        emit("updated");
    } catch {
        toast.error("Gagal hapus deadline");
    }
};

// --- ATTACHMENT ---
const triggerFileUpload = () => fileInput.value?.click();

const onFileSelected = async (evt: Event) => {
    const file = (evt.target as HTMLInputElement).files?.[0];
    if (!file) return;
    isUploadingFile.value = true;
    const formData = new FormData();
    formData.append("file", file);
    formData.append("todo_id", String(localTodo.value.id));
    formData.append("type", "file");
    try {
        const res = await axios.post("/chat/todos/attachments", formData, {
            headers: { "Content-Type": "multipart/form-data" },
        });
        const att = res.data.data ?? res.data;
        if (!localTodo.value.attachments) localTodo.value.attachments = [];
        localTodo.value.attachments.push(att);
        emit("updated");
        toast.success("File berhasil diupload!");
    } catch {
        toast.error("Gagal upload file");
    } finally {
        isUploadingFile.value = false;
        if (fileInput.value) fileInput.value.value = "";
    }
};

const addLink = async () => {
    if (!linkUrl.value.trim()) return;
    isAddingLink.value = true;
    try {
        const res = await axios.post("/chat/todos/attachments", {
            todo_id: localTodo.value.id,
            type: "link",
            name: linkName.value || linkUrl.value,
            url: linkUrl.value,
        });
        const att = res.data.data ?? res.data;
        if (!localTodo.value.attachments) localTodo.value.attachments = [];
        localTodo.value.attachments.push(att);
        linkUrl.value = "";
        linkName.value = "";
        showLinkForm.value = false;
        emit("updated");
        toast.success("Link ditambahkan!");
    } catch {
        toast.error("Gagal tambah link");
    } finally {
        isAddingLink.value = false;
    }
};

const deleteAttachment = async (attId: number) => {
    try {
        await axios.delete(`/chat/todos/attachments/${attId}`);
        localTodo.value.attachments = localTodo.value.attachments?.filter(
            (a) => a.id !== attId
        );
        emit("updated");
    } catch {
        toast.error("Gagal hapus lampiran");
    }
};

const getFileIcon = (att: Attachment) => {
    if (att.type === "link") return "🔗";
    const mime = att.mime_type ?? "";
    if (mime.startsWith("image/")) return "🖼️";
    if (mime.includes("pdf")) return "📄";
    if (mime.includes("word") || mime.includes("doc")) return "📝";
    if (mime.includes("sheet") || mime.includes("excel")) return "📊";
    return "📎";
};

const formatBytes = (b?: number) => {
    if (!b) return "";
    if (b < 1024) return `${b} B`;
    if (b < 1048576) return `${(b / 1024).toFixed(1)} KB`;
    return `${(b / 1048576).toFixed(1)} MB`;
};

// --- ASSIGNEES ---
const addAssignee = async (contact: Assignee) => {
    try {
        await axios.post(`/chat/todos/${localTodo.value.id}/assignees`, {
            user_id: contact.id,
        });
        if (!localTodo.value.assignees) localTodo.value.assignees = [];
        localTodo.value.assignees.push(contact);
        contactSearch.value = "";
        contacts.value = [];
        emit("updated");
        toast.success(`${contact.name} ditambahkan!`);
    } catch {
        toast.error("Gagal tambah anggota");
    }
};

const removeAssignee = async (userId: number) => {
    try {
        await axios.delete(
            `/chat/todos/${localTodo.value.id}/assignees/${userId}`
        );
        localTodo.value.assignees = localTodo.value.assignees?.filter(
            (a) => a.id !== userId
        );
        emit("updated");
    } catch {
        toast.error("Gagal hapus anggota");
    }
};

// --- DELETE TODO ---
const deleteTodo = async () => {
    isDeleting.value = true;
    try {
        await axios.delete(`/chat/todos/${localTodo.value.id}`);
        toast.success("Tugas dihapus");
        emit("deleted");
    } catch {
        toast.error("Gagal menghapus tugas");
    } finally {
        isDeleting.value = false;
    }
};
</script>

<template>
    <Teleport to="body">
        <div class="dmodal-wrap">
            <Transition name="backdrop">
                <div
                    v-if="show"
                    class="dmodal-backdrop"
                    @click="emit('close')"
                ></div>
            </Transition>

            <Transition name="slide-up">
                <div v-if="show" class="dmodal-container">
                    <div class="dmodal-card">
                        <!-- HEADER -->
                        <div class="dmodal-header">
                            <!-- Title area -->
                            <div class="dtitle-area">
                                <div
                                    v-if="!isEditingTitle"
                                    class="dtitle-view"
                                    @click="isEditingTitle = true"
                                >
                                    <h3 class="dtitle">
                                        {{ localTodo.title }}
                                    </h3>
                                    <button
                                        class="btn-edit-inline"
                                        title="Edit judul"
                                    >
                                        <Pencil class="w-3 h-3" />
                                    </button>
                                </div>
                                <div v-else class="dtitle-edit">
                                    <input
                                        v-model="editTitle"
                                        class="dtitle-input"
                                        @keyup.enter="saveTitle"
                                        @keyup.esc="isEditingTitle = false"
                                        autofocus
                                    />
                                    <div
                                        style="
                                            display: flex;
                                            gap: 6px;
                                            margin-top: 6px;
                                        "
                                    >
                                        <button
                                            class="btn-save-sm"
                                            @click="saveTitle"
                                            :disabled="isSaving"
                                        >
                                            <Check class="w-3 h-3" /> Simpan
                                        </button>
                                        <button
                                            class="btn-cancel-sm"
                                            @click="isEditingTitle = false"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button class="btn-close-x" @click="emit('close')">
                                <X class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- TABS -->
                        <div class="dmodal-tabs">
                            <button
                                v-for="tab in ['detail', 'attachments', 'members', 'comments'] as const"
                                :key="tab"
                                class="dtab"
                                :class="{ active: activeTab === tab }"
                                @click="
                                    activeTab = tab;
                                    tab === 'comments' && fetchComments();
                                "
                            >
                                <span v-if="tab === 'detail'">📋 Detail</span>
                                <span v-else-if="tab === 'attachments'">
                                    📎 Lampiran
                                    <span
                                        v-if="localTodo.attachments?.length"
                                        class="tab-badge"
                                    >
                                        {{ localTodo.attachments.length }}
                                    </span>
                                </span>
                                <span v-else-if="tab === 'members'">
                                    👥 Anggota
                                    <span
                                        v-if="localTodo.assignees?.length"
                                        class="tab-badge"
                                    >
                                        {{ localTodo.assignees.length }}
                                    </span>
                                </span>
                                <span v-else>
                                    💬 Komentar
                                    <span
                                        v-if="comments.length"
                                        class="tab-badge"
                                    >
                                        {{ comments.length }}
                                    </span>
                                </span>
                            </button>
                        </div>

                        <!-- CONTENT -->
                        <div class="dmodal-body">
                            <!-- ===== TAB: DETAIL ===== -->
                            <div
                                v-if="activeTab === 'detail'"
                                class="tab-content"
                            >
                                <div class="side-grid">
                                    <!-- LEFT: Description -->
                                    <div class="main-col">
                                        <p class="section-label">
                                            <AlignLeft class="w-3 h-3" />
                                            Deskripsi
                                        </p>
                                        <textarea
                                            v-model="editDesc"
                                            class="desc-textarea"
                                            placeholder="Tambahkan deskripsi yang lebih detail..."
                                            rows="4"
                                        ></textarea>
                                        <button
                                            class="btn-save-desc"
                                            @click="saveTitle"
                                            :disabled="isSaving"
                                        >
                                            <span
                                                v-if="isSaving"
                                                class="spinner-border spinner-border-sm"
                                            ></span>
                                            <span v-else>Simpan Deskripsi</span>
                                        </button>
                                    </div>

                                    <!-- RIGHT: Sidebar -->
                                    <div class="sidebar-col">
                                        <!-- Status -->
                                        <div class="sidebar-section">
                                            <p class="section-label">Status</p>
                                            <div class="sidebar-btns">
                                                <button
                                                    v-for="s in statusConfig"
                                                    :key="s.key"
                                                    class="sidebar-btn"
                                                    :class="{
                                                        active:
                                                            localTodo.status ===
                                                            s.key,
                                                    }"
                                                    :style="
                                                        localTodo.status ===
                                                        s.key
                                                            ? {
                                                                  borderColor:
                                                                      s.color,
                                                                  color: s.color,
                                                                  background:
                                                                      s.color +
                                                                      '15',
                                                              }
                                                            : {}
                                                    "
                                                    @click="changeStatus(s.key)"
                                                >
                                                    <span
                                                        class="s-dot"
                                                        :style="{
                                                            background: s.color,
                                                        }"
                                                    ></span>
                                                    {{ s.label }}
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Priority -->
                                        <div class="sidebar-section">
                                            <p class="section-label">
                                                <Flag class="w-3 h-3" />
                                                Prioritas
                                            </p>
                                            <div class="sidebar-btns">
                                                <button
                                                    v-for="(
                                                        cfg, key
                                                    ) in priorityConfig"
                                                    :key="key"
                                                    class="sidebar-btn"
                                                    :class="{
                                                        active:
                                                            localTodo.priority ===
                                                            key,
                                                    }"
                                                    :style="
                                                        localTodo.priority ===
                                                        key
                                                            ? {
                                                                  borderColor:
                                                                      cfg.color,
                                                                  color: cfg.color,
                                                                  background:
                                                                      cfg.bg,
                                                              }
                                                            : {}
                                                    "
                                                    @click="changePriority(key)"
                                                >
                                                    {{ cfg.label }}
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Deadline -->
                                        <div class="sidebar-section">
                                            <p class="section-label">
                                                <Clock class="w-3 h-3" />
                                                Deadline
                                            </p>

                                            <!-- Deadline display -->
                                            <div
                                                v-if="
                                                    localTodo.due_date &&
                                                    deadlineInfo &&
                                                    !showDeadlinePicker
                                                "
                                                class="deadline-chip"
                                                :style="{
                                                    color: deadlineInfo.color,
                                                    background: deadlineInfo.bg,
                                                }"
                                                @click="
                                                    showDeadlinePicker = true
                                                "
                                            >
                                                <span>{{
                                                    deadlineInfo.icon
                                                }}</span>
                                                <span>{{
                                                    deadlineInfo.label
                                                }}</span>
                                                <button class="chip-edit">
                                                    <Pencil class="w-3 h-3" />
                                                </button>
                                            </div>
                                            <button
                                                v-else-if="!showDeadlinePicker"
                                                class="btn-add-deadline"
                                                @click="
                                                    showDeadlinePicker = true
                                                "
                                            >
                                                <Calendar class="w-4 h-4" />
                                                Tambah Deadline
                                            </button>

                                            <!-- Deadline Picker -->
                                            <div
                                                v-if="showDeadlinePicker"
                                                class="deadline-picker"
                                            >
                                                <div class="dp-row">
                                                    <input
                                                        v-model="editDueDate"
                                                        type="date"
                                                        class="dp-input"
                                                        :min="minDate"
                                                    />
                                                    <input
                                                        v-model="editDueTime"
                                                        type="time"
                                                        class="dp-input"
                                                        style="width: 90px"
                                                    />
                                                </div>
                                                <div class="dp-actions">
                                                    <button
                                                        class="dp-save"
                                                        @click="saveDeadline"
                                                    >
                                                        Simpan
                                                    </button>
                                                    <button
                                                        v-if="
                                                            localTodo.due_date
                                                        "
                                                        class="dp-remove"
                                                        @click="removeDeadline"
                                                    >
                                                        Hapus
                                                    </button>
                                                    <button
                                                        class="dp-cancel"
                                                        @click="
                                                            showDeadlinePicker = false
                                                        "
                                                    >
                                                        Batal
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ===== TAB: ATTACHMENTS ===== -->
                            <div
                                v-if="activeTab === 'attachments'"
                                class="tab-content"
                            >
                                <!-- Upload Actions -->
                                <div class="attach-actions">
                                    <button
                                        class="btn-attach-action"
                                        @click="triggerFileUpload"
                                        :disabled="isUploadingFile"
                                    >
                                        <Upload class="w-4 h-4" />
                                        <span>{{
                                            isUploadingFile
                                                ? "Mengupload..."
                                                : "Upload File/Gambar"
                                        }}</span>
                                    </button>
                                    <button
                                        class="btn-attach-action"
                                        @click="showLinkForm = !showLinkForm"
                                    >
                                        <Link2 class="w-4 h-4" />
                                        <span>Tambah Link</span>
                                    </button>
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        style="display: none"
                                        @change="onFileSelected"
                                    />
                                </div>

                                <!-- Link Form -->
                                <div v-if="showLinkForm" class="link-form">
                                    <input
                                        v-model="linkUrl"
                                        type="url"
                                        class="link-input"
                                        placeholder="https://..."
                                    />
                                    <input
                                        v-model="linkName"
                                        type="text"
                                        class="link-input"
                                        placeholder="Nama link (opsional)"
                                    />
                                    <div style="display: flex; gap: 6px">
                                        <button
                                            class="dp-save"
                                            @click="addLink"
                                            :disabled="isAddingLink"
                                        >
                                            {{
                                                isAddingLink ? "..." : "Tambah"
                                            }}
                                        </button>
                                        <button
                                            class="dp-cancel"
                                            @click="showLinkForm = false"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </div>

                                <!-- Attachment List -->
                                <div
                                    v-if="localTodo.attachments?.length"
                                    class="attach-list"
                                >
                                    <div
                                        v-for="att in localTodo.attachments"
                                        :key="att.id"
                                        class="attach-item"
                                    >
                                        <span class="attach-icon">{{
                                            getFileIcon(att)
                                        }}</span>
                                        <div class="attach-info">
                                            <p class="attach-name">
                                                {{ att.name }}
                                            </p>
                                            <p class="attach-meta">
                                                {{
                                                    att.type === "link"
                                                        ? att.url?.slice(
                                                              0,
                                                              40
                                                          ) + "..."
                                                        : formatBytes(att.size)
                                                }}
                                            </p>
                                        </div>
                                        <div class="attach-actions-row">
                                            <a
                                                v-if="att.url"
                                                :href="att.url"
                                                target="_blank"
                                                class="btn-icon-sm"
                                            >
                                                <ExternalLink class="w-3 h-3" />
                                            </a>
                                            <a
                                                v-else-if="att.path"
                                                :href="att.path"
                                                download
                                                class="btn-icon-sm"
                                            >
                                                <Download class="w-3 h-3" />
                                            </a>
                                            <button
                                                class="btn-icon-sm danger"
                                                @click="
                                                    deleteAttachment(att.id)
                                                "
                                            >
                                                <Trash2 class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="empty-tab">
                                    <span style="font-size: 2rem">📎</span>
                                    <p>Belum ada lampiran</p>
                                    <p class="empty-sub">
                                        Upload file, gambar, atau tambahkan link
                                    </p>
                                </div>
                            </div>

                            <!-- ===== TAB: MEMBERS ===== -->
                            <div
                                v-if="activeTab === 'members'"
                                class="tab-content"
                            >
                                <!-- Search -->
                                <div class="contact-search-wrap">
                                    <Search class="cs-ico" />
                                    <input
                                        v-model="contactSearch"
                                        type="text"
                                        class="cs-input"
                                        placeholder="Cari kontak untuk diundang..."
                                        @focus="showContactDropdown = true"
                                        @blur="closeDropdownDelayed"
                                    />
                                    <div
                                        v-if="
                                            showContactDropdown &&
                                            (contacts.length ||
                                                isLoadingContacts ||
                                                contactSearch.length >= 2)
                                        "
                                        class="cs-dropdown"
                                    >
                                        <div
                                            v-if="isLoadingContacts"
                                            class="cs-loading"
                                        >
                                            <span
                                                class="spinner-border spinner-border-sm"
                                            ></span>
                                            Mencari...
                                        </div>
                                        <div
                                            v-for="c in contacts"
                                            :key="c.id"
                                            class="cs-item"
                                            @mousedown.prevent="addAssignee(c)"
                                        >
                                            <div class="cs-avatar">
                                                <img
                                                    v-if="c.profile_photo_url"
                                                    :src="c.profile_photo_url"
                                                />
                                                <span v-else>{{
                                                    c.name[0]
                                                }}</span>
                                            </div>
                                            <div>
                                                <p class="cs-name">
                                                    {{ c.name }}
                                                </p>
                                                <p class="cs-email">
                                                    {{ c.email }}
                                                </p>
                                            </div>
                                            <button class="cs-add">
                                                <Check class="w-3 h-3" /> Undang
                                            </button>
                                        </div>
                                        <div
                                            v-if="
                                                !isLoadingContacts &&
                                                !contacts.length &&
                                                contactSearch.length >= 2
                                            "
                                            class="cs-empty"
                                        >
                                            Kontak tidak ditemukan
                                        </div>
                                    </div>
                                </div>

                                <!-- Member List -->
                                <div
                                    v-if="localTodo.assignees?.length"
                                    class="member-list"
                                >
                                    <p class="section-label">
                                        Anggota ({{
                                            localTodo.assignees.length
                                        }})
                                    </p>
                                    <div
                                        v-for="a in localTodo.assignees"
                                        :key="a.id"
                                        class="member-item"
                                    >
                                        <div class="member-avatar">
                                            <img
                                                v-if="a.profile_photo_url"
                                                :src="a.profile_photo_url"
                                            />
                                            <span v-else>{{ a.name[0] }}</span>
                                        </div>
                                        <div style="flex: 1">
                                            <p class="member-name">
                                                {{ a.name }}
                                            </p>
                                            <p class="member-role">
                                                {{
                                                    a.pivot?.role === "owner"
                                                        ? "Pemilik"
                                                        : "Anggota"
                                                }}
                                            </p>
                                        </div>
                                        <button
                                            v-if="a.pivot?.role !== 'owner'"
                                            class="btn-icon-sm danger"
                                            @click="removeAssignee(a.id)"
                                            title="Keluarkan"
                                        >
                                            <X class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="empty-tab">
                                    <span style="font-size: 2rem">👥</span>
                                    <p>Belum ada anggota</p>
                                    <p class="empty-sub">
                                        Cari kontak di atas untuk mengundang
                                    </p>
                                </div>
                            </div>

                            <!-- ===== TAB: KOMENTAR ===== -->
                            <div
                                v-if="activeTab === 'comments'"
                                class="tab-comments"
                            >
                                <!-- Input komentar baru -->
                                <div class="comment-input-area">
                                    <textarea
                                        v-model="newComment"
                                        class="comment-textarea"
                                        placeholder="Tulis komentar..."
                                        rows="2"
                                        @keydown.ctrl.enter="postComment"
                                    ></textarea>
                                    <div
                                        style="
                                            display: flex;
                                            justify-content: flex-end;
                                            margin-top: 6px;
                                        "
                                    >
                                        <button
                                            class="dp-save"
                                            @click="postComment"
                                            :disabled="
                                                isPostingComment ||
                                                !newComment.trim()
                                            "
                                        >
                                            {{
                                                isPostingComment
                                                    ? "..."
                                                    : "Kirim"
                                            }}
                                        </button>
                                    </div>
                                    <p class="comment-hint">
                                        Ctrl+Enter untuk kirim
                                    </p>
                                </div>

                                <!-- Loading -->
                                <div v-if="isLoadingComments" class="empty-tab">
                                    <span
                                        class="spinner-border spinner-border-sm"
                                    ></span>
                                    <p>Memuat komentar...</p>
                                </div>

                                <!-- Daftar komentar -->
                                <div
                                    v-else-if="comments.length"
                                    class="comment-list"
                                >
                                    <div
                                        v-for="c in comments"
                                        :key="c.id"
                                        class="comment-item"
                                    >
                                        <div class="comment-avatar">
                                            <img
                                                v-if="c.user?.profile_photo_url"
                                                :src="c.user.profile_photo_url"
                                            />
                                            <span v-else>{{
                                                c.user?.name?.[0]?.toUpperCase() ??
                                                "?"
                                            }}</span>
                                        </div>
                                        <div class="comment-body">
                                            <div class="comment-header">
                                                <span class="comment-author">{{
                                                    c.user?.name
                                                }}</span>
                                                <span class="comment-time">{{
                                                    formatCommentTime(
                                                        c.created_at
                                                    )
                                                }}</span>
                                                <button
                                                    class="btn-icon-sm danger"
                                                    style="margin-left: auto"
                                                    @click="deleteComment(c.id)"
                                                    title="Hapus komentar"
                                                >
                                                    <X class="w-3 h-3" />
                                                </button>
                                            </div>
                                            <p class="comment-content">
                                                {{ c.content }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empty -->
                                <div v-else class="empty-tab">
                                    <span style="font-size: 2rem">💬</span>
                                    <p>Belum ada komentar</p>
                                    <p class="empty-sub">
                                        Jadilah yang pertama berkomentar
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- FOOTER -->
                        <div class="dmodal-footer">
                            <div v-if="!showDeleteConfirm">
                                <button
                                    class="btn-delete-todo"
                                    @click="showDeleteConfirm = true"
                                >
                                    <Trash2 class="w-4 h-4" /> Hapus Tugas
                                </button>
                            </div>
                            <div v-else class="confirm-delete-row">
                                <AlertTriangle class="w-4 h-4 text-danger" />
                                <span style="font-size: 0.85rem; color: #ef4444"
                                    >Yakin hapus tugas ini?</span
                                >
                                <button
                                    class="btn-confirm-del"
                                    @click="deleteTodo"
                                    :disabled="isDeleting"
                                >
                                    {{ isDeleting ? "..." : "Ya, Hapus" }}
                                </button>
                                <button
                                    class="dp-cancel"
                                    @click="showDeleteConfirm = false"
                                >
                                    Batal
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<style scoped>
/* --- LAYOUT --- */
.dmodal-wrap {
    position: fixed;
    inset: 0;
    z-index: 9990;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    pointer-events: none;
}
@media (min-width: 640px) {
    .dmodal-wrap {
        align-items: center;
    }
}
.dmodal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(6px);
    pointer-events: auto;
    z-index: 9991;
}
.dmodal-container {
    z-index: 9995;
    width: 100%;
    max-width: 780px;
    padding: 0 0 0 0;
    pointer-events: auto;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}
.dmodal-card {
    background: #fff;
    border-radius: 24px 24px 0 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
    box-shadow: 0 -8px 40px rgba(0, 0, 0, 0.15);
}
@media (min-width: 640px) {
    .dmodal-card {
        border-radius: 24px;
    }
}

/* --- HEADER --- */
.dmodal-header {
    padding: 20px 22px 14px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1.5px solid #f1f5f9;
}
.dtitle-area {
    flex: 1;
}
.dtitle-view {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    cursor: pointer;
}
.dtitle {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1a1a2e;
    margin: 0;
    line-height: 1.4;
}
.btn-edit-inline {
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 4px;
    border-radius: 6px;
    opacity: 0;
    transition: opacity 0.2s;
    flex-shrink: 0;
}
.dtitle-view:hover .btn-edit-inline {
    opacity: 1;
}
.dtitle-input {
    width: 100%;
    border: 2px solid #5e6ad2;
    border-radius: 10px;
    padding: 8px 12px;
    font-size: 1.05rem;
    font-weight: 700;
    color: #1a1a2e;
    outline: none;
}
.btn-save-sm {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    background: #5e6ad2;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
}
.btn-cancel-sm {
    padding: 5px 12px;
    background: none;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.82rem;
    cursor: pointer;
    color: #6b7280;
}

.btn-close-x {
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 6px;
    border-radius: 8px;
    transition: all 0.2s;
    flex-shrink: 0;
}
.btn-close-x:hover {
    background: #fef2f2;
    color: #ef4444;
}

/* --- TABS --- */
.dmodal-tabs {
    display: flex;
    border-bottom: 1.5px solid #f1f5f9;
    padding: 0 20px;
}
.dtab {
    padding: 10px 16px;
    border: none;
    background: none;
    font-size: 0.88rem;
    font-weight: 600;
    color: #9ca3af;
    cursor: pointer;
    border-bottom: 2.5px solid transparent;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.dtab.active {
    color: #5e6ad2;
    border-bottom-color: #5e6ad2;
}
.tab-badge {
    background: #5e6ad2;
    color: white;
    font-size: 0.68rem;
    padding: 1px 6px;
    border-radius: 10px;
}

/* --- BODY --- */
.dmodal-body {
    flex: 1;
    overflow-y: auto;
    padding: 18px 22px;
}

.tab-content {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Side grid */
.side-grid {
    display: grid;
    grid-template-columns: 1fr 240px;
    gap: 20px;
}
@media (max-width: 600px) {
    .side-grid {
        grid-template-columns: 1fr;
    }
}

.section-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 8px;
}

/* Description */
.desc-textarea {
    width: 100%;
    border: 2px solid #f1f5f9;
    border-radius: 12px;
    padding: 12px 14px;
    font-size: 0.92rem;
    color: #1a1a2e;
    outline: none;
    resize: none;
    font-family: inherit;
    transition: border-color 0.2s;
}
.desc-textarea:focus {
    border-color: #5e6ad2;
}
.btn-save-desc {
    margin-top: 8px;
    padding: 7px 18px;
    background: #5e6ad2;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
}
.btn-save-desc:hover {
    background: #4c56b0;
}
.btn-save-desc:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Sidebar */
.sidebar-col {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.sidebar-section {
    margin-bottom: 4px;
}
.sidebar-btns {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.sidebar-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    border: 1.5px solid #e5e7eb;
    border-radius: 9px;
    background: none;
    font-size: 0.83rem;
    font-weight: 600;
    cursor: pointer;
    color: #6b7280;
    text-align: left;
    transition: all 0.15s;
}
.sidebar-btn:hover {
    border-color: #9ca3af;
    background: #f8f9fc;
}
.sidebar-btn.active {
    font-weight: 700;
}
.s-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Deadline */
.deadline-chip {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
}
.chip-edit {
    background: none;
    border: none;
    cursor: pointer;
    color: inherit;
    opacity: 0.6;
}
.btn-add-deadline {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border: 1.5px dashed #d1d5db;
    border-radius: 10px;
    background: none;
    font-size: 0.85rem;
    color: #9ca3af;
    cursor: pointer;
    width: 100%;
}
.btn-add-deadline:hover {
    border-color: #5e6ad2;
    color: #5e6ad2;
}
.deadline-picker {
    background: #f8f9fc;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.dp-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.dp-input {
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    padding: 6px 8px;
    font-size: 0.85rem;
    outline: none;
    flex: 1;
}
.dp-input:focus {
    border-color: #5e6ad2;
}
.dp-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.dp-save {
    padding: 6px 14px;
    background: #5e6ad2;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
}
.dp-remove {
    padding: 6px 14px;
    background: #fef2f2;
    color: #ef4444;
    border: 1px solid #fecaca;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
}
.dp-cancel {
    padding: 6px 14px;
    background: none;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.82rem;
    cursor: pointer;
    color: #6b7280;
}

/* Attachments */
.attach-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.btn-attach-action {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 9px 16px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    color: #374151;
    transition: all 0.2s;
}
.btn-attach-action:hover {
    border-color: #5e6ad2;
    color: #5e6ad2;
    background: #eef0ff;
}
.btn-attach-action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.link-form {
    background: #f8f9fc;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.link-input {
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.88rem;
    outline: none;
    width: 100%;
}
.link-input:focus {
    border-color: #5e6ad2;
}

.attach-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.attach-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    background: #f8f9fc;
    border: 1.5px solid #f1f5f9;
    border-radius: 12px;
    transition: border-color 0.2s;
}
.attach-item:hover {
    border-color: #e5e7eb;
}
.attach-icon {
    font-size: 1.2rem;
}
.attach-info {
    flex: 1;
    min-width: 0;
}
.attach-name {
    font-size: 0.88rem;
    font-weight: 600;
    color: #1a1a2e;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.attach-meta {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 2px 0 0;
}
.attach-actions-row {
    display: flex;
    gap: 6px;
}
.btn-icon-sm {
    width: 28px;
    height: 28px;
    border: 1.5px solid #e5e7eb;
    background: #fff;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.15s;
    text-decoration: none;
}
.btn-icon-sm:hover {
    border-color: #5e6ad2;
    color: #5e6ad2;
}
.btn-icon-sm.danger:hover {
    border-color: #ef4444;
    color: #ef4444;
    background: #fef2f2;
}

/* Members */
.contact-search-wrap {
    position: relative;
}
.cs-ico {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 15px;
    height: 15px;
    color: #9ca3af;
}
.cs-input {
    width: 100%;
    padding: 10px 14px 10px 36px;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    font-size: 0.9rem;
    outline: none;
    color: #1a1a2e;
    transition: border-color 0.2s;
}
.cs-input:focus {
    border-color: #5e6ad2;
}
.cs-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    z-index: 100;
    max-height: 200px;
    overflow-y: auto;
}
.cs-loading {
    padding: 12px 16px;
    font-size: 0.85rem;
    color: #9ca3af;
    display: flex;
    align-items: center;
    gap: 8px;
}
.cs-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    cursor: pointer;
    transition: background 0.15s;
}
.cs-item:hover {
    background: #f8f9fc;
}
.cs-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #5e6ad2, #8b5cf6);
    color: white;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}
.cs-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.cs-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1a1a2e;
    margin: 0;
}
.cs-email {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
}
.cs-add {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 5px 10px;
    background: #eef0ff;
    color: #5e6ad2;
    border: none;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    flex-shrink: 0;
}
.cs-empty {
    padding: 12px 16px;
    font-size: 0.85rem;
    color: #9ca3af;
    text-align: center;
}

.member-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.member-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    background: #f8f9fc;
    border-radius: 12px;
}
.member-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #5e6ad2, #8b5cf6);
    color: white;
    font-size: 0.85rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}
.member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.member-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1a1a2e;
    margin: 0;
}
.member-role {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
}

/* Empty state */
.empty-tab {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    gap: 8px;
    text-align: center;
}
.empty-tab p {
    font-size: 0.95rem;
    font-weight: 600;
    color: #374151;
    margin: 0;
}
.empty-sub {
    font-size: 0.82rem;
    color: #9ca3af !important;
    font-weight: 400 !important;
}

/* Footer */
.dmodal-footer {
    padding: 12px 22px 16px;
    border-top: 1.5px solid #f1f5f9;
    display: flex;
    align-items: center;
}
.btn-delete-todo {
    display: flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: 1.5px solid #fecaca;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #ef4444;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-delete-todo:hover {
    background: #fef2f2;
}
.confirm-delete-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.btn-confirm-del {
    padding: 7px 14px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 9px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
}
.btn-confirm-del:hover {
    background: #dc2626;
}

/* Animations */
.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.3s ease;
}
.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}
.slide-up-enter-active {
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.slide-up-leave-active {
    transition: all 0.25s ease;
}
.slide-up-enter-from {
    opacity: 0;
    transform: translateY(60px) scale(0.95);
}
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

/* Dark Mode */
:global(.dark) .dmodal-card {
    background: #1e1e2d;
}
:global(.dark) .dtitle {
    color: #e5e7eb;
}
:global(.dark) .dtitle-input {
    background: #1a1a2e;
    color: #e5e7eb;
    border-color: #5e6ad2;
}
:global(.dark) .dmodal-header,
:global(.dark) .dmodal-tabs,
:global(.dark) .dmodal-footer {
    border-color: #2b2b40;
}
:global(.dark) .desc-textarea {
    background: #1a1a2e;
    border-color: #2b2b40;
    color: #e5e7eb;
}
:global(.dark) .sidebar-btn {
    border-color: #2b2b40;
    color: #9ca3af;
}
:global(.dark) .sidebar-btn:hover {
    background: #2b2b40;
}
:global(.dark) .cs-input {
    background: #1a1a2e;
    border-color: #2b2b40;
    color: #e5e7eb;
}
:global(.dark) .cs-dropdown {
    background: #1e1e2d;
    border-color: #2b2b40;
}
:global(.dark) .cs-item:hover {
    background: #2b2b40;
}
:global(.dark) .cs-name,
:global(.dark) .member-name,
:global(.dark) .attach-name {
    color: #e5e7eb;
}
:global(.dark) .attach-item,
:global(.dark) .member-item {
    background: #1a1a2e;
    border-color: #2b2b40;
}
:global(.dark) .btn-attach-action {
    background: #1a1a2e;
    border-color: #2b2b40;
    color: #9ca3af;
}
:global(.dark) .btn-icon-sm {
    background: #1a1a2e;
    border-color: #2b2b40;
    color: #9ca3af;
}
:global(.dark) .link-form {
    background: #1a1a2e;
    border-color: #2b2b40;
}
:global(.dark) .link-input {
    background: #151521;
    border-color: #2b2b40;
    color: #e5e7eb;
}

/* ── COMMENT STYLES ── */
.tab-comments {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 4px 0;
}
.comment-input-area {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px;
}
.comment-textarea {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 0.875rem;
    resize: none;
    outline: none;
    background: white;
    color: #111827;
    font-family: inherit;
    line-height: 1.5;
}
.comment-textarea:focus {
    border-color: #5e6ad2;
}
.comment-hint {
    font-size: 0.7rem;
    color: #9ca3af;
    margin-top: 4px;
    text-align: right;
}
.comment-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.comment-item {
    display: flex;
    gap: 10px;
    align-items: flex-start;
}
.comment-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    flex-shrink: 0;
    background: linear-gradient(135deg, #5e6ad2, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    overflow: hidden;
}
.comment-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.comment-body {
    flex: 1;
    background: #f3f4f6;
    border-radius: 10px;
    padding: 8px 12px;
}
.comment-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}
.comment-author {
    font-size: 0.8rem;
    font-weight: 600;
    color: #111827;
}
.comment-time {
    font-size: 0.72rem;
    color: #9ca3af;
}
.comment-content {
    font-size: 0.85rem;
    color: #374151;
    line-height: 1.5;
    white-space: pre-wrap;
    margin: 0;
}

/* dark mode komentar */
:global(.dark) .comment-input-area {
    background: #1a1a2e;
    border-color: #2b2b40;
}
:global(.dark) .comment-textarea {
    background: #151521;
    border-color: #2b2b40;
    color: #e5e7eb;
}
:global(.dark) .comment-textarea:focus {
    border-color: #5e6ad2;
}
:global(.dark) .comment-body {
    background: #252538;
}
:global(.dark) .comment-author {
    color: #e5e7eb;
}
:global(.dark) .comment-content {
    color: #d1d5db;
}
</style>