<script setup lang="ts">
import { ref, watch, nextTick } from "vue";
import { X, Sparkles, PenLine } from "lucide-vue-next";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";

// --- PROPS & EMITS ---
const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits(["close", "refresh"]);

// --- STATE ---
const title = ref("");
const description = ref("");
const isLoading = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);
const errorMessage = ref("");

// --- WATCHERS ---
watch(
    () => props.show,
    (newVal) => {
        if (newVal) {
            errorMessage.value = "";
            nextTick(() => {
                setTimeout(() => {
                    inputRef.value?.focus();
                }, 150);
            });
        }
    }
);

// --- ACTIONS ---
const submit = async () => {
    if (!title.value.trim()) {
        const input = inputRef.value;
        input?.parentElement?.classList.add("shake-animation");
        setTimeout(
            () => input?.parentElement?.classList.remove("shake-animation"),
            500
        );
        errorMessage.value = "Judul tugas tidak boleh kosong.";
        return;
    }

    isLoading.value = true;
    errorMessage.value = "";

    try {
        // 2. Kirim Data
        await axios.post("/chat/todos", {
            title: title.value,
            description: description.value,
        });

        toast.success("Tugas berhasil disimpan!");
        title.value = "";
        description.value = "";
        emit("refresh");
        emit("close");
    } catch (err: any) {
        console.error(err);
        if (err.response?.status === 422) {
            errorMessage.value =
                err.response.data.errors?.title?.[0] || "Data tidak valid";
            toast.error(errorMessage.value);
        } else {
            toast.error("Gagal menyimpan tugas. Cek koneksi internet.");
        }
    } finally {
        isLoading.value = false;
    }
};

const handleClose = () => {
    title.value = "";
    errorMessage.value = "";
    emit("close");
};
</script>

<template>
    <Teleport to="body">
        <div class="modal-wrapper">
            <Transition name="backdrop">
                <div
                    v-if="show"
                    class="modal-backdrop-custom"
                    @click="handleClose"
                ></div>
            </Transition>

            <Transition name="modal-spring">
                <div v-if="show" class="modal-container">
                    <div class="custom-modal shadow-lg">
                        <div class="modal-header-custom">
                            <div class="d-flex align-items-center gap-3">
                                <div
                                    class="icon-circle bg-light-primary text-primary"
                                >
                                    <Sparkles class="w-5 h-5" />
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-adaptive fs-4">
                                        Tugas Baru
                                    </h5>
                                    <span class="text-gray-400 fs-7 fw-semibold"
                                        >Mau kerjain apa hari ini?</span
                                    >
                                </div>
                            </div>
                            <button
                                class="btn-close-custom"
                                @click="handleClose"
                            >
                                <X class="w-5 h-5" />
                            </button>
                        </div>

                        <form @submit.prevent="submit">
                            <div class="modal-body-custom">
                                <div
                                    class="input-group-custom"
                                    :class="{ 'has-error': errorMessage }"
                                >
                                    <label class="input-label">
                                        <PenLine
                                            class="w-4 h-4 me-2 text-gray-400"
                                        />
                                        Judul Tugas
                                    </label>
                                    <input
                                        ref="inputRef"
                                        v-model="title"
                                        type="text"
                                        class="form-control-custom"
                                        placeholder="Misal: Meeting jam 10..."
                                        :disabled="isLoading"
                                    />
                                </div>
                                <div v-if="errorMessage" class="error-text">
                                    {{ errorMessage }}
                                </div>
                                <div class="input-group-custom">
                                    <label class="input-label">
                                        <AlignLeft class="w-4 h-4 me-2 text-gray-400" /> Deskripsi (Opsional)
                                    </label>
                                    <textarea 
                                        v-model="description" 
                                        rows="3" 
                                        class="form-control-custom" 
                                        placeholder="Tambahkan detail catatan..."
                                        style="resize: none; min-height: 80px;"
                                        :disabled="isLoading"
                                    ></textarea>
                                </div>
                            </div>

                            <div class="modal-footer-custom">
                                <button
                                    type="button"
                                    class="btn btn-light btn-hover-scale me-3"
                                    @click="handleClose"
                                    :disabled="isLoading"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    class="btn btn-primary btn-hover-scale shadow-sm"
                                    :disabled="isLoading"
                                >
                                    <span
                                        v-if="isLoading"
                                        class="spinner-border spinner-border-sm me-2"
                                    ></span>
                                    <span v-else>Simpan Sekarang</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<style scoped>
/* --- IMPORTANT: TOAST Z-INDEX FIX --- */
:global(.Toastify) {
    z-index: 999999 !important;
}
:global(.Toastify__toast-container) {
    z-index: 999999 !important;
}

/* --- LAYOUT WRAPPER --- */
.modal-wrapper {
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

.modal-container {
    z-index: 9995;
    width: 100%;
    max-width: 500px;
    padding: 20px;
    pointer-events: auto;
}

/* --- BACKDROP --- */
.modal-backdrop-custom {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    pointer-events: auto;
    z-index: 9991;
}

/* --- MODAL DESIGN --- */
.custom-modal {
    background: #ffffff;
    border-radius: 24px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.modal-header-custom {
    padding: 24px 24px 10px 24px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.modal-body-custom {
    padding: 10px 24px 24px 24px;
}

.modal-footer-custom {
    padding: 0 24px 24px 24px;
    display: flex;
    justify-content: flex-end;
}

/* --- ELEMENTS --- */
.icon-circle {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f1faff;
}

.btn-close-custom {
    background: transparent;
    border: none;
    color: #a1a5b7;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.2s;
    cursor: pointer;
}
.btn-close-custom:hover {
    background-color: #f5f8fa;
    color: #f1416c;
    transform: rotate(90deg);
}

.input-group-custom {
    background-color: #f9f9f9;
    border: 2px solid transparent;
    border-radius: 16px;
    padding: 12px 16px;
    transition: all 0.3s ease;
}

.input-group-custom:focus-within {
    background-color: #ffffff;
    border-color: #009ef7;
    box-shadow: 0 10px 30px rgba(0, 158, 247, 0.1);
    transform: translateY(-2px);
}

.input-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #a1a5b7;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
}

textarea.form-control-custom {
    line-height: 1.5;
    margin-top: 5px;
}

.form-control-custom {
    width: 100%;
    border: none;
    background: transparent;
    font-size: 1.1rem;
    font-weight: 500;
    color: #181c32;
    outline: none;
    padding: 0;
}
.form-control-custom::placeholder {
    color: #d8d8e5;
}

.input-group-custom.has-error {
    border-color: #f1416c;
    background-color: #fff5f8;
}
.error-text {
    color: #f1416c;
    font-size: 0.85rem;
    margin-top: 8px;
    margin-left: 5px;
    animation: fadeIn 0.3s ease;
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
    transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-spring-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.modal-spring-enter-from {
    opacity: 0;
    transform: scale(0.8) translateY(30px);
}
.modal-spring-leave-to {
    opacity: 0;
    transform: scale(0.95) translateY(-20px);
}

.btn-hover-scale {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 12px;
    padding: 10px 24px;
    font-weight: 600;
}
.btn-hover-scale:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}
.btn-hover-scale:active {
    transform: translateY(0);
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
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* --- DARK MODE --- */
.text-adaptive {
    color: #181c32;
}
@media (prefers-color-scheme: dark) {
    .custom-modal {
        background-color: #1e1e2d;
        border-color: #2b2b40;
    }
    .text-adaptive {
        color: #ffffff !important;
    }
    .text-gray-400 {
        color: #565674 !important;
    }
    .input-group-custom {
        background-color: #151521;
    }
    .input-group-custom:focus-within {
        background-color: #1b1b29;
        border-color: #009ef7;
    }
    .input-group-custom.has-error {
        background-color: #2a121d;
        border-color: #f1416c;
    }
    .form-control-custom {
        color: #ffffff;
    }
    .icon-circle {
        background-color: #2b2b40;
    }
    .btn-light {
        background-color: #2b2b40;
        color: #cdcdde;
    }
    .btn-light:hover {
        background-color: #323248;
        color: #fff;
    }
    .btn-close-custom:hover {
        background-color: #2b2b40;
    }
}
:global(.dark) .custom-modal {
    background-color: #1e1e2d;
}
:global(.dark) .text-adaptive {
    color: #ffffff !important;
}
:global(.dark) .input-group-custom {
    background-color: #151521;
}
:global(.dark) .form-control-custom {
    color: #ffffff;
}
</style>
