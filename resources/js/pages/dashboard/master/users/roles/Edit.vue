<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import axios from "@/libs/axios";   
import { toast } from "vue3-toastify";

const props = defineProps({
    contactId: { type: [String, Number], default: null },
    title: { type: String, default: 'Edit Kontak' }
    });

const emit = defineEmits(['close', 'refresh']);

const isLoading = ref(false);
const isFetching = ref(false);

const form = ref({
    name: "",
    phone: "",
});

const isEditMode = computed(() => !!props.contactId);

// Judul Modal
const modalTitle = computed(() => isEditMode.value ? "Edit Kontak" : "Tambah Kontak Baru");

// Fetch Data jika Edit Mode
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
        emit('close');
    } finally {
        isFetching.value = false;
    }
};

// Handle Submit
const submit = async () => {
    if (!form.value.name) {
        toast.error("Nama wajib diisi.");
        return;
    }

    isLoading.value = true;
    try {
        if (isEditMode.value) {
            await axios.put(`/chat/contacts/${props.contactId}`, {
                name: form.value.name
            });
            const msg = props.title.includes('Simpan') 
                ? "Kontak berhasil disimpan." 
                : "Kontak berhasil diperbarui.";
            toast.success(msg);

        } else {
            await axios.post("/chat/contacts", {
                phone: form.value.phone,
                name: form.value.name
            });
            toast.success("Kontak berhasil ditambahkan.");
        }

        emit('refresh');
        emit('close');   
    } catch (error: any) {
        console.error("Error submit:", error);
        if (error.response?.data?.message) {
            toast.error(error.response.data.message);
        } else {
            toast.error("Terjadi kesalahan sistem.");
        }
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    if (isEditMode.value) {
        fetchContactData();
    }
});
</script>

<template>
    <div class="d-flex flex-column h-100 bg-body">
        
        <div class="modal-header d-flex align-items-center justify-content-between p-4 border-bottom">
            <h3 class="fw-bold m-0 text-gray-800">{{ modalTitle }}</h3>
            <button @click="$emit('close')" class="btn btn-icon btn-sm btn-active-light-primary ms-2">
                <i class="fas fa-times fs-2"></i>
            </button>
        </div>

        <div class="modal-body p-5">
            
            <div v-if="isFetching" class="d-flex justify-content-center align-items-center py-10">
                <span class="spinner-border text-primary"></span>
            </div>

            <form v-else @submit.prevent="submit" id="contactForm">
                
                <div class="mb-5">
                    <label class="required form-label fw-bold">Nomor Telepon</label>
                    <input v-model="form.phone" 
                           type="text" 
                           class="form-control form-control-solid" 
                           placeholder="Contoh: 08123456789"
                           :disabled="isEditMode" 
                           :class="{'bg-secondary': isEditMode}"/>
                    <div v-if="isEditMode" class="form-text text-muted">
                        Nomor telepon tidak dapat diubah untuk menjaga riwayat chat.
                    </div>
                    <div v-else class="form-text text-muted">
                        Pastikan user tersebut sudah terdaftar di aplikasi.
                    </div>
                </div>

                <div class="mb-5">
                    <label class="required form-label fw-bold">Nama Kontak</label>
                    <input v-model="form.name" 
                           type="text" 
                           class="form-control form-control-solid" 
                           placeholder="Nama teman Anda..." />
                </div>

            </form>
        </div>

        <div class="modal-footer p-4 border-top d-flex justify-content-end bg-light-subtle">
            <button type="button" class="btn btn-light me-3" @click="$emit('close')">
                Batal
            </button>
            <button type="submit" form="contactForm" class="btn btn-primary" :disabled="isLoading || isFetching">
                <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                {{ isEditMode ? 'Simpan Perubahan' : 'Tambah Kontak' }}
            </button>
        </div>

    </div>
</template>

<style scoped>
.bg-body {
    background-color: #ffffff;
}
.bg-light-subtle {
    background-color: #f9f9f9;
}

/* Dark Mode Overrides */
[data-bs-theme="dark"] .bg-body {
    background-color: #1e1e2d !important;
}

[data-bs-theme="dark"] .modal-header,
[data-bs-theme="dark"] .modal-footer {
    border-color: #2b2b40 !important;
    background-color: #1e1e2d !important;
}

[data-bs-theme="dark"] .text-gray-800 {
    color: #ffffff !important;
}

[data-bs-theme="dark"] .form-control-solid {
    background-color: #1b1b29 !important;
    border-color: #2b2b40 !important;
    color: #ffffff !important;
}
[data-bs-theme="dark"] .form-control-solid:focus {
    background-color: #1b1b29 !important;
    border-color: #474761 !important;
}

/* Readonly Input di Dark Mode */
[data-bs-theme="dark"] .bg-secondary {
    background-color: #323248 !important; 
    color: #92929f !important;
}

[data-bs-theme="dark"] .btn-active-light-primary:hover {
    background-color: #2b2b40 !important;
    color: #3699ff !important;
}
</style>