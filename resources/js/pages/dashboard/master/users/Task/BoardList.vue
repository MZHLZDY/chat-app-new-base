<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import {
    Plus,
    Settings,
    Trash2,
    Users,
    LayoutDashboard,
    ChevronRight,
    X,
    Check,
    AlertTriangle,
} from "lucide-vue-next";
import KanbanBoard from "./Index.vue";

// ─── TYPES ───────────────────────────────────────────────────────────────────
interface BoardMember {
    id: number;
    name: string;
    profile_photo_url?: string;
    pivot?: { role: string };
}

interface Board {
    id: number;
    user_id: number;
    name: string;
    description?: string;
    color: string;
    icon: string;
    members?: BoardMember[];
    todos_count?: number;
    stats?: { total: number; done: number; progress: number };
}

// ─── STATE ───────────────────────────────────────────────────────────────────
const boards = ref<Board[]>([]);
const isLoading = ref(false);
const activeBoard = ref<Board | null>(null); // board yang sedang dibuka

// Modal buat board
const showCreateModal = ref(false);
const isCreating = ref(false);
const newBoardName = ref("");
const newBoardDesc = ref("");
const newBoardColor = ref("#667eea");
const newBoardIcon = ref("📋");

// Contacts untuk invite di board baru
interface Contact {
    id: number;
    name: string;
    email?: string;
    profile_photo_url?: string;
    alias?: string;
}
const contacts = ref<Contact[]>([]);
const contactSearch = ref("");
const isLoadingContacts = ref(false);
const newBoardMembers = ref<Contact[]>([]);

const fetchContacts = async () => {
    isLoadingContacts.value = true;
    try {
        const res = await axios.get("/chat/contacts");
        contacts.value = res.data.data ?? res.data;
    } catch {
        contacts.value = [];
    } finally {
        isLoadingContacts.value = false;
    }
};

const filteredContacts = computed(() =>
    contacts.value.filter(
        (c) =>
            !newBoardMembers.value.find((m) => m.id === c.id) &&
            (c.alias ?? c.name)
                .toLowerCase()
                .includes(contactSearch.value.toLowerCase())
    )
);

const addMemberToNew = (c: Contact) => {
    if (!newBoardMembers.value.find((m) => m.id === c.id)) {
        newBoardMembers.value.push(c);
    }
    contactSearch.value = "";
};

const removeMemberFromNew = (id: number) => {
    newBoardMembers.value = newBoardMembers.value.filter((m) => m.id !== id);
};

const openCreateModal = () => {
    showCreateModal.value = true;
    newBoardMembers.value = [];
    contactSearch.value = "";
    fetchContacts();
};

// Modal edit board
const showEditModal = ref(false);
const editingBoard = ref<Board | null>(null);
const editName = ref("");
const editDesc = ref("");
const editColor = ref("");
const editIcon = ref("");
const isSavingEdit = ref(false);

// Member management di edit modal
const editExistingMembers = ref<BoardMember[]>([]); // member yang sudah ada
const editNewMembers = ref<Contact[]>([]); // member baru yang akan ditambah
const editRemovedMemberIds = ref<number[]>([]); // id member yang akan dihapus
const editContactSearch = ref("");
const isLoadingEditContacts = ref(false);
const editFilteredContacts = computed(() =>
    contacts.value.filter(
        (c) =>
            !editNewMembers.value.find((m) => m.id === c.id) &&
            !editExistingMembers.value.find((m) => m.id === c.id) &&
            (c.alias ?? c.name)
                .toLowerCase()
                .includes(editContactSearch.value.toLowerCase())
    )
);

// Hapus board
const deletingBoardId = ref<number | null>(null);

const colorOptions = [
    "#667eea",
    "#764ba2",
    "#ec4899",
    "#ef4444",
    "#f97316",
    "#f59e0b",
    "#10b981",
    "#06b6d4",
];

const iconOptions = [
    "📋",
    "🚀",
    "💼",
    "🎯",
    "📚",
    "🏠",
    "💡",
    "🔧",
    "🎨",
    "⚡",
    "🌟",
    "🔥",
];

// ─── COMPUTED ────────────────────────────────────────────────────────────────
const myBoards = computed(() =>
    boards.value.filter((b) =>
        b.members?.find((m) => m.pivot?.role === "owner" && m.id)
    )
);
const sharedBoards = computed(() =>
    boards.value.filter((b) => {
        const me = b.members?.find((m) => m.pivot?.role === "owner");
        return !me;
    })
);

// ─── FETCH ───────────────────────────────────────────────────────────────────
const fetchBoards = async () => {
    isLoading.value = true;
    try {
        const res = await axios.get("/chat/boards");
        boards.value = res.data.data ?? res.data;
    } catch {
        toast.error("Gagal memuat board");
    } finally {
        isLoading.value = false;
    }
};

onMounted(fetchBoards);

// ─── ACTIONS ─────────────────────────────────────────────────────────────────
const openBoard = (board: Board) => {
    activeBoard.value = board;
};

const backToBoards = () => {
    activeBoard.value = null;
    fetchBoards(); // refresh stats
};

const createBoard = async () => {
    if (!newBoardName.value.trim()) return;
    isCreating.value = true;
    try {
        const res = await axios.post("/chat/boards", {
            name: newBoardName.value,
            description: newBoardDesc.value,
            color: newBoardColor.value,
            icon: newBoardIcon.value,
        });
        const board = res.data.data ?? res.data;

        // Invite member yang sudah dipilih sebelum board dibuat
        for (const member of newBoardMembers.value) {
            try {
                await axios.post(`/chat/boards/${board.id}/members`, {
                    user_id: member.id,
                });
            } catch {
                /* skip jika gagal */
            }
        }

        await fetchBoards(); // refresh agar stats & members muncul
        showCreateModal.value = false;
        newBoardName.value = "";
        newBoardDesc.value = "";
        newBoardColor.value = "#667eea";
        newBoardIcon.value = "📋";
        newBoardMembers.value = [];
        toast.success("Board berhasil dibuat!");
    } catch {
        toast.error("Gagal membuat board");
    } finally {
        isCreating.value = false;
    }
};

const openEdit = (board: Board, e: Event) => {
    e.stopPropagation();
    editingBoard.value = board;
    editName.value = board.name;
    editDesc.value = board.description ?? "";
    editColor.value = board.color;
    editIcon.value = board.icon;
    // Reset member state
    editExistingMembers.value = board.members ? [...board.members] : [];
    editNewMembers.value = [];
    editRemovedMemberIds.value = [];
    editContactSearch.value = "";
    showEditModal.value = true;
    // Load kontak jika belum ada
    if (!contacts.value.length) fetchContacts();
};

const saveEdit = async () => {
    if (!editingBoard.value || !editName.value.trim()) return;
    isSavingEdit.value = true;
    try {
        // Update board info
        const res = await axios.put(`/chat/boards/${editingBoard.value.id}`, {
            name: editName.value,
            description: editDesc.value,
            color: editColor.value,
            icon: editIcon.value,
        });
        const updated = res.data.data ?? res.data;
        const idx = boards.value.findIndex(
            (b) => b.id === editingBoard.value!.id
        );
        if (idx !== -1)
            boards.value[idx] = { ...boards.value[idx], ...updated };

        // Tambah member baru
        for (const member of editNewMembers.value) {
            try {
                await axios.post(
                    `/chat/boards/${editingBoard.value.id}/members`,
                    {
                        user_id: member.id,
                    }
                );
            } catch {
                /* skip jika gagal */
            }
        }

        // Hapus member yang diremove (kecuali owner)
        for (const memberId of editRemovedMemberIds.value) {
            try {
                await axios.delete(
                    `/chat/boards/${editingBoard.value.id}/members/${memberId}`
                );
            } catch {
                /* skip jika gagal */
            }
        }

        await fetchBoards(); // refresh agar members ter-update
        showEditModal.value = false;
        editingBoard.value = null;
        toast.success("Board diperbarui!");
    } catch {
        toast.error("Gagal memperbarui board");
    } finally {
        isSavingEdit.value = false;
    }
};

const deleteBoard = async (board: Board, e: Event) => {
    e.stopPropagation();
    if (deletingBoardId.value === board.id) {
        // Konfirmasi kedua — hapus
        try {
            await axios.delete(`/chat/boards/${board.id}`);
            boards.value = boards.value.filter((b) => b.id !== board.id);
            deletingBoardId.value = null;
            toast.success("Board dihapus");
        } catch {
            toast.error("Gagal menghapus board");
            deletingBoardId.value = null;
        }
    } else {
        deletingBoardId.value = board.id;
        setTimeout(() => {
            if (deletingBoardId.value === board.id)
                deletingBoardId.value = null;
        }, 3000);
    }
};
</script>

<template>
    <!-- Jika sedang buka board → tampilkan kanban -->
    <KanbanBoard v-if="activeBoard" :board="activeBoard" @back="backToBoards" />

    <!-- Halaman daftar board -->
    <div v-else class="bl-page">
        <!-- HEADER -->
        <div class="bl-header">
            <div class="bl-title-area">
                <div class="bl-icon">
                    <LayoutDashboard class="w-6 h-6" />
                </div>
                <div>
                    <h1 class="bl-title">Board Saya</h1>
                    <p class="bl-sub">{{ boards.length }} board tersedia</p>
                </div>
            </div>
            <button class="btn-create-board" @click="openCreateModal()">
                <Plus class="w-4 h-4" />
                <span>Board Baru</span>
            </button>
        </div>

        <!-- LOADING -->
        <div v-if="isLoading" class="bl-loading">
            <div class="spinner-border text-primary"></div>
            <p>Memuat board...</p>
        </div>

        <!-- EMPTY -->
        <div v-else-if="!boards.length" class="bl-empty">
            <span style="font-size: 4rem">📋</span>
            <h2>Belum ada board</h2>
            <p>Buat board pertamamu untuk mulai mengorganisir tugas</p>
            <button class="btn-create-board" @click="openCreateModal()">
                <Plus class="w-4 h-4" /> Buat Board
            </button>
        </div>

        <!-- BOARD GRID -->
        <div v-else>
            <!-- Board milikku -->
            <div class="bl-section">
                <p class="bl-section-label">📁 Board Saya</p>
                <div class="bl-grid">
                    <div
                        v-for="board in boards"
                        :key="board.id"
                        class="bl-card"
                        :style="{ '--board-color': board.color }"
                        @click="openBoard(board)"
                    >
                        <!-- Stripe warna atas -->
                        <div
                            class="bl-card-stripe"
                            :style="{ background: board.color }"
                        ></div>

                        <div class="bl-card-body">
                            <!-- Icon + Name -->
                            <div class="bl-card-head">
                                <span class="bl-icon-emoji">{{
                                    board.icon
                                }}</span>
                                <div class="bl-card-actions">
                                    <button
                                        class="btn-board-action"
                                        @click="openEdit(board, $event)"
                                        title="Edit"
                                    >
                                        <Settings class="w-3 h-3" />
                                    </button>
                                    <button
                                        class="btn-board-action"
                                        :class="{
                                            danger:
                                                deletingBoardId === board.id,
                                        }"
                                        @click="deleteBoard(board, $event)"
                                        :title="
                                            deletingBoardId === board.id
                                                ? 'Klik lagi untuk hapus'
                                                : 'Hapus'
                                        "
                                    >
                                        <AlertTriangle
                                            v-if="deletingBoardId === board.id"
                                            class="w-3 h-3"
                                        />
                                        <Trash2 v-else class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>

                            <h3 class="bl-card-name">{{ board.name }}</h3>
                            <p v-if="board.description" class="bl-card-desc">
                                {{ board.description }}
                            </p>

                            <!-- Progress -->
                            <div
                                v-if="board.stats && board.stats.total > 0"
                                class="bl-progress"
                            >
                                <div class="bl-progress-track">
                                    <div
                                        class="bl-progress-fill"
                                        :style="{
                                            width: board.stats.progress + '%',
                                            background: board.color,
                                        }"
                                    ></div>
                                </div>
                                <span class="bl-progress-label">
                                    {{ board.stats.done }}/{{
                                        board.stats.total
                                    }}
                                    selesai
                                </span>
                            </div>
                            <div v-else class="bl-progress">
                                <span
                                    class="bl-progress-label"
                                    style="color: #9ca3af"
                                    >Belum ada tugas</span
                                >
                            </div>

                            <!-- Members -->
                            <div class="bl-card-footer">
                                <div class="bl-members">
                                    <div
                                        v-for="(m, i) in board.members?.slice(
                                            0,
                                            4
                                        )"
                                        :key="m.id"
                                        class="bl-member-avatar"
                                        :style="{ zIndex: 10 - i }"
                                        :title="m.name"
                                    >
                                        <img
                                            v-if="m.profile_photo_url"
                                            :src="m.profile_photo_url"
                                        />
                                        <span v-else>{{
                                            m.name[0]?.toUpperCase()
                                        }}</span>
                                    </div>
                                    <span
                                        v-if="(board.members?.length ?? 0) > 4"
                                        class="bl-member-more"
                                    >
                                        +{{ (board.members?.length ?? 0) - 4 }}
                                    </span>
                                </div>
                                <ChevronRight class="w-4 h-4 bl-arrow" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL BUAT BOARD -->
        <Teleport to="body">
            <div
                v-if="showCreateModal"
                class="bl-overlay"
                @click.self="showCreateModal = false"
            >
                <div class="bl-modal">
                    <div class="bl-modal-head">
                        <h2>Board Baru</h2>
                        <button
                            class="btn-close-x"
                            @click="showCreateModal = false"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Preview -->
                    <div
                        class="bl-preview"
                        :style="{ borderColor: newBoardColor }"
                    >
                        <span class="bl-preview-icon">{{ newBoardIcon }}</span>
                        <span class="bl-preview-name">{{
                            newBoardName || "Nama Board"
                        }}</span>
                    </div>

                    <!-- Form -->
                    <div class="bl-form-group">
                        <label>Nama Board *</label>
                        <input
                            v-model="newBoardName"
                            type="text"
                            class="bl-input"
                            placeholder="Contoh: Skripsi, Kerjaan..."
                            @keydown.enter="createBoard"
                            maxlength="100"
                        />
                    </div>
                    <div class="bl-form-group">
                        <label>Deskripsi</label>
                        <textarea
                            v-model="newBoardDesc"
                            class="bl-input bl-textarea"
                            placeholder="Opsional..."
                            rows="1"
                            maxlength="500"
                        ></textarea>
                    </div>

                    <!-- Icon picker -->
                    <div class="bl-form-group">
                        <label>Icon</label>
                        <div class="bl-icon-picker">
                            <button
                                v-for="ico in iconOptions"
                                :key="ico"
                                class="bl-icon-btn"
                                :class="{ active: newBoardIcon === ico }"
                                @click="newBoardIcon = ico"
                            >
                                {{ ico }}
                            </button>
                        </div>
                    </div>

                    <!-- Color picker -->
                    <div class="bl-form-group">
                        <label>Warna</label>
                        <div class="bl-color-picker">
                            <button
                                v-for="c in colorOptions"
                                :key="c"
                                class="bl-color-btn"
                                :class="{ active: newBoardColor === c }"
                                :style="{ background: c }"
                                @click="newBoardColor = c"
                            >
                                <Check
                                    v-if="newBoardColor === c"
                                    class="w-3 h-3 text-white"
                                />
                            </button>
                        </div>
                    </div>

                    <!-- Undang anggota dari kontak -->
                    <div class="bl-form-group">
                        <label
                            >Undang Anggota
                            <span style="color: #9ca3af; font-weight: 400"
                                >(opsional)</span
                            ></label
                        >

                        <!-- Chip anggota terpilih -->
                        <div
                            v-if="newBoardMembers.length"
                            class="bl-selected-members"
                        >
                            <div
                                v-for="m in newBoardMembers"
                                :key="m.id"
                                class="bl-member-chip"
                            >
                                <div class="bl-chip-avatar">
                                    <img
                                        v-if="m.profile_photo_url"
                                        :src="m.profile_photo_url"
                                    />
                                    <span v-else>{{
                                        (m.alias ?? m.name)?.[0]?.toUpperCase()
                                    }}</span>
                                </div>
                                <span class="bl-chip-name">{{
                                    m.alias ?? m.name
                                }}</span>
                                <button
                                    class="bl-chip-remove"
                                    @click="removeMemberFromNew(m.id)"
                                >
                                    <X class="w-3 h-3" />
                                </button>
                            </div>
                        </div>

                        <!-- Search kontak -->
                        <input
                            v-model="contactSearch"
                            type="text"
                            class="bl-input"
                            placeholder="Cari nama kontak..."
                        />

                        <!-- Loading -->
                        <div
                            v-if="isLoadingContacts"
                            class="bl-contact-loading"
                        >
                            <span
                                class="spinner-border spinner-border-sm"
                            ></span>
                            Memuat kontak...
                        </div>

                        <!-- Daftar kontak -->
                        <div
                            v-else-if="filteredContacts.length"
                            class="bl-contact-list"
                        >
                            <div
                                v-for="c in filteredContacts.slice(0, 5)"
                                :key="c.id"
                                class="bl-contact-item"
                                @click="addMemberToNew(c)"
                            >
                                <div class="bl-contact-avatar">
                                    <img
                                        v-if="c.profile_photo_url"
                                        :src="c.profile_photo_url"
                                    />
                                    <span v-else>{{
                                        (c.alias ?? c.name)?.[0]?.toUpperCase()
                                    }}</span>
                                </div>
                                <div class="bl-contact-info">
                                    <p class="bl-contact-name">
                                        {{ c.alias ?? c.name }}
                                    </p>
                                    <p class="bl-contact-email">
                                        {{ c.email }}
                                    </p>
                                </div>
                                <Plus class="w-3 h-3 bl-contact-add-icon" />
                            </div>
                        </div>
                        <p
                            v-else-if="
                                !isLoadingContacts && contacts.length === 0
                            "
                            class="bl-contact-empty"
                        >
                            Belum ada kontak tersimpan
                        </p>
                        <p
                            v-else-if="
                                contactSearch && !filteredContacts.length
                            "
                            class="bl-contact-empty"
                        >
                            Kontak tidak ditemukan
                        </p>
                    </div>

                    <div class="bl-modal-footer">
                        <button
                            class="btn-cancel-sm"
                            @click="showCreateModal = false"
                        >
                            Batal
                        </button>
                        <button
                            class="btn-save-main"
                            @click="createBoard"
                            :disabled="isCreating || !newBoardName.trim()"
                        >
                            {{ isCreating ? "Membuat..." : "Buat Board" }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- MODAL EDIT BOARD -->
        <Teleport to="body">
            <div
                v-if="showEditModal"
                class="bl-overlay"
                @click.self="showEditModal = false"
            >
                <div class="bl-modal">
                    <div class="bl-modal-head">
                        <h2>Edit Board</h2>
                        <button
                            class="btn-close-x"
                            @click="showEditModal = false"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="bl-preview" :style="{ borderColor: editColor }">
                        <span class="bl-preview-icon">{{ editIcon }}</span>
                        <span class="bl-preview-name">{{
                            editName || "Nama Board"
                        }}</span>
                    </div>

                    <div class="bl-form-group">
                        <label>Nama Board *</label>
                        <input
                            v-model="editName"
                            type="text"
                            class="bl-input"
                            maxlength="100"
                        />
                    </div>
                    <div class="bl-form-group">
                        <label>Deskripsi</label>
                        <textarea
                            v-model="editDesc"
                            class="bl-input bl-textarea"
                            rows="1"
                            maxlength="500"
                        ></textarea>
                    </div>
                    <div class="bl-form-group">
                        <label>Icon</label>
                        <div class="bl-icon-picker">
                            <button
                                v-for="ico in iconOptions"
                                :key="ico"
                                class="bl-icon-btn"
                                :class="{ active: editIcon === ico }"
                                @click="editIcon = ico"
                            >
                                {{ ico }}
                            </button>
                        </div>
                    </div>
                    <div class="bl-form-group">
                        <label>Warna</label>
                        <div class="bl-color-picker">
                            <button
                                v-for="c in colorOptions"
                                :key="c"
                                class="bl-color-btn"
                                :class="{ active: editColor === c }"
                                :style="{ background: c }"
                                @click="editColor = c"
                            >
                                <Check
                                    v-if="editColor === c"
                                    class="w-3 h-3 text-white"
                                />
                            </button>
                        </div>
                    </div>

                    <!-- Kelola Anggota -->
                    <div class="bl-form-group">
                        <label>Anggota Board</label>

                        <!-- Member yang sudah ada -->
                        <div
                            v-if="editExistingMembers.length"
                            class="bl-existing-members"
                        >
                            <div
                                v-for="m in editExistingMembers"
                                :key="m.id"
                                class="bl-existing-member-item"
                                :class="{
                                    'will-remove':
                                        editRemovedMemberIds.includes(m.id),
                                }"
                            >
                                <div
                                    class="bl-contact-avatar"
                                    style="
                                        width: 28px;
                                        height: 28px;
                                        font-size: 0.7rem;
                                    "
                                >
                                    <img
                                        v-if="m.profile_photo_url"
                                        :src="m.profile_photo_url"
                                    />
                                    <span v-else>{{
                                        m.name[0]?.toUpperCase()
                                    }}</span>
                                </div>
                                <div class="bl-contact-info">
                                    <p
                                        class="bl-contact-name"
                                        style="font-size: 0.8rem"
                                    >
                                        {{ m.name }}
                                    </p>
                                    <p
                                        v-if="m.pivot?.role === 'owner'"
                                        class="bl-member-role"
                                    >
                                        Owner
                                    </p>
                                </div>
                                <button
                                    v-if="m.pivot?.role !== 'owner'"
                                    class="bl-member-toggle-btn"
                                    :class="{
                                        'is-removing':
                                            editRemovedMemberIds.includes(m.id),
                                    }"
                                    @click="
                                        editRemovedMemberIds.includes(m.id)
                                            ? editRemovedMemberIds.splice(
                                                  editRemovedMemberIds.indexOf(
                                                      m.id
                                                  ),
                                                  1
                                              )
                                            : editRemovedMemberIds.push(m.id)
                                    "
                                    :title="
                                        editRemovedMemberIds.includes(m.id)
                                            ? 'Batalkan'
                                            : 'Keluarkan'
                                    "
                                >
                                    <X
                                        v-if="
                                            !editRemovedMemberIds.includes(m.id)
                                        "
                                        class="w-3 h-3"
                                    />
                                    <Check v-else class="w-3 h-3" />
                                </button>
                            </div>
                        </div>

                        <!-- Chip member baru -->
                        <div
                            v-if="editNewMembers.length"
                            class="bl-selected-members"
                            style="margin-top: 8px"
                        >
                            <div
                                v-for="m in editNewMembers"
                                :key="m.id"
                                class="bl-member-chip"
                            >
                                <div class="bl-chip-avatar">
                                    <img
                                        v-if="m.profile_photo_url"
                                        :src="m.profile_photo_url"
                                    />
                                    <span v-else>{{
                                        (m.alias ?? m.name)?.[0]?.toUpperCase()
                                    }}</span>
                                </div>
                                <span class="bl-chip-name">{{
                                    m.alias ?? m.name
                                }}</span>
                                <button
                                    class="bl-chip-remove"
                                    @click="
                                        editNewMembers = editNewMembers.filter(
                                            (x) => x.id !== m.id
                                        )
                                    "
                                >
                                    <X class="w-3 h-3" />
                                </button>
                            </div>
                        </div>

                        <!-- Search kontak baru -->
                        <input
                            v-model="editContactSearch"
                            type="text"
                            class="bl-input"
                            style="margin-top: 8px"
                            placeholder="Cari kontak untuk ditambah..."
                        />

                        <!-- Loading kontak -->
                        <div
                            v-if="isLoadingContacts"
                            class="bl-contact-loading"
                        >
                            <span
                                class="spinner-border spinner-border-sm"
                            ></span>
                            Memuat kontak...
                        </div>

                        <!-- Daftar kontak -->
                        <div
                            v-else-if="
                                editFilteredContacts.length && editContactSearch
                            "
                            class="bl-contact-list"
                        >
                            <div
                                v-for="c in editFilteredContacts.slice(0, 5)"
                                :key="c.id"
                                class="bl-contact-item"
                                @click="
                                    editNewMembers.push(c);
                                    editContactSearch = '';
                                "
                            >
                                <div class="bl-contact-avatar">
                                    <img
                                        v-if="c.profile_photo_url"
                                        :src="c.profile_photo_url"
                                    />
                                    <span v-else>{{
                                        (c.alias ?? c.name)?.[0]?.toUpperCase()
                                    }}</span>
                                </div>
                                <div class="bl-contact-info">
                                    <p class="bl-contact-name">
                                        {{ c.alias ?? c.name }}
                                    </p>
                                    <p class="bl-contact-email">
                                        {{ c.email }}
                                    </p>
                                </div>
                                <Plus class="w-3 h-3 bl-contact-add-icon" />
                            </div>
                        </div>
                        <p
                            v-else-if="
                                editContactSearch &&
                                !editFilteredContacts.length
                            "
                            class="bl-contact-empty"
                        >
                            Kontak tidak ditemukan
                        </p>
                    </div>

                    <div class="bl-modal-footer">
                        <button
                            class="btn-cancel-sm"
                            @click="showEditModal = false"
                        >
                            Batal
                        </button>
                        <button
                            class="btn-save-main"
                            @click="saveEdit"
                            :disabled="isSavingEdit || !editName.trim()"
                        >
                            {{ isSavingEdit ? "Menyimpan..." : "Simpan" }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
/* ─── PAGE ─── */
.bl-page {
    padding: 24px;
    min-height: 100vh;
    background: #f9f9f9;
}

/* ─── HEADER ─── */
.bl-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28px;
}
.bl-title-area {
    display: flex;
    align-items: center;
    gap: 14px;
}
.bl-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
.bl-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
}
.bl-sub {
    font-size: 0.875rem;
    color: #6b7280;
    margin: 2px 0 0;
}

/* ─── SECTION ─── */
.bl-section-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 14px;
}

/* ─── GRID ─── */
.bl-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

/* ─── CARD ─── */
.bl-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}
.bl-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}
.bl-card-stripe {
    height: 5px;
    width: 100%;
}
.bl-card-body {
    padding: 16px;
}
.bl-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.bl-icon-emoji {
    font-size: 1.8rem;
    line-height: 1;
}
.bl-card-actions {
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.15s;
}
.bl-card:hover .bl-card-actions {
    opacity: 1;
}
.btn-board-action {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.15s;
}
.btn-board-action:hover {
    background: #f3f4f6;
    color: #111827;
}
.btn-board-action.danger {
    border-color: #fca5a5;
    background: #fef2f2;
    color: #ef4444;
}

.bl-card-name {
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 4px;
}
.bl-card-desc {
    font-size: 0.8rem;
    color: #6b7280;
    margin: 0 0 12px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* ─── PROGRESS ─── */
.bl-progress {
    margin-bottom: 12px;
}
.bl-progress-track {
    height: 5px;
    background: #f3f4f6;
    border-radius: 99px;
    overflow: hidden;
    margin-bottom: 4px;
}
.bl-progress-fill {
    height: 100%;
    border-radius: 99px;
    transition: width 0.3s;
}
.bl-progress-label {
    font-size: 0.72rem;
    color: #6b7280;
}

/* ─── CARD FOOTER ─── */
.bl-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.bl-members {
    display: flex;
    align-items: center;
}
.bl-member-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
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
.bl-member-avatar:first-child {
    margin-left: 0;
}
.bl-member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.bl-member-more {
    font-size: 0.7rem;
    color: #9ca3af;
    margin-left: 6px;
}
.bl-arrow {
    color: #d1d5db;
}

/* ─── EMPTY & LOADING ─── */
.bl-loading,
.bl-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 80px 20px;
    color: #6b7280;
}
.bl-empty h2 {
    font-size: 1.2rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
}
.bl-empty p {
    margin: 0;
    font-size: 0.9rem;
}

/* ─── BUTTONS ─── */
.btn-create-board {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 10px 16px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s;
}
.btn-create-board:hover {
    opacity: 0.9;
}
.btn-close-x {
    background: none;
    border: none;
    cursor: pointer;
    color: #6b7280;
    display: flex;
    padding: 4px;
    border-radius: 6px;
}
.btn-close-x:hover {
    background: #f3f4f6;
}
.btn-cancel-sm {
    padding: 8px 14px;
    border: 1px solid #e5e7eb;
    background: white;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
    color: #374151;
}
.btn-save-main {
    padding: 8px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
}
.btn-save-main:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ─── MODAL ─── */
.bl-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 16px;
}
.bl-modal {
    background: white;
    border-radius: 20px;
    padding: 20px;
    width: 100%;
    max-width: 440px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    /* Batasi tinggi modal agar tidak melampaui layar */
    max-height: 90dvh;
    overflow-y: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.bl-modal::-webkit-scrollbar {
    display: none;
}
.bl-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.bl-modal-head h2 {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
    color: #111827;
}
.bl-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

/* ─── PREVIEW ─── */
.bl-preview {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 2px solid;
    border-radius: 10px;
    background: #f9fafb;
}
.bl-preview-icon {
    font-size: 1.3rem;
}
.bl-preview-name {
    font-weight: 600;
    color: #111827;
    font-size: 0.875rem;
}

/* ─── FORM ─── */
.bl-form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.bl-form-group label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #374151;
}
.bl-input {
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.875rem;
    outline: none;
    font-family: inherit;
    color: #111827;
}
.bl-input:focus {
    border-color: #667eea;
}
.bl-textarea {
    resize: none;
}

/* ─── PICKERS ─── */
.bl-icon-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.bl-icon-btn {
    width: 32px;
    height: 32px;
    border: 2px solid transparent;
    border-radius: 8px;
    background: #f3f4f6;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.bl-icon-btn.active {
    border-color: #667eea;
    background: #eff1ff;
}
.bl-color-picker {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.bl-color-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 3px solid transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    outline: 2px solid transparent;
    transition: outline 0.15s;
}
.bl-color-btn.active {
    outline: 2px solid #374151;
    outline-offset: 2px;
}

/* ─── DARK MODE ─── */
[data-bs-theme="dark"] .bl-page {
    background: #0f0f1a;
}
[data-bs-theme="dark"] .bl-title {
    color: #e5e7eb;
}
[data-bs-theme="dark"] .bl-card {
    background: #1e1e2d;
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .bl-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}
[data-bs-theme="dark"] .bl-card-name {
    color: #e5e7eb;
}
[data-bs-theme="dark"] .bl-card-desc {
    color: #6b7280;
}
[data-bs-theme="dark"] .btn-board-action {
    background: #1e1e2d;
    border-color: #2b2b40;
    color: #6b7280;
}
[data-bs-theme="dark"] .btn-board-action:hover {
    background: #2b2b40;
    color: #e5e7eb;
}
[data-bs-theme="dark"] .bl-progress-track {
    background: #2b2b40;
}
[data-bs-theme="dark"] .bl-member-avatar {
    border-color: #1e1e2d;
}
[data-bs-theme="dark"] .bl-modal {
    background: #1e1e2d;
}
[data-bs-theme="dark"] .bl-modal-head h2 {
    color: #e5e7eb;
}
[data-bs-theme="dark"] .bl-preview {
    background: #16162a;
}
[data-bs-theme="dark"] .bl-preview-name {
    color: #e5e7eb;
}
[data-bs-theme="dark"] .bl-form-group label {
    color: #9ca3af;
}
[data-bs-theme="dark"] .bl-input {
    background: #151521;
    border-color: #2b2b40;
    color: #e5e7eb;
}
[data-bs-theme="dark"] .bl-icon-btn {
    background: #2b2b40;
}
[data-bs-theme="dark"] .bl-icon-btn.active {
    background: #1e1e2d;
    border-color: #667eea;
}
[data-bs-theme="dark"] .btn-cancel-sm {
    background: #1e1e2d;
    border-color: #2b2b40;
    color: #9ca3af;
}

/* ─── ANIMATIONS ─── */
@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bl-page {
    animation: fadeIn 0.3s ease;
}
.bl-header {
    animation: fadeUp 0.35s ease;
}
.bl-section-label {
    animation: fadeUp 0.4s ease;
}

.bl-card {
    animation: fadeUp 0.4s ease both;
}
.bl-card:nth-child(1) {
    animation-delay: 0.05s;
}
.bl-card:nth-child(2) {
    animation-delay: 0.1s;
}
.bl-card:nth-child(3) {
    animation-delay: 0.15s;
}
.bl-card:nth-child(4) {
    animation-delay: 0.2s;
}
.bl-card:nth-child(5) {
    animation-delay: 0.25s;
}
.bl-card:nth-child(6) {
    animation-delay: 0.3s;
}

.bl-overlay {
    animation: fadeIn 0.2s ease;
}
.bl-modal {
    animation: scaleIn 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

/* ─── CONTACTS PICKER ─── */
.bl-selected-members {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}
.bl-member-chip {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #eff1ff;
    border: 1px solid #c7d2fe;
    border-radius: 20px;
    padding: 3px 8px 3px 3px;
    animation: scaleIn 0.15s ease;
}
.bl-chip-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 0.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.bl-chip-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.bl-chip-name {
    font-size: 0.78rem;
    font-weight: 600;
    color: #3730a3;
}
.bl-chip-remove {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: none;
    background: #c7d2fe;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #4338ca;
    transition: background 0.15s;
}
.bl-chip-remove:hover {
    background: #a5b4fc;
}

.bl-contact-loading {
    font-size: 0.8rem;
    color: #9ca3af;
    padding: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.bl-contact-list {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow-y: auto;
    margin-top: 6px;
    animation: slideDown 0.2s ease;
    /* Tampilkan max 3 item, sisanya scroll */
    max-height: 156px;
    scrollbar-width: thin;
    scrollbar-color: #e5e7eb transparent;
}
.bl-contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    cursor: pointer;
    transition: background 0.12s;
}
.bl-contact-item:not(:last-child) {
    border-bottom: 1px solid #f3f4f6;
}
.bl-contact-item:hover {
    background: #f9fafb;
}
.bl-contact-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    flex-shrink: 0;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.bl-contact-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.bl-contact-info {
    flex: 1;
    min-width: 0;
}
.bl-contact-name {
    font-size: 0.83rem;
    font-weight: 600;
    color: #111827;
    margin: 0;
}
.bl-contact-email {
    font-size: 0.72rem;
    color: #9ca3af;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bl-contact-add-icon {
    color: #667eea;
    flex-shrink: 0;
}
.bl-contact-empty {
    font-size: 0.8rem;
    color: #9ca3af;
    margin: 8px 0 0;
    text-align: center;
}

/* ─── EXISTING MEMBERS (edit modal) ─── */
.bl-existing-members {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}
.bl-existing-member-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 12px;
    transition: background 0.12s;
}
.bl-existing-member-item:not(:last-child) {
    border-bottom: 1px solid #f3f4f6;
}
.bl-existing-member-item.will-remove {
    background: #fef2f2;
    opacity: 0.6;
    text-decoration: line-through;
}
.bl-member-role {
    font-size: 0.68rem;
    color: #667eea;
    font-weight: 600;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.bl-member-toggle-btn {
    margin-left: auto;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 1.5px solid #e5e7eb;
    background: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    transition: all 0.15s;
    flex-shrink: 0;
}
.bl-member-toggle-btn:hover {
    border-color: #ef4444;
    color: #ef4444;
    background: #fef2f2;
}
.bl-member-toggle-btn.is-removing {
    border-color: #10b981;
    color: #10b981;
    background: #f0fdf4;
}

/* dark mode contacts */
[data-bs-theme="dark"] .bl-member-chip {
    background: #1e1e2d;
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .bl-chip-name {
    color: #818cf8;
}
[data-bs-theme="dark"] .bl-chip-remove {
    background: #2b2b40;
    color: #818cf8;
}
[data-bs-theme="dark"] .bl-contact-list {
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .bl-contact-item:hover {
    background: #16162a;
}
[data-bs-theme="dark"] .bl-contact-item {
    border-bottom-color: #2b2b40;
}
[data-bs-theme="dark"] .bl-contact-name {
    color: #e5e7eb;
}
[data-bs-theme="dark"] .bl-existing-members {
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .bl-existing-member-item {
    border-bottom-color: #2b2b40;
}
[data-bs-theme="dark"] .bl-existing-member-item.will-remove {
    background: #2d1515;
}
[data-bs-theme="dark"] .bl-member-toggle-btn {
    background: #2b2b40;
    border-color: #3b3b55;
    color: #6b7280;
}
[data-bs-theme="dark"] .bl-member-toggle-btn:hover {
    border-color: #ef4444;
    color: #f87171;
    background: #2d1515;
}
[data-bs-theme="dark"] .bl-member-toggle-btn.is-removing {
    border-color: #10b981;
    color: #34d399;
    background: #052e16;
}
</style>
