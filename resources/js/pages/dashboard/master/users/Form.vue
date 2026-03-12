<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import * as Yup from "yup";
import { ErrorMessage, Field, Form as VeeForm } from "vee-validate";

const emit = defineEmits(["close", "refresh"]);

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const users = ref<any[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const searchQuery = ref("");

const formData = ref({
    name: "",
    member_ids: [] as number[],
});

const schema = Yup.object().shape({
    name: Yup.string()
        .required("Nama grup wajib diisi")
        .max(100, "Maksimal 100 karakter"),
    member_ids: Yup.array().min(1, "Pilih minimal 1 anggota grup"),
});

const filteredUsers = computed(() =>
    users.value.filter((u) =>
        u.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
);

const fetchUsers = async () => {
    isLoading.value = true;
    try {
        const { data } = await axios.get("/master/users");
        const allUsers = data.data ? data.data : data;
        users.value = allUsers.filter(
            (u: any) => u.id !== currentUser.value.id
        );
    } catch (error) {
        console.error(error);
        toast.error("Gagal memuat daftar user");
    } finally {
        isLoading.value = false;
    }
};

const submit = async () => {
    isSubmitting.value = true;
    try {
        await axios.post("/chat/groups", formData.value);
        toast.success("Grup berhasil dibuat");
        emit("refresh");
        emit("close");
    } catch (error: any) {
        console.error(error);
        toast.error(error.response?.data?.message || "Gagal membuat grup");
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    fetchUsers();
});
</script>

<template>
    <div class="gf-wrapper">
        <!-- Header -->
        <div class="gf-header">
            <div class="gf-header-content">
                <div class="gf-icon-wrap"><i class="fas fa-users"></i></div>
                <div>
                    <h3 class="gf-title">Buat Grup Baru</h3>
                    <p class="gf-subtitle">Isi detail dan pilih anggota grup</p>
                </div>
            </div>
            <button class="gf-close-btn" @click="$emit('close')">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body -->
        <VeeForm :validation-schema="schema" @submit="submit">
            <div class="gf-body">
                <!-- Nama Grup -->
                <div class="gf-field">
                    <label class="gf-label">
                        <i class="fas fa-tag me-2"></i>Nama Grup
                        <span class="gf-required">*</span>
                    </label>
                    <Field
                        type="text"
                        name="name"
                        v-model="formData.name"
                        class="gf-input"
                        placeholder="Contoh: Tim IT, Alumni 2024..."
                    />
                    <div class="gf-error"><ErrorMessage name="name" /></div>
                </div>

                <!-- Pilih Anggota -->
                <div class="gf-field">
                    <label class="gf-label">
                        <i class="fas fa-user-friends me-2"></i>Pilih Anggota
                        <span class="gf-required">*</span>
                        <span
                            v-if="formData.member_ids.length"
                            class="gf-count-badge"
                        >
                            {{ formData.member_ids.length }} dipilih
                        </span>
                    </label>

                    <!-- Search -->
                    <div class="gf-search-wrap">
                        <i class="fas fa-search gf-search-icon"></i>
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="gf-search-input"
                            placeholder="Cari anggota..."
                        />
                    </div>

                    <!-- Member List -->
                    <div class="gf-member-list">
                        <div v-if="isLoading" class="gf-loading">
                            <div class="gf-spinner"></div>
                            <span>Memuat pengguna...</span>
                        </div>
                        <div
                            v-else-if="filteredUsers.length === 0"
                            class="gf-empty"
                        >
                            <i class="fas fa-user-slash"></i>
                            <span>Tidak ada pengguna ditemukan</span>
                        </div>
                        <label
                            v-else
                            v-for="user in filteredUsers"
                            :key="user.id"
                            class="gf-member-row"
                            :class="{
                                selected: formData.member_ids.includes(user.id),
                            }"
                        >
                            <input
                                type="checkbox"
                                name="member_ids"
                                :value="user.id"
                                v-model="formData.member_ids"
                                class="d-none"
                            />
                            <div class="gf-member-avatar">
                                <img
                                    v-if="user.photo"
                                    :src="`/storage/${user.photo}`"
                                />
                                <span v-else>{{
                                    user.name?.charAt(0)?.toUpperCase()
                                }}</span>
                            </div>
                            <div class="gf-member-info">
                                <span class="gf-member-name">{{
                                    user.name
                                }}</span>
                                <span class="gf-member-sub">{{
                                    user.email
                                }}</span>
                            </div>
                            <div
                                class="gf-check-box"
                                :class="{
                                    active: formData.member_ids.includes(
                                        user.id
                                    ),
                                }"
                            >
                                <i
                                    class="fas fa-check"
                                    v-if="formData.member_ids.includes(user.id)"
                                ></i>
                            </div>
                        </label>
                    </div>
                    <div class="gf-error">
                        <ErrorMessage name="member_ids" />
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="gf-footer">
                <button
                    type="button"
                    class="gf-btn-cancel"
                    @click="$emit('close')"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="gf-btn-submit"
                    :disabled="isSubmitting"
                >
                    <span v-if="isSubmitting" class="gf-spinner-sm"></span>
                    <i v-else class="fas fa-check me-2"></i>
                    {{ isSubmitting ? "Membuat..." : "Buat Grup" }}
                </button>
            </div>
        </VeeForm>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
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
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
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

/* ═══ WRAPPER ══════════════════════════════════════════════════════════════ */
.gf-wrapper {
    display: flex;
    flex-direction: column;
    background: #fff;
    animation: slideIn 0.25s ease;
}

/* ═══ HEADER ═══════════════════════════════════════════════════════════════ */
.gf-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}
.gf-header-content {
    display: flex;
    align-items: center;
    gap: 14px;
}
.gf-icon-wrap {
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
.gf-title {
    font-size: 1rem;
    font-weight: 800;
    color: white;
    margin: 0;
}
.gf-subtitle {
    font-size: 0.74rem;
    color: rgba(255, 255, 255, 0.75);
    margin: 0;
}
.gf-close-btn {
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
    transition: background 0.2s, transform 0.15s;
}
.gf-close-btn:hover {
    background: rgba(255, 255, 255, 0.32);
    transform: rotate(90deg);
}

/* ═══ BODY ══════════════════════════════════════════════════════════════════ */
.gf-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.gf-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.gf-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 4px;
}
.gf-required {
    color: #ef4444;
}
.gf-count-badge {
    margin-left: auto;
    font-size: 0.68rem;
    padding: 2px 8px;
    border-radius: 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-weight: 700;
    animation: popIn 0.3s ease;
}

.gf-input {
    padding: 10px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    background: #f9fafb;
    font-size: 0.88rem;
    color: #1a202c;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    width: 100%;
}
.gf-input:focus {
    border-color: #667eea;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
.gf-error {
    font-size: 0.76rem;
    color: #ef4444;
    min-height: 16px;
}

/* ── Search ── */
.gf-search-wrap {
    position: relative;
}
.gf-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 0.8rem;
}
.gf-search-input {
    width: 100%;
    padding: 9px 12px 9px 34px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    background: #f9fafb;
    font-size: 0.84rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.gf-search-input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: #fff;
}

/* ── Member List ── */
.gf-member-list {
    max-height: 220px;
    overflow-y: auto;
    border: 1.5px solid #f0f2f5;
    border-radius: 12px;
    scrollbar-width: thin;
    scrollbar-color: rgba(102, 126, 234, 0.2) transparent;
}
.gf-member-list::-webkit-scrollbar {
    width: 4px;
}
.gf-member-list::-webkit-scrollbar-thumb {
    background: rgba(102, 126, 234, 0.25);
    border-radius: 10px;
}

.gf-loading,
.gf-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 28px;
    color: #94a3b8;
    font-size: 0.83rem;
}
.gf-loading i,
.gf-empty i {
    font-size: 1.4rem;
    opacity: 0.5;
}

.gf-spinner {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 3px solid rgba(102, 126, 234, 0.15);
    border-top-color: #667eea;
    animation: spin 1s linear infinite;
}
.gf-spinner-sm {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    display: inline-block;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    animation: spin 1s linear infinite;
}

.gf-member-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    cursor: pointer;
    transition: background 0.15s;
    border-bottom: 1px solid #f9fafb;
    animation: fadeIn 0.2s ease;
}
.gf-member-row:last-child {
    border-bottom: none;
}
.gf-member-row:hover {
    background: rgba(102, 126, 234, 0.05);
}
.gf-member-row.selected {
    background: rgba(102, 126, 234, 0.08);
}

.gf-member-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.85rem;
    font-weight: 700;
}
.gf-member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.gf-member-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}
.gf-member-name {
    font-size: 0.84rem;
    font-weight: 700;
    color: #1a202c;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.gf-member-sub {
    font-size: 0.73rem;
    color: #94a3b8;
}

.gf-check-box {
    width: 22px;
    height: 22px;
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
.gf-check-box.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-color: #667eea;
}

/* ═══ FOOTER ════════════════════════════════════════════════════════════════ */
.gf-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid #f0f2f5;
    background: #fff;
}
.gf-btn-cancel {
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
.gf-btn-cancel:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.gf-btn-submit {
    padding: 9px 22px;
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
.gf-btn-submit:hover:not(:disabled) {
    opacity: 0.9;
    transform: translateY(-1px);
}
.gf-btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ═══ DARK MODE ══════════════════════════════════════════════════════════════ */
[data-bs-theme="dark"] .gf-wrapper,
[data-bs-theme="dark"] .gf-footer {
    background: #1e1e2d;
}
[data-bs-theme="dark"] .gf-body {
    background: #1e1e2d;
}
[data-bs-theme="dark"] .gf-label {
    color: #a1a5b7;
}
[data-bs-theme="dark"] .gf-input {
    background: #1b1b29;
    border-color: #2b2b40;
    color: #e1e1e1;
}
[data-bs-theme="dark"] .gf-input:focus {
    background: #22223a;
    border-color: #667eea;
}
[data-bs-theme="dark"] .gf-search-input {
    background: #1b1b29;
    border-color: #2b2b40;
    color: #e1e1e1;
}
[data-bs-theme="dark"] .gf-search-input:focus {
    background: #22223a;
    border-color: #667eea;
}
[data-bs-theme="dark"] .gf-member-list {
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .gf-member-row {
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .gf-member-row:hover {
    background: rgba(102, 126, 234, 0.1);
}
[data-bs-theme="dark"] .gf-member-row.selected {
    background: rgba(102, 126, 234, 0.15);
}
[data-bs-theme="dark"] .gf-member-name {
    color: #e1e1e1;
}
[data-bs-theme="dark"] .gf-check-box {
    background: #2b2b40;
    border-color: #3d3d5c;
}
[data-bs-theme="dark"] .gf-btn-cancel {
    border-color: #2b2b40;
    color: #a1a5b7;
}
[data-bs-theme="dark"] .gf-btn-cancel:hover {
    background: #2b2b40;
}
[data-bs-theme="dark"] .gf-footer {
    border-color: #2b2b40;
}
</style>
