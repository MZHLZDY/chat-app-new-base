<script setup lang="ts">
import { ref, computed, watch } from "vue";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import { useAuthStore } from "@/stores/authStore";

const props = defineProps({
    groupId: { type: [String, Number], default: null },
    modelValue: { type: Boolean, default: false },
});

const emit = defineEmits([
    "update:modelValue",
    "close",
    "refresh",
    "group-updated",
]);

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

// State
const activeTab = ref<"info" | "members">("info");
const isLoading = ref(false);
const isFetching = ref(false);

// Data Grup & Foto
const form = ref({
    name: "",
    description: "",
});
const existingPhotoUrl = ref("");
const photoPreview = ref<string | null>(null);
const photoFile = ref<File | null>(null);

const members = ref<any[]>([]);

// State
const searchQuery = ref("");
const searchResults = ref<any[]>([]);
const selectedUsersToAdd = ref<number[]>([]);
const isSearching = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

// State Modal Kick
const showKickModal = ref(false);
const memberToKick = ref<any>(null);
const isKicking = ref(false);

// Title Modal
const modalTitle = computed(() =>
    props.groupId ? "Kelola Grup" : "Buat Grup Baru"
);

// FETCH DATA
const fetchGroupData = async () => {
    if (!props.groupId) return;

    isFetching.value = true;
    try {
        const response = await axios.get(`/chat/groups/${props.groupId}`);
        const data = response.data.data;

        form.value.name = data.name;
        existingPhotoUrl.value = data.photo ? `/storage/${data.photo}` : "";
        members.value = data.members || [];
    } catch (error) {
        console.error(error);
        toast.error("Gagal memuat data grup.");
        emit("close");
    } finally {
        isFetching.value = false;
    }
};

// Actions
const triggerFileInput = () => {
    fileInputRef.value?.click();
};

const handleFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        const file = input.files[0];

        if (!file.type.startsWith("image/")) {
            toast.error("Harap pilih file gambar (JPG/PNG).");
            return;
        }

        photoFile.value = file;
        photoPreview.value = URL.createObjectURL(file);
    }
};

const submitInfo = async () => {
    if (!form.value.name) {
        toast.error("Nama grup tidak boleh kosong.");
        return;
    }

    isLoading.value = true;
    try {
        const formData = new FormData();
        formData.append("name", form.value.name);

        if (photoFile.value) {
            formData.append("photo", photoFile.value);
        }

        if (props.groupId) {
            formData.append("_method", "PUT");

            const response = await axios.post(
                `/chat/groups/${props.groupId}`,
                formData,
                {
                    headers: { "Content-Type": "multipart/form-data" },
                }
            );

            toast.success("Info grup diperbarui");

            if (response.data.data.photo) {
                existingPhotoUrl.value = `/storage/${response.data.data.photo}`;
                photoPreview.value = null;
                photoFile.value = null;
            }

            emit("group-updated", response.data.data);
        } else {
            await axios.post(`/chat/groups`, formData, {
                headers: { "Content-Type": "multipart/form-data" },
            });
            toast.success("Grup dibuat");
            emit("refresh");
            emit("close");
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || "Gagal menyimpan.");
        console.error(error);
    } finally {
        isLoading.value = false;
    }
};

const searchUsers = async () => {
    if (searchQuery.value.length < 2) return;
    isSearching.value = true;
    try {
        const response = await axios.get(`/chat/users/search`, {
            params: {
                q: searchQuery.value,
                exclude_group: props.groupId,
            },
        });
        searchResults.value = response.data.data;
    } catch (error) {
        console.error(error);
    } finally {
        isSearching.value = false;
    }
};

const toggleUserSelection = (userId: number) => {
    if (selectedUsersToAdd.value.includes(userId)) {
        selectedUsersToAdd.value = selectedUsersToAdd.value.filter(
            (id) => id !== userId
        );
    } else {
        selectedUsersToAdd.value.push(userId);
    }
};

const addSelectedMembers = async () => {
    if (selectedUsersToAdd.value.length === 0) return;

    isLoading.value = true;
    try {
        await axios.post(`/chat/groups/${props.groupId}/members`, {
            user_ids: selectedUsersToAdd.value,
        });
        toast.success("Anggota berhasil ditambahkan!");

        searchQuery.value = "";
        searchResults.value = [];
        selectedUsersToAdd.value = [];

        await fetchGroupData();
    } catch (error) {
        toast.error("Gagal menambahkan anggota.");
    } finally {
        isLoading.value = false;
    }
};

const confirmKick = (member: any) => {
    memberToKick.value = member;
    showKickModal.value = true;
};

const processKickMember = async () => {
    if (!memberToKick.value || !props.groupId) return;

    isKicking.value = true;
    try {
        await axios.delete(
            `/chat/groups/${props.groupId}/members/${memberToKick.value.id}`
        );

        toast.success(`${memberToKick.value.name} berhasil dikeluarkan.`);
        await fetchGroupData();
        cancelKick();
    } catch (error: any) {
        console.error("Gagal kick member:", error);
        toast.error(
            error.response?.data?.message || "Gagal mengeluarkan anggota."
        );
    } finally {
        isKicking.value = false;
    }
};

const cancelKick = () => {
    showKickModal.value = false;
    memberToKick.value = null;
};

// Watcher
watch(
    () => props.groupId,
    (newVal) => {
        if (newVal) {
            activeTab.value = "info";
            photoPreview.value = null;
            photoFile.value = null;
            fetchGroupData();
        } else {
            form.value.name = "";
            existingPhotoUrl.value = "";
            members.value = [];
            searchQuery.value = "";
            searchResults.value = [];
            selectedUsersToAdd.value = [];
        }
    },
    { immediate: true }
);
</script>

<template>
    <!-- EDIT GROUP PANEL -->
    <div class="eg-wrapper">
        <!-- Header Gradient -->
        <div class="eg-header">
            <div class="eg-header-bg"></div>
            <div class="eg-header-content">
                <div class="eg-icon-wrap"><i class="fas fa-users-cog"></i></div>
                <div>
                    <h3 class="eg-title">{{ modalTitle }}</h3>
                    <p class="eg-subtitle">Kelola informasi dan anggota grup</p>
                </div>
            </div>
            <button class="eg-close-btn" @click="$emit('close')">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Tabs -->
        <div v-if="props.groupId" class="eg-tabs">
            <button
                class="eg-tab"
                :class="{ active: activeTab === 'info' }"
                @click="activeTab = 'info'"
            >
                <i class="fas fa-info-circle me-2"></i>Info Grup
            </button>
            <button
                class="eg-tab"
                :class="{ active: activeTab === 'members' }"
                @click="activeTab = 'members'"
            >
                <i class="fas fa-users me-2"></i>Anggota ({{ members.length }})
            </button>
        </div>

        <!-- Body -->
        <div class="eg-body">
            <div v-if="isFetching" class="eg-loading">
                <div class="eg-spinner"></div>
                <span>Memuat data...</span>
            </div>

            <div v-else>
                <!-- TAB: INFO -->
                <div v-if="activeTab === 'info'">
                    <form @submit.prevent="submitInfo" id="groupForm">
                        <!-- Avatar -->
                        <div class="eg-avatar-section">
                            <div class="eg-avatar-wrap">
                                <img
                                    :src="
                                        photoPreview ||
                                        existingPhotoUrl ||
                                        '/media/avatars/blank.png'
                                    "
                                    class="eg-avatar"
                                    alt="Foto Grup"
                                />
                                <button
                                    type="button"
                                    @click="triggerFileInput"
                                    class="eg-avatar-camera"
                                    title="Ganti Foto"
                                >
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <p class="eg-avatar-hint">
                                Klik ikon kamera untuk mengganti foto
                            </p>
                            <input
                                type="file"
                                ref="fileInputRef"
                                class="d-none"
                                accept="image/*"
                                @change="handleFileChange"
                            />
                        </div>

                        <!-- Nama Grup -->
                        <div class="eg-field">
                            <label class="eg-label">Nama Grup</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="eg-input"
                                placeholder="Contoh: Tim IT"
                                required
                            />
                        </div>
                    </form>
                </div>

                <!-- TAB: MEMBERS -->
                <div v-if="activeTab === 'members'">
                    <!-- Search Add Member -->
                    <div class="eg-add-member-box">
                        <div class="eg-add-member-title">
                            <i class="fas fa-user-plus me-2"></i>Tambah Anggota
                            Baru
                        </div>
                        <div class="eg-search-wrap">
                            <i class="fas fa-search eg-search-icon"></i>
                            <input
                                v-model="searchQuery"
                                @input="searchUsers"
                                type="text"
                                class="eg-search-input"
                                placeholder="Cari nama kontak..."
                            />
                        </div>

                        <!-- Search Results -->
                        <div
                            v-if="searchResults.length > 0"
                            class="eg-search-results"
                        >
                            <div
                                v-for="user in searchResults"
                                :key="user.id"
                                class="eg-search-item"
                                :class="{
                                    selected: selectedUsersToAdd.includes(
                                        user.id
                                    ),
                                }"
                                @click="toggleUserSelection(user.id)"
                            >
                                <div
                                    class="eg-check"
                                    :class="{
                                        active: selectedUsersToAdd.includes(
                                            user.id
                                        ),
                                    }"
                                >
                                    <i
                                        class="fas fa-check"
                                        v-if="
                                            selectedUsersToAdd.includes(user.id)
                                        "
                                    ></i>
                                </div>
                                <div class="eg-user-avatar">
                                    <img
                                        v-if="user.photo"
                                        :src="`/storage/${user.photo}`"
                                    />
                                    <span v-else>{{
                                        user.name?.charAt(0)?.toUpperCase()
                                    }}</span>
                                </div>
                                <div class="eg-user-info">
                                    <span class="eg-user-name">{{
                                        user.name
                                    }}</span>
                                    <span class="eg-user-sub">{{
                                        user.email
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Add Button -->
                        <button
                            v-if="selectedUsersToAdd.length > 0"
                            type="button"
                            @click="addSelectedMembers"
                            class="eg-add-btn"
                            :disabled="isSearching"
                        >
                            <span
                                v-if="isSearching"
                                class="eg-btn-spinner"
                            ></span>
                            <i v-else class="fas fa-user-plus me-2"></i>
                            Tambah {{ selectedUsersToAdd.length }} Anggota
                        </button>
                    </div>

                    <!-- Member List -->
                    <div class="eg-section-title">Anggota Saat Ini</div>
                    <div class="eg-member-list">
                        <div
                            v-for="member in members"
                            :key="member.id"
                            class="eg-member-row"
                        >
                            <div class="eg-member-avatar">
                                <img
                                    :src="
                                        member.photo
                                            ? `/storage/${member.photo}`
                                            : '/media/avatars/blank.png'
                                    "
                                    alt="member"
                                />
                            </div>
                            <div class="eg-member-info">
                                <span class="eg-member-name">
                                    {{
                                        member.id === currentUser?.id
                                            ? "Anda"
                                            : member.name
                                    }}
                                </span>
                                <span class="eg-member-sub">{{
                                    member.email
                                }}</span>
                            </div>
                            <span
                                v-if="member.pivot?.is_admin"
                                class="eg-member-badge"
                                >Admin</span
                            >
                            <button
                                v-if="member.id !== currentUser?.id"
                                @click="confirmKick(member)"
                                class="eg-kick-btn"
                                type="button"
                                title="Keluarkan"
                            >
                                <i class="fas fa-user-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="eg-footer">
            <button type="button" class="eg-btn-cancel" @click="$emit('close')">
                Tutup
            </button>
            <button
                v-if="activeTab === 'info'"
                type="submit"
                form="groupForm"
                class="eg-btn-save"
                :disabled="isLoading || isFetching"
            >
                <span v-if="isLoading" class="eg-btn-spinner"></span>
                <i v-else class="fas fa-save me-2"></i>
                Simpan Perubahan
            </button>
        </div>
    </div>

    <!-- ═══ KICK CONFIRM MODAL ═══ -->
    <transition name="modal-pop">
        <div
            v-if="showKickModal"
            class="kick-backdrop"
            @click.self="cancelKick"
        >
            <div class="kick-card">
                <div class="kick-icon-wrap">
                    <i class="fas fa-user-times"></i>
                </div>
                <h4 class="kick-title">Keluarkan Anggota?</h4>
                <p class="kick-desc">
                    Apakah Anda yakin ingin mengeluarkan
                    <strong class="kick-name">{{ memberToKick?.name }}</strong>
                    dari grup ini?
                </p>
                <div class="kick-actions">
                    <button
                        @click="processKickMember"
                        class="kick-btn danger"
                        :disabled="isKicking"
                    >
                        <span
                            v-if="isKicking"
                            class="eg-btn-spinner white"
                        ></span>
                        <i v-else class="fas fa-user-times me-2"></i>Ya,
                        Keluarkan
                    </button>
                    <button
                        @click="cancelKick"
                        class="kick-btn ghost"
                        :disabled="isKicking"
                    >
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
/* ═══ KEYFRAMES ═══════════════════════════════════════════════════════════ */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
@keyframes modalPop {
    from {
        opacity: 0;
        transform: scale(0.88) translateY(18px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
@keyframes popIn {
    0% {
        transform: scale(0.8);
        opacity: 0;
    }
    70% {
        transform: scale(1.05);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ═══ WRAPPER ══════════════════════════════════════════════════════════════ */
.eg-wrapper {
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 0;
    overflow: hidden;
    animation: slideIn 0.25s ease;
}

/* ═══ HEADER ═══════════════════════════════════════════════════════════════ */
.eg-header {
    position: relative;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 14px;
    overflow: hidden;
}
.eg-header-bg {
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='30'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.eg-header-content {
    display: flex;
    align-items: center;
    gap: 14px;
    flex: 1;
    position: relative;
}
.eg-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.eg-title {
    font-size: 1rem;
    font-weight: 800;
    color: white;
    margin: 0;
}
.eg-subtitle {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.75);
    margin: 0;
}
.eg-close-btn {
    position: relative;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.8rem;
    flex-shrink: 0;
    transition: background 0.2s, transform 0.15s;
}
.eg-close-btn:hover {
    background: rgba(255, 255, 255, 0.32);
    transform: rotate(90deg);
}

/* ═══ TABS ══════════════════════════════════════════════════════════════════ */
.eg-tabs {
    display: flex;
    border-bottom: 1px solid #f0f2f5;
    padding: 0 16px;
    background: #fff;
}
.eg-tab {
    flex: 1;
    padding: 13px 8px;
    border: none;
    background: transparent;
    font-size: 0.84rem;
    font-weight: 600;
    color: #94a3b8;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: color 0.2s, border-color 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.eg-tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
}
.eg-tab:hover:not(.active) {
    color: #64748b;
}

/* ═══ BODY ══════════════════════════════════════════════════════════════════ */
.eg-body {
    padding: 20px;
    max-height: 58vh;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(102, 126, 234, 0.2) transparent;
}
.eg-body::-webkit-scrollbar {
    width: 4px;
}
.eg-body::-webkit-scrollbar-thumb {
    background: rgba(102, 126, 234, 0.25);
    border-radius: 10px;
}

.eg-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 40px;
    color: #94a3b8;
    font-size: 0.85rem;
}
.eg-spinner {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 3px solid rgba(102, 126, 234, 0.15);
    border-top-color: #667eea;
    animation: spin 1s linear infinite;
}

/* ── Avatar Section ── */
.eg-avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 24px;
}
.eg-avatar-wrap {
    position: relative;
    display: inline-block;
}
.eg-avatar {
    width: 88px;
    height: 88px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(102, 126, 234, 0.25);
    box-shadow: 0 4px 16px rgba(102, 126, 234, 0.2);
    animation: popIn 0.35s ease;
}
.eg-avatar-camera {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid white;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 0.65rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.2s;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
}
.eg-avatar-camera:hover {
    transform: scale(1.12);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.5);
}
.eg-avatar-hint {
    font-size: 0.72rem;
    color: #94a3b8;
    margin: 8px 0 0;
}

/* ── Field ── */
.eg-field {
    margin-bottom: 16px;
}
.eg-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: #374151;
    display: block;
    margin-bottom: 6px;
}
.eg-input {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    background: #f9fafb;
    font-size: 0.88rem;
    color: #1a202c;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}
.eg-input:focus {
    border-color: #667eea;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* ── Add Member Box ── */
.eg-add-member-box {
    background: rgba(102, 126, 234, 0.05);
    border: 1.5px dashed rgba(102, 126, 234, 0.3);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}
.eg-add-member-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 12px;
}
.eg-search-wrap {
    position: relative;
    margin-bottom: 10px;
}
.eg-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.8rem;
}
.eg-search-input {
    width: 100%;
    padding: 9px 12px 9px 34px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    font-size: 0.84rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.eg-search-input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.eg-search-results {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #f0f2f5;
    margin-bottom: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}
.eg-search-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    cursor: pointer;
    transition: background 0.15s;
    border-bottom: 1px solid #f9fafb;
}
.eg-search-item:last-child {
    border-bottom: none;
}
.eg-search-item:hover {
    background: rgba(102, 126, 234, 0.05);
}
.eg-search-item.selected {
    background: rgba(102, 126, 234, 0.08);
}

.eg-check {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    border: 2px solid #e2e8f0;
    background: #fff;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    color: white;
    transition: all 0.15s;
}
.eg-check.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-color: #667eea;
}

.eg-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    overflow: hidden;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
    font-weight: 700;
    flex-shrink: 0;
}
.eg-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.eg-user-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}
.eg-user-name {
    font-size: 0.83rem;
    font-weight: 700;
    color: #1a202c;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.eg-user-sub {
    font-size: 0.72rem;
    color: #94a3b8;
}

.eg-add-btn {
    width: 100%;
    padding: 9px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 0.83rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: opacity 0.2s, transform 0.15s;
    box-shadow: 0 3px 10px rgba(102, 126, 234, 0.35);
}
.eg-add-btn:hover:not(:disabled) {
    opacity: 0.9;
    transform: translateY(-1px);
}
.eg-add-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ── Section Title ── */
.eg-section-title {
    font-size: 0.76rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}

/* ── Member List ── */
.eg-member-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.eg-member-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #f9fafb;
    transition: background 0.2s;
    animation: fadeIn 0.2s ease;
}
.eg-member-row:hover {
    background: rgba(102, 126, 234, 0.07);
}
.eg-member-avatar img {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #f0f0f0;
}
.eg-member-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}
.eg-member-name {
    font-size: 0.84rem;
    font-weight: 700;
    color: #1a202c;
}
.eg-member-sub {
    font-size: 0.74rem;
    color: #94a3b8;
}
.eg-member-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    flex-shrink: 0;
}
.eg-kick-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: none;
    background: rgba(239, 68, 68, 0.08);
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s, transform 0.15s;
}
.eg-kick-btn:hover {
    background: rgba(239, 68, 68, 0.16);
    transform: scale(1.08);
}

/* ═══ FOOTER ════════════════════════════════════════════════════════════════ */
.eg-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid #f0f2f5;
    background: #fff;
}
.eg-btn-cancel {
    padding: 9px 20px;
    border-radius: 10px;
    border: 1.5px solid #e5e7eb;
    background: transparent;
    color: #64748b;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
}
.eg-btn-cancel:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.eg-btn-save {
    padding: 9px 20px;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: opacity 0.2s, transform 0.15s;
    box-shadow: 0 3px 10px rgba(102, 126, 234, 0.35);
}
.eg-btn-save:hover:not(:disabled) {
    opacity: 0.9;
    transform: translateY(-1px);
}
.eg-btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.eg-btn-spinner {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    animation: spin 1s linear infinite;
    display: inline-block;
}
.eg-btn-spinner.white {
    border-color: rgba(255, 255, 255, 0.3);
    border-top-color: white;
}

/* ═══ KICK CONFIRM ═══════════════════════════════════════════════════════════ */
.kick-backdrop {
    position: fixed;
    inset: 0;
    z-index: 10500;
    background: rgba(15, 15, 30, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    animation: fadeIn 0.2s ease;
}
.kick-card {
    background: #fff;
    border-radius: 18px;
    padding: 32px 28px 24px;
    width: 100%;
    max-width: 340px;
    text-align: center;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.2);
    animation: modalPop 0.28s cubic-bezier(0.34, 1.2, 0.64, 1) both;
}
.kick-icon-wrap {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    margin: 0 auto 14px;
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    animation: popIn 0.35s ease;
}
.kick-title {
    font-size: 1rem;
    font-weight: 800;
    color: #1a202c;
    margin: 0 0 8px;
}
.kick-desc {
    font-size: 0.84rem;
    color: #64748b;
    line-height: 1.6;
    margin: 0 0 20px;
}
.kick-name {
    color: #ef4444;
}
.kick-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.kick-btn {
    width: 100%;
    padding: 10px;
    border-radius: 10px;
    border: none;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: opacity 0.2s, transform 0.15s;
}
.kick-btn:hover:not(:disabled) {
    opacity: 0.88;
    transform: translateY(-1px);
}
.kick-btn.danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    box-shadow: 0 3px 10px rgba(239, 68, 68, 0.3);
}
.kick-btn.ghost {
    background: transparent;
    color: #94a3b8;
    font-weight: 500;
}
.kick-btn.ghost:hover {
    background: #f8fafc;
    color: #64748b;
}

/* ═══ VUE TRANSITIONS ═══════════════════════════════════════════════════════ */
.modal-pop-enter-active {
    animation: modalPop 0.28s cubic-bezier(0.34, 1.2, 0.64, 1) both;
}
.modal-pop-leave-active {
    animation: modalPop 0.2s ease reverse both;
}

/* ═══ DARK MODE ══════════════════════════════════════════════════════════════ */
[data-bs-theme="dark"] .eg-wrapper,
[data-bs-theme="dark"] .eg-footer,
[data-bs-theme="dark"] .eg-body {
    background: #1e1e2d;
}
[data-bs-theme="dark"] .eg-tabs {
    background: #1e1e2d;
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .eg-tab {
    color: #6b7280;
}
[data-bs-theme="dark"] .eg-tab.active {
    color: #a5b4fc;
}
[data-bs-theme="dark"] .eg-input {
    background: #1b1b29;
    border-color: #2b2b40;
    color: #e1e1e1;
}
[data-bs-theme="dark"] .eg-input:focus {
    background: #22223a;
    border-color: #667eea;
}
[data-bs-theme="dark"] .eg-label {
    color: #a1a5b7;
}
[data-bs-theme="dark"] .eg-add-member-box {
    background: rgba(102, 126, 234, 0.08);
    border-color: rgba(102, 126, 234, 0.25);
}
[data-bs-theme="dark"] .eg-search-input {
    background: #1b1b29;
    border-color: #2b2b40;
    color: #e1e1e1;
}
[data-bs-theme="dark"] .eg-search-results {
    background: #1e1e2d;
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .eg-search-item:hover {
    background: rgba(102, 126, 234, 0.1);
}
[data-bs-theme="dark"] .eg-search-item.selected {
    background: rgba(102, 126, 234, 0.15);
}
[data-bs-theme="dark"] .eg-check {
    background: #2b2b40;
    border-color: #3d3d5c;
}
[data-bs-theme="dark"] .eg-user-name {
    color: #e1e1e1;
}
[data-bs-theme="dark"] .eg-member-row {
    background: #2b2b40;
}
[data-bs-theme="dark"] .eg-member-row:hover {
    background: rgba(102, 126, 234, 0.12);
}
[data-bs-theme="dark"] .eg-member-name {
    color: #e1e1e1;
}
[data-bs-theme="dark"] .eg-btn-cancel {
    border-color: #2b2b40;
    color: #a1a5b7;
}
[data-bs-theme="dark"] .eg-btn-cancel:hover {
    background: #2b2b40;
}
[data-bs-theme="dark"] .eg-footer {
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .kick-card {
    background: #1e1e2d;
}
[data-bs-theme="dark"] .kick-title {
    color: #e1e1e1;
}
[data-bs-theme="dark"] .kick-desc {
    color: #7e8299;
}
[data-bs-theme="dark"] .kick-btn.ghost:hover {
    background: #2b2b40;
}
</style>
