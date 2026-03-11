<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";

const props = defineProps({
    contactId: { type: [String, Number], default: null },
    title: { type: String, default: "Edit Kontak" },
});

const emit = defineEmits(["close", "refresh"]);

const isLoading = ref(false);
const isFetching = ref(false);

const form = ref({
    name: "",
    phone: "",
});

const isEditMode = computed(() => !!props.contactId);
const modalTitle = computed(() =>
    isEditMode.value ? "Edit Kontak" : "Tambah Kontak Baru"
);

const fetchContactData = async () => {
    if (!props.contactId) return;
    isFetching.value = true;
    try {
        const response = await axios.get(`/chat/contacts/${props.contactId}`);
        const data = response.data.data || response.data;
        form.value.name = data.alias || data.name;
        form.value.phone = data.phone || data.email;
    } catch (error) {
        console.error(error);
        toast.error("Gagal memuat data kontak.");
        emit("close");
    } finally {
        isFetching.value = false;
    }
};

const submit = async () => {
    if (!form.value.name) {
        toast.error("Nama wajib diisi.");
        return;
    }
    isLoading.value = true;
    try {
        if (isEditMode.value) {
            await axios.put(`/chat/contacts/${props.contactId}`, {
                name: form.value.name,
            });
            toast.success(
                props.title.includes("Simpan")
                    ? "Kontak berhasil disimpan."
                    : "Kontak berhasil diperbarui."
            );
        } else {
            await axios.post("/chat/contacts", {
                phone: form.value.phone,
                name: form.value.name,
            });
            toast.success("Kontak berhasil ditambahkan.");
        }
        emit("refresh");
        emit("close");
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || "Terjadi kesalahan sistem."
        );
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    if (isEditMode.value) fetchContactData();
});
</script>

<template>
    <div class="d-flex flex-column h-100 edit-panel">
        <!-- Header dengan gradient tema -->
        <div
            class="edit-header d-flex align-items-center justify-content-between p-4"
        >
            <div class="d-flex align-items-center gap-3">
                <div class="edit-icon-wrap">
                    <i
                        class="fas"
                        :class="isEditMode ? 'fa-user-edit' : 'fa-user-plus'"
                    ></i>
                </div>
                <h3 class="fw-bold m-0 text-white fs-5">{{ modalTitle }}</h3>
            </div>
            <button @click="$emit('close')" class="btn-close-custom">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="edit-body p-5 flex-grow-1">
            <div
                v-if="isFetching"
                class="d-flex justify-content-center align-items-center py-10"
            >
                <div class="spinner-themed"></div>
            </div>

            <form
                v-else
                @submit.prevent="submit"
                id="contactForm"
                class="edit-form"
            >
                <div class="field-group mb-4">
                    <label class="field-label">
                        <i class="fas fa-phone-alt me-2 text-primary-theme"></i>
                        Nomor Telepon
                        <span class="required-star">*</span>
                    </label>
                    <input
                        v-model="form.phone"
                        type="text"
                        class="field-input"
                        placeholder="Contoh: 08123456789"
                        :disabled="isEditMode"
                        :class="{ 'field-input--disabled': isEditMode }"
                    />
                    <p class="field-hint">
                        <i class="fas fa-info-circle me-1"></i>
                        {{
                            isEditMode
                                ? "Nomor tidak dapat diubah untuk menjaga riwayat chat."
                                : "Pastikan user tersebut sudah terdaftar di aplikasi."
                        }}
                    </p>
                </div>

                <div class="field-group mb-4">
                    <label class="field-label">
                        <i class="fas fa-user me-2 text-primary-theme"></i>
                        Nama Kontak
                        <span class="required-star">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="field-input"
                        placeholder="Nama teman Anda..."
                    />
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="edit-footer p-4 d-flex justify-content-end gap-2">
            <button type="button" class="btn-cancel" @click="$emit('close')">
                Batal
            </button>
            <button
                type="submit"
                form="contactForm"
                class="btn-save"
                :disabled="isLoading || isFetching"
            >
                <span
                    v-if="isLoading"
                    class="spinner-border spinner-border-sm me-2"
                ></span>
                <i
                    v-else
                    class="fas me-2"
                    :class="isEditMode ? 'fa-save' : 'fa-user-plus'"
                ></i>
                {{ isEditMode ? "Simpan Perubahan" : "Tambah Kontak" }}
            </button>
        </div>
    </div>
</template>

<style scoped>
/* ── Panel ───────────────────────────────────────────────────────────────── */
.edit-panel {
    background: #ffffff;
    animation: slideInPanel 0.3s cubic-bezier(0.34, 1.2, 0.64, 1) both;
}

@keyframes slideInPanel {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* ── Header ──────────────────────────────────────────────────────────────── */
.edit-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 68px;
    box-shadow: 0 3px 12px rgba(102, 126, 234, 0.3);
}
.edit-icon-wrap {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.btn-close-custom {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.18);
    border: none;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.15s ease;
}
.btn-close-custom:hover {
    background: rgba(255, 255, 255, 0.32);
    transform: rotate(90deg);
}

/* ── Body & Form ─────────────────────────────────────────────────────────── */
.edit-body {
    overflow-y: auto;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: #3f4254;
    letter-spacing: 0.3px;
}
.text-primary-theme {
    color: #667eea;
}
.required-star {
    color: #ef4444;
    margin-left: 3px;
}

.field-input {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #e8ecf0;
    border-radius: 10px;
    background: #f8fafc;
    font-size: 0.88rem;
    color: #1a202c;
    transition: border-color 0.2s ease, box-shadow 0.2s ease,
        background 0.2s ease;
    outline: none;
}
.field-input:focus {
    border-color: #667eea;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
}
.field-input--disabled {
    background: #f1f3f7 !important;
    color: #9ca3af;
    cursor: not-allowed;
}
.field-hint {
    font-size: 0.75rem;
    color: #94a3b8;
    margin: 0;
}

/* ── Spinner ─────────────────────────────────────────────────────────────── */
.spinner-themed {
    width: 32px;
    height: 32px;
    border: 3px solid rgba(102, 126, 234, 0.2);
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* ── Footer ──────────────────────────────────────────────────────────────── */
.edit-footer {
    border-top: 1px solid #f1f3f7;
    background: #fafbfc;
}
.btn-cancel {
    padding: 8px 18px;
    border: 1.5px solid #e2e8f0;
    border-radius: 9px;
    background: transparent;
    color: #64748b;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease;
}
.btn-cancel:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.btn-save {
    padding: 8px 20px;
    border: none;
    border-radius: 9px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
    box-shadow: 0 3px 10px rgba(102, 126, 234, 0.35);
    display: flex;
    align-items: center;
}
.btn-save:hover:not(:disabled) {
    opacity: 0.9;
    transform: translateY(-1px);
    box-shadow: 0 5px 14px rgba(102, 126, 234, 0.45);
}
.btn-save:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ── Dark Mode ───────────────────────────────────────────────────────────── */
[data-bs-theme="dark"] .edit-panel {
    background: #1e1e2d;
}
[data-bs-theme="dark"] .field-label {
    color: #c4c4d4;
}
[data-bs-theme="dark"] .field-input {
    background: #1b1b29;
    border-color: #2b2b40;
    color: #e1e1e1;
}
[data-bs-theme="dark"] .field-input:focus {
    background: #22223a;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.18);
}
[data-bs-theme="dark"] .field-input--disabled {
    background: #2b2b40 !important;
    color: #6b7280;
}
[data-bs-theme="dark"] .field-hint {
    color: #6b7280;
}
[data-bs-theme="dark"] .edit-footer {
    background: #1b1b29;
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .btn-cancel {
    border-color: #2b2b40;
    color: #9ca3af;
}
[data-bs-theme="dark"] .btn-cancel:hover {
    background: #2b2b40;
}
</style>
