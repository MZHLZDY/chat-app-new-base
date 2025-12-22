<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import * as Yup from "yup";
import { ErrorMessage, Field, Form as VeeForm } from "vee-validate";

// Props & Emits
const emit = defineEmits(["close", "refresh"]);

// State
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const users = ref<any[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);

// Form Data
const formData = ref({
    name: "",
    member_ids: [] as number[],
});

// Schema Validasi
const schema = Yup.object().shape({
    name: Yup.string().required("Nama grup wajib diisi").max(100, "Maksimal 100 karakter"),
    member_ids: Yup.array().min(1, "Pilih minimal 1 anggota grup"),
});

// 1. Fetch Users untuk dijadikan Anggota
const fetchUsers = async () => {
    isLoading.value = true;
    try {
        const { data } = await axios.get("/master/users"); 
        const allUsers = data.data ? data.data : data;
        users.value = allUsers.filter((u: any) => u.id !== currentUser.value.id);
    } catch (error) {
        console.error(error);
        toast.error("Gagal memuat daftar user");
    } finally {
        isLoading.value = false;
    }
};

// 2. Submit Form
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
    <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5)">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bold">Buat Grup Baru</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" @click="$emit('close')">
                        <span class="svg-icon svg-icon-1">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="currentColor"/>
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="currentColor"/>
                            </svg>
                        </span>
                    </div>
                </div>

                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <VeeForm :validation-schema="schema" @submit="submit">
                        
                        <div class="d-flex flex-column mb-8">
                            <label class="d-flex align-items-center fs-6 fw-bold mb-2">
                                <span class="required">Nama Grup</span>
                            </label>
                            <Field 
                                type="text" 
                                class="form-control form-control-solid" 
                                placeholder="Contoh: Tim IT, Alumni 2024" 
                                name="name" 
                                v-model="formData.name"
                            />
                            <div class="text-danger mt-1">
                                <ErrorMessage name="name" />
                            </div>
                        </div>

                        <div class="d-flex flex-column mb-8">
                            <label class="fs-6 fw-bold mb-2">
                                <span class="required">Pilih Anggota</span>
                            </label>
                            
                            <div class="border rounded p-4 scroll-y" style="max-height: 200px; overflow-y: auto;">
                                <div v-if="isLoading" class="text-center text-muted">Memuat user...</div>
                                
                                <div v-else v-for="user in users" :key="user.id" class="d-flex align-items-center mb-4">
                                    <label class="form-check form-check-custom form-check-solid me-5">
                                        <input 
                                            class="form-check-input" 
                                            type="checkbox" 
                                            :value="user.id" 
                                            v-model="formData.member_ids"
                                        />
                                    </label>
                                    <div class="d-flex align-items-center flex-grow-1">
                                        <div class="symbol symbol-35px symbol-circle me-3">
                                            <span class="symbol-label bg-light-primary text-primary fw-bold">
                                                {{ user.name.charAt(0).toUpperCase() }}
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-900 fw-bold">{{ user.name }}</span>
                                            <span class="text-gray-500 fs-7">{{ user.email }}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div v-if="!isLoading && users.length === 0" class="text-muted text-center">
                                    Tidak ada user lain ditemukan.
                                </div>
                            </div>
                            <div class="text-danger mt-1">
                                <ErrorMessage name="member_ids" />
                            </div>
                        </div>

                        <div class="text-center pt-15">
                            <button type="button" class="btn btn-light me-3" @click="$emit('close')">
                                Batal
                            </button>
                            <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                                <span v-if="!isSubmitting">Buat Grup</span>
                                <span v-else>Menyimpan...</span>
                            </button>
                        </div>

                    </VeeForm>
                </div>
            </div>
        </div>
    </div>
</template>