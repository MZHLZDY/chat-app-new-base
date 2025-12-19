<script setup lang="ts">
import { ref, onMounted, computed } from "vue";
import axios from "@/libs/axios";   
import { toast } from "vue3-toastify";

// Mengganti contactId menjadi groupId
const props = defineProps({
    groupId: { type: [String, Number], default: null },
    title: { type: String, default: 'Edit Grup' }
});

const emit = defineEmits(['close', 'refresh', 'updated']);

const isLoading = ref(false);
const isFetching = ref(false);

const form = ref({
    name: "",
    // Phone dihapus karena grup tidak membutuhkan nomor telepon
});

const isEditMode = computed(() => !!props.groupId);

// Judul Modal
const modalTitle = computed(() => isEditMode.value ? "Edit Info Grup" : "Buat Grup Baru");

// Fetch Data jika Edit Mode
const fetchGroupData = async () => {
    if (!props.groupId) return;
    
    isFetching.value = true;
    try {
        // Mengambil data grup berdasarkan ID
        const response = await axios.get(`/chat/groups/${props.groupId}`);
        const data = response.data.data || response.data;
        
        form.value.name = data.name;
    } catch (error) {
        console.error(error);
        toast.error("Gagal memuat data grup.");
        emit('close');
    } finally {
        isFetching.value = false;
    }
};

// Handle Submit
const submit = async () => {
    if (!form.value.name) {
        toast.error("Nama grup wajib diisi.");
        return;
    }

    isLoading.value = true;
    try {
        if (isEditMode.value) {
            // Update Grup
            await axios.put(`/chat/groups/${props.groupId}`, {
                name: form.value.name
            });
            
            toast.success("Info grup berhasil diperbarui.");
            emit('updated'); // Memberitahu parent component bahwa data telah berubah
        } else {
            // Buat Grup Baru
            await axios.post("/chat/groups", {
                name: form.value.name
            });
            toast.success("Grup berhasil dibuat.");
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
        fetchGroupData();
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

            <form v-else @submit.prevent="submit" id="groupForm">
                
                <div class="mb-5">
                    <label class="required form-label fw-bold">Nama Grup</label>
                    <input v-model="form.name" 
                           type="text" 
                           class="form-control form-control-solid" 
                           placeholder="Masukkan nama grup..." />
                </div>

            </form>
        </div>

        <div class="modal-footer p-4 border-top d-flex justify-content-end bg-light-subtle">
            <button type="button" class="btn btn-light me-3" @click="$emit('close')">
                Batal
            </button>
            <button type="submit" form="groupForm" class="btn btn-primary" :disabled="isLoading || isFetching">
                <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                {{ isEditMode ? 'Simpan Perubahan' : 'Buat Grup' }}
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

/* Dark Mode Overrides - Sama persis dengan Edit.vue */
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

[data-bs-theme="dark"] .btn-active-light-primary:hover {
    background-color: #2b2b40 !important;
    color: #3699ff !important;
}
</style>