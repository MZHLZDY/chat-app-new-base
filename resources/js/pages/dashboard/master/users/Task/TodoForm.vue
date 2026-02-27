<script setup lang="ts">
import { ref, watch, nextTick, computed } from "vue";
import {
    X,
    Sparkles,
    PenLine,
    AlignLeft,
    Calendar,
    Users,
    Search,
    ChevronDown,
    Flag,
    Clock,
} from "lucide-vue-next";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";

// --- PROPS & EMITS ---
const props = defineProps<{
    show: boolean;
    boardId: number;
    defaultStatus?: "todo" | "in_progress" | "done";
}>();

const emit = defineEmits(["close", "created"]);

// --- TYPES ---
interface Contact {
    id: number;
    name: string;
    email: string;
    profile_photo_url?: string;
}

// --- STATE ---
const title = ref("");
const description = ref("");
const dueDate = ref("");
const dueTime = ref("");
const priority = ref<"low" | "medium" | "high" | "">("medium");
const selectedAssignees = ref<Contact[]>([]);
const status = ref<"todo" | "in_progress" | "done">("todo");

const isLoading = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);
const errorMessage = ref("");

// Contact Search
const contactSearch = ref("");
const contacts = ref<Contact[]>([]);
const showContactDropdown = ref(false);
const isLoadingContacts = ref(false);

// Sections visibility
const showAdvanced = ref(false);

// --- WATCHERS ---
watch(
    () => props.show,
    async (newVal) => {
        if (newVal) {
            // Reset form
            title.value = "";
            description.value = "";
            dueDate.value = "";
            dueTime.value = "23:59";
            priority.value = "medium";
            selectedAssignees.value = [];
            contactSearch.value = "";
            errorMessage.value = "";
            showAdvanced.value = false;
            status.value = props.defaultStatus ?? "todo";

            nextTick(() => {
                setTimeout(() => inputRef.value?.focus(), 150);
            });
        }
    }
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
            (c: Contact) => !selectedAssignees.value.find((a) => a.id === c.id)
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

// Helper: delay tutup dropdown agar click item sempat terpanggil dulu
const closeDropdownDelayed = () => {
    setTimeout(() => {
        showContactDropdown.value = false;
    }, 200);
};

// --- ACTIONS ---
const submit = async () => {
    if (!title.value.trim()) {
        inputRef.value?.parentElement?.classList.add("shake-animation");
        setTimeout(
            () =>
                inputRef.value?.parentElement?.classList.remove(
                    "shake-animation"
                ),
            500
        );
        errorMessage.value = "Judul tugas tidak boleh kosong.";
        return;
    }

    isLoading.value = true;
    errorMessage.value = "";

    try {
        let dueDateTime: string | null = null;
        if (dueDate.value) {
            dueDateTime = `${dueDate.value} ${dueTime.value || "23:59"}:00`;
        }

        const payload = {
            board_id: props.boardId,
            title: title.value,
            description: description.value,
            status: status.value,
            priority: priority.value || "medium",
            due_date: dueDateTime,
            assignee_ids: selectedAssignees.value.map((a) => a.id),
        };

        await axios.post("/chat/todos", payload);
        toast.success("Tugas berhasil disimpan! 🎉");
        emit("created");
        emit("close");
    } catch (err: any) {
        if (err.response?.status === 422) {
            const errors = err.response.data.errors;
            errorMessage.value = Object.values(errors).flat()[0] as string;
        } else {
            toast.error("Gagal menyimpan tugas. Cek koneksi.");
        }
    } finally {
        isLoading.value = false;
    }
};

const addAssignee = (contact: Contact) => {
    if (!selectedAssignees.value.find((a) => a.id === contact.id)) {
        selectedAssignees.value.push(contact);
    }
    contactSearch.value = "";
    contacts.value = [];
    showContactDropdown.value = false;
};

const removeAssignee = (id: number) => {
    selectedAssignees.value = selectedAssignees.value.filter(
        (a) => a.id !== id
    );
};

const handleClose = () => {
    emit("close");
};

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
</script>

<template>
    <Teleport to="body">
        <div class="modal-wrapper">
            <Transition name="backdrop">
                <div
                    v-if="show"
                    class="modal-backdrop"
                    @click="handleClose"
                ></div>
            </Transition>

            <Transition name="modal-spring">
                <div v-if="show" class="modal-container">
                    <div class="modal-card">
                        <!-- HEADER -->
                        <div class="modal-header">
                            <div class="d-flex align-items-center gap-3">
                                <div class="modal-icon">
                                    <Sparkles class="w-5 h-5" />
                                </div>
                                <div>
                                    <h5 class="modal-title">Tugas Baru</h5>
                                    <p class="modal-sub">
                                        Tambahkan tugas ke board-mu
                                    </p>
                                </div>
                            </div>
                            <button class="btn-close-x" @click="handleClose">
                                <X class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- BODY -->
                        <div class="modal-body">
                            <!-- Title -->
                            <div
                                class="field-group"
                                :class="{ error: errorMessage }"
                            >
                                <label class="field-label">
                                    <PenLine class="w-3 h-3" /> Judul Tugas *
                                </label>
                                <input
                                    ref="inputRef"
                                    v-model="title"
                                    type="text"
                                    class="field-input"
                                    placeholder="Apa yang perlu dikerjakan?"
                                    :disabled="isLoading"
                                />
                            </div>
                            <p v-if="errorMessage" class="error-text">
                                {{ errorMessage }}
                            </p>

                            <!-- Description -->
                            <div class="field-group">
                                <label class="field-label">
                                    <AlignLeft class="w-3 h-3" /> Deskripsi
                                </label>
                                <textarea
                                    v-model="description"
                                    rows="2"
                                    class="field-input"
                                    placeholder="Tambahkan detail atau catatan..."
                                    style="resize: none; min-height: 60px"
                                    :disabled="isLoading"
                                ></textarea>
                            </div>

                            <!-- Row: Deadline + Priority -->
                            <div class="field-row">
                                <!-- Deadline -->
                                <div class="field-group flex-1">
                                    <label class="field-label">
                                        <Calendar class="w-3 h-3" /> Deadline
                                    </label>
                                    <div class="deadline-inputs">
                                        <input
                                            v-model="dueDate"
                                            type="date"
                                            class="field-input date-inp"
                                            :min="minDate"
                                            :disabled="isLoading"
                                        />
                                        <input
                                            v-model="dueTime"
                                            type="time"
                                            class="field-input time-inp"
                                            :disabled="isLoading || !dueDate"
                                        />
                                    </div>
                                </div>

                                <!-- Priority -->
                                <div
                                    class="field-group"
                                    style="min-width: 120px"
                                >
                                    <label class="field-label">
                                        <Flag class="w-3 h-3" /> Prioritas
                                    </label>
                                    <div class="priority-btns">
                                        <button
                                            v-for="(cfg, key) in priorityConfig"
                                            :key="key"
                                            type="button"
                                            class="priority-btn"
                                            :class="{
                                                active: priority === key,
                                            }"
                                            :style="
                                                priority === key
                                                    ? {
                                                          background: cfg.bg,
                                                          color: cfg.color,
                                                          borderColor:
                                                              cfg.color,
                                                      }
                                                    : {}
                                            "
                                            @click="priority = key as any"
                                        >
                                            {{ cfg.label }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="field-group">
                                <label class="field-label">Status Awal</label>
                                <div class="status-btns">
                                    <button
                                        v-for="s in statusConfig"
                                        :key="s.key"
                                        type="button"
                                        class="status-btn"
                                        :class="{ active: status === s.key }"
                                        :style="
                                            status === s.key
                                                ? {
                                                      borderColor: s.color,
                                                      color: s.color,
                                                  }
                                                : {}
                                        "
                                        @click="status = s.key as any"
                                    >
                                        <span
                                            class="status-dot"
                                            :style="{ background: s.color }"
                                        ></span>
                                        {{ s.label }}
                                    </button>
                                </div>
                            </div>

                            <!-- Assignees -->
                            <div class="field-group">
                                <label class="field-label">
                                    <Users class="w-3 h-3" /> Undang Anggota
                                </label>

                                <!-- Selected Assignees -->
                                <div
                                    v-if="selectedAssignees.length"
                                    class="selected-assignees"
                                >
                                    <div
                                        v-for="a in selectedAssignees"
                                        :key="a.id"
                                        class="assignee-chip"
                                    >
                                        <div class="chip-avatar">
                                            <img
                                                v-if="a.profile_photo_url"
                                                :src="a.profile_photo_url"
                                                :alt="a.name"
                                            />
                                            <span v-else>{{
                                                a.name?.[0]?.toUpperCase() ??
                                                "?"
                                            }}</span>
                                        </div>
                                        <span class="chip-name">{{
                                            a.name.split(" ")[0]
                                        }}</span>
                                        <button
                                            class="chip-remove"
                                            @click="removeAssignee(a.id)"
                                            type="button"
                                        >
                                            <X class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Search Contact -->
                                <div class="contact-search-wrap">
                                    <Search class="contact-search-ico" />
                                    <input
                                        v-model="contactSearch"
                                        type="text"
                                        class="contact-search-input"
                                        placeholder="Cari kontak..."
                                        @focus="showContactDropdown = true"
                                        @blur="closeDropdownDelayed"
                                        :disabled="isLoading"
                                    />
                                    <!-- Dropdown -->
                                    <div
                                        v-if="
                                            showContactDropdown &&
                                            (contacts.length ||
                                                isLoadingContacts)
                                        "
                                        class="contact-dropdown"
                                    >
                                        <div
                                            v-if="isLoadingContacts"
                                            class="contact-loading"
                                        >
                                            <span
                                                class="spinner-border spinner-border-sm"
                                            ></span>
                                            Mencari...
                                        </div>
                                        <div
                                            v-for="c in contacts"
                                            :key="c.id"
                                            class="contact-item"
                                            @mousedown.prevent="addAssignee(c)"
                                        >
                                            <div class="contact-avatar">
                                                <img
                                                    v-if="c.profile_photo_url"
                                                    :src="c.profile_photo_url"
                                                    :alt="c.name"
                                                />
                                                <span v-else>{{
                                                    c.name?.[0]?.toUpperCase() ??
                                                    "?"
                                                }}</span>
                                            </div>
                                            <div>
                                                <p class="contact-name">
                                                    {{ c.name }}
                                                </p>
                                                <p class="contact-email">
                                                    {{ c.email }}
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            v-if="
                                                !isLoadingContacts &&
                                                !contacts.length &&
                                                contactSearch.length >= 2
                                            "
                                            class="contact-empty"
                                        >
                                            Kontak tidak ditemukan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FOOTER -->
                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn-cancel"
                                @click="handleClose"
                                :disabled="isLoading"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                class="btn-submit"
                                @click="submit"
                                :disabled="isLoading"
                            >
                                <span
                                    v-if="isLoading"
                                    class="spinner-border spinner-border-sm me-2"
                                ></span>
                                <span v-else>✨ Simpan Tugas</span>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<style scoped>
/* --- LAYOUT --- */
.modal-wrapper {
    position: fixed;
    inset: 0;
    z-index: 9990;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(4px);
    pointer-events: auto;
    z-index: 9991;
}
.modal-container {
    z-index: 9995;
    width: 100%;
    max-width: 560px;
    padding: 16px;
    pointer-events: auto;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-card {
    background: #fff;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.3);
}

/* --- HEADER --- */
.modal-header {
    padding: 22px 24px 14px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1.5px solid #f1f5f9;
}
.modal-icon {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #5e6ad2, #8b5cf6);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
.modal-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1a2e;
    margin: 0;
}
.modal-sub {
    font-size: 0.8rem;
    color: #9ca3af;
    margin: 2px 0 0;
}
.btn-close-x {
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    padding: 6px;
    border-radius: 8px;
    transition: all 0.2s;
}
.btn-close-x:hover {
    background: #fef2f2;
    color: #ef4444;
    transform: rotate(90deg);
}

/* --- BODY --- */
.modal-body {
    padding: 16px 24px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* --- FIELDS --- */
.field-group {
    background: #f8f9fc;
    border: 2px solid transparent;
    border-radius: 14px;
    padding: 10px 14px;
    transition: all 0.2s;
}
.field-group:focus-within {
    background: #fff;
    border-color: #5e6ad2;
    box-shadow: 0 0 0 4px rgba(94, 106, 210, 0.1);
}
.field-group.error {
    border-color: #ef4444;
    background: #fef2f2;
}
.field-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
}
.field-input {
    width: 100%;
    border: none;
    background: transparent;
    font-size: 0.95rem;
    color: #1a1a2e;
    outline: none;
    font-weight: 500;
}
.field-input::placeholder {
    color: #d1d5db;
    font-weight: 400;
}
.error-text {
    color: #ef4444;
    font-size: 0.82rem;
    margin: -8px 0 0 4px;
}

/* Field Row */
.field-row {
    display: flex;
    gap: 12px;
}
.flex-1 {
    flex: 1;
}

/* Deadline inputs */
.deadline-inputs {
    display: flex;
    gap: 8px;
}
.date-inp {
    flex: 1;
    font-size: 0.88rem;
}
.time-inp {
    width: 90px;
    font-size: 0.88rem;
}
.time-inp:disabled {
    opacity: 0.4;
}

/* Priority */
.priority-btns {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
.priority-btn {
    padding: 4px 10px;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    background: none;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    color: #6b7280;
}
.priority-btn:hover {
    border-color: #9ca3af;
}
.priority-btn.active {
    font-weight: 700;
}

/* Status Buttons */
.status-btns {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.status-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    background: none;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.15s;
}
.status-btn.active {
    font-weight: 700;
}
.status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

/* Assignees */
.selected-assignees {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}
.assignee-chip {
    display: flex;
    align-items: center;
    gap: 5px;
    background: #eef0ff;
    border: 1px solid #c7d2fe;
    border-radius: 20px;
    padding: 3px 8px 3px 4px;
}
.chip-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: linear-gradient(135deg, #5e6ad2, #8b5cf6);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.chip-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.chip-name {
    font-size: 0.8rem;
    font-weight: 600;
    color: #5e6ad2;
}
.chip-remove {
    background: none;
    border: none;
    cursor: pointer;
    color: #9ca3af;
    padding: 0;
    display: flex;
    align-items: center;
}
.chip-remove:hover {
    color: #ef4444;
}

/* Contact Search */
.contact-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.contact-search-ico {
    position: absolute;
    left: 10px;
    width: 14px;
    height: 14px;
    color: #9ca3af;
}
.contact-search-input {
    width: 100%;
    padding: 8px 12px 8px 32px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.88rem;
    outline: none;
    background: #fff;
    color: #1a1a2e;
    transition: border-color 0.2s;
}
.contact-search-input:focus {
    border-color: #5e6ad2;
}
.contact-dropdown {
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
.contact-loading {
    padding: 12px 16px;
    font-size: 0.85rem;
    color: #9ca3af;
    display: flex;
    align-items: center;
    gap: 8px;
}
.contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    cursor: pointer;
    transition: background 0.15s;
}
.contact-item:hover {
    background: #f8f9fc;
}
.contact-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #5e6ad2, #8b5cf6);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}
.contact-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.contact-name {
    font-size: 0.88rem;
    font-weight: 600;
    color: #1a1a2e;
    margin: 0;
}
.contact-email {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
}
.contact-empty {
    padding: 12px 16px;
    font-size: 0.85rem;
    color: #9ca3af;
    text-align: center;
}

/* --- FOOTER --- */
.modal-footer {
    padding: 14px 24px 20px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1.5px solid #f1f5f9;
}
.btn-cancel {
    padding: 10px 20px;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    background: none;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.2s;
}
.btn-cancel:hover {
    background: #f1f5f9;
}
.btn-submit {
    padding: 10px 24px;
    background: linear-gradient(135deg, #5e6ad2, #8b5cf6);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(94, 106, 210, 0.35);
    display: flex;
    align-items: center;
}
.btn-submit:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(94, 106, 210, 0.45);
}
.btn-submit:disabled {
    opacity: 0.7;
    transform: none;
    cursor: not-allowed;
}

/* --- ANIMATIONS --- */
.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.3s ease;
}
.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}
.modal-spring-enter-active {
    transition: all 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-spring-leave-active {
    transition: all 0.25s ease;
}
.modal-spring-enter-from {
    opacity: 0;
    transform: scale(0.85) translateY(30px);
}
.modal-spring-leave-to {
    opacity: 0;
    transform: scale(0.95);
}

.shake-animation {
    animation: shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
}
@keyframes shake {
    10%,
    90% {
        transform: translate3d(-1px, 0, 0);
    }
    20%,
    80% {
        transform: translate3d(2px, 0, 0);
    }
    30%,
    50%,
    70% {
        transform: translate3d(-4px, 0, 0);
    }
    40%,
    60% {
        transform: translate3d(4px, 0, 0);
    }
}

/* --- DARK MODE --- */
:global(.dark) .modal-card {
    background: #1e1e2d;
}
:global(.dark) .modal-title {
    color: #e5e7eb;
}
:global(.dark) .field-group {
    background: #151521;
}
:global(.dark) .field-group:focus-within {
    background: #1a1a2e;
}
:global(.dark) .field-input {
    color: #e5e7eb;
}
:global(.dark) .contact-search-input {
    background: #151521;
    border-color: #2b2b40;
    color: #e5e7eb;
}
:global(.dark) .contact-dropdown {
    background: #1e1e2d;
    border-color: #2b2b40;
}
:global(.dark) .contact-item:hover {
    background: #2b2b40;
}
:global(.dark) .contact-name {
    color: #e5e7eb;
}
:global(.dark) .btn-cancel {
    border-color: #2b2b40;
    color: #9ca3af;
}
:global(.dark) .btn-cancel:hover {
    background: #2b2b40;
}
:global(.dark) .modal-header,
:global(.dark) .modal-footer {
    border-color: #2b2b40;
}
</style>
