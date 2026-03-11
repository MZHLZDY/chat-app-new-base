<template>
    <VForm
        @submit="submit"
        :validation-schema="formSchema"
        id="form-contact"
        class="form-panel"
    >
        <!-- Header gradient -->
        <div
            class="form-header d-flex align-items-center justify-content-between p-4"
        >
            <div class="d-flex align-items-center gap-3">
                <div class="form-icon-wrap">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3 class="fw-bold m-0 text-white fs-5">Tambah Kontak Baru</h3>
            </div>
            <button
                type="button"
                class="btn-close-custom"
                @click="emit('close')"
                :disabled="isLoading"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="form-body p-5">
            <!-- Info hint -->
            <div class="form-hint-box mb-4">
                <i class="fas fa-info-circle me-2"></i>
                Pastikan nomor HP teman Anda sudah terdaftar di sistem.
            </div>

            <div class="field-group mb-4">
                <label class="field-label">
                    <i class="fas fa-user me-2 text-primary-theme"></i>
                    Nama Kontak <span class="required-star">*</span>
                </label>
                <Field
                    name="name"
                    v-model="contact.name"
                    class="field-input"
                    placeholder="Misal: Teman Kuliah"
                />
                <div class="field-error"><ErrorMessage name="name" /></div>
            </div>

            <div class="field-group mb-4">
                <label class="field-label">
                    <i class="fas fa-phone-alt me-2 text-primary-theme"></i>
                    Nomor Telepon <span class="required-star">*</span>
                </label>
                <Field
                    name="phone"
                    type="text"
                    v-model="contact.phone"
                    class="field-input"
                    placeholder="08xxxxxxxxxx"
                />
                <div class="field-error"><ErrorMessage name="phone" /></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="form-footer p-4 d-flex justify-content-end gap-2">
            <button
                type="button"
                class="btn-cancel"
                @click="emit('close')"
                :disabled="isLoading"
            >
                Batal
            </button>
            <button type="submit" class="btn-save" :disabled="isLoading">
                <span
                    v-if="isLoading"
                    class="spinner-border spinner-border-sm me-2"
                ></span>
                <i v-else class="fas fa-search me-2"></i>
                Cari & Simpan
            </button>
        </div>
    </VForm>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { Form as VForm, Field, ErrorMessage } from "vee-validate";
import * as Yup from "yup";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";

const emit = defineEmits(["close", "refresh"]);
const isLoading = ref(false);
const contact = ref({ name: "", phone: "" });

const formSchema = Yup.object().shape({
    name: Yup.string().required("Nama alias wajib diisi"),
    phone: Yup.string().required("Nomor HP wajib diisi"),
});

const submit = async () => {
    isLoading.value = true;
    try {
        await axios.post("/chat/add-contact", {
            name: contact.value.name,
            phone: contact.value.phone,
        });
        toast.success("Kontak berhasil disimpan!");
        emit("refresh");
        emit("close");
    } catch (err: any) {
        if (err.response?.status === 404) {
            toast.error(
                "Gagal: Nomor HP tersebut belum terdaftar di aplikasi."
            );
        } else if (err.response?.status === 422) {
            toast.error(err.response.data.message);
        } else {
            toast.error("Terjadi kesalahan sistem.");
        }
    } finally {
        isLoading.value = false;
    }
};
</script>

<style scoped>
/* ── Panel ───────────────────────────────────────────────────────────────── */
.form-panel {
    background: #ffffff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.15);
    animation: popIn 0.3s cubic-bezier(0.34, 1.2, 0.64, 1) both;
}
@keyframes popIn {
    from {
        opacity: 0;
        transform: translateY(-12px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* ── Header ──────────────────────────────────────────────────────────────── */
.form-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 68px;
}
.form-icon-wrap {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.85rem;
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
.btn-close-custom:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.32);
    transform: rotate(90deg);
}

/* ── Hint ────────────────────────────────────────────────────────────────── */
.form-hint-box {
    background: rgba(102, 126, 234, 0.08);
    border: 1px solid rgba(102, 126, 234, 0.2);
    border-radius: 9px;
    padding: 10px 14px;
    font-size: 0.8rem;
    color: #667eea;
    font-weight: 500;
}

/* ── Fields ──────────────────────────────────────────────────────────────── */
.field-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.field-label {
    font-size: 0.8rem;
    font-weight: 700;
    color: #3f4254;
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
.field-error {
    font-size: 0.75rem;
    color: #ef4444;
    min-height: 16px;
}

/* ── Footer ──────────────────────────────────────────────────────────────── */
.form-footer {
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
.btn-cancel:hover:not(:disabled) {
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
    display: flex;
    align-items: center;
    transition: opacity 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
    box-shadow: 0 3px 10px rgba(102, 126, 234, 0.35);
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
[data-bs-theme="dark"] .form-panel {
    background: #1e1e2d;
}
[data-bs-theme="dark"] .field-label {
    color: #c4c4d4;
}
[data-bs-theme="dark"] .form-hint-box {
    background: rgba(102, 126, 234, 0.15);
    border-color: rgba(102, 126, 234, 0.3);
    color: #a5b4fc;
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
[data-bs-theme="dark"] .form-footer {
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
