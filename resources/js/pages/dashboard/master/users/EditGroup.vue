<script setup lang="ts">
import { ref, computed, watch } from "vue";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";

const props = defineProps({
    groupId: { type: [String, Number], default: null },
    modelValue: { type: Boolean, default: false } // Untuk v-model open/close modal
});

const emit = defineEmits(['update:modelValue', 'close', 'refresh', 'group-updated']);

// State
const activeTab = ref<'info' | 'members'>('info');
const isLoading = ref(false);
const isFetching = ref(false);

// Data Grup
const form = ref({
    name: "",
    description: ""
});
const members = ref<any[]>([]);

// State untuk Tambah Member
const searchQuery = ref("");
const searchResults = ref<any[]>([]);
const selectedUsersToAdd = ref<number[]>([]);
const isSearching = ref(false);

// Judul Modal
const modalTitle = computed(() => props.groupId ? "Kelola Grup" : "Buat Grup Baru");

// --- LOGIC FETCH DATA ---
const fetchGroupData = async () => {
    if (!props.groupId) return;
    
    isFetching.value = true;
    try {
        // Panggil endpoint SHOW yang baru kita buat di controller
        const response = await axios.get(`/chat/groups/${props.groupId}`);
        const data = response.data.data;
        
        form.value.name = data.name;
        // Pastikan backend mengirim relasi 'members'
        members.value = data.members || [];
    } catch (error) {
        console.error(error);
        toast.error("Gagal memuat data grup.");
        emit('close');
    } finally {
        isFetching.value = false;
    }
};

// --- LOGIC TAB 1: EDIT INFO ---
const submitInfo = async () => {
    isLoading.value = true;
    try {
        if (props.groupId) {
            const response = await axios.put(`/chat/groups/${props.groupId}`, {
                name: form.value.name
            });
            
            toast.success("Info grup diperbarui");
            emit('group-updated', response.data.data);
            emit('close'); 
        } else {
            // Create New
            await axios.post(`/chat/groups`, { name: form.value.name });
            toast.success("Grup dibuat");
            emit('refresh'); 
            emit('close');
        }
    } catch (error) {
        toast.error("Gagal menyimpan.");
        console.error(error);
    } finally {
        isLoading.value = false;
    }
};

// --- LOGIC TAB 2: MEMBERS ---

// A. Cari User Baru
const searchUsers = async () => {
    if (searchQuery.value.length < 2) return;
    isSearching.value = true;
    try {
        // Panggil endpoint search user, exclude user yang sudah ada di grup
        const response = await axios.get(`/chat/users/search`, {
            params: { 
                q: searchQuery.value,
                exclude_group: props.groupId 
            }
        });
        searchResults.value = response.data.data;
    } catch (error) {
        console.error(error);
    } finally {
        isSearching.value = false;
    }
};

// B. Pilih User untuk Ditambah
const toggleUserSelection = (userId: number) => {
    if (selectedUsersToAdd.value.includes(userId)) {
        selectedUsersToAdd.value = selectedUsersToAdd.value.filter(id => id !== userId);
    } else {
        selectedUsersToAdd.value.push(userId);
    }
};

// C. Eksekusi Tambah Member
const addSelectedMembers = async () => {
    if (selectedUsersToAdd.value.length === 0) return;
    
    isLoading.value = true;
    try {
        await axios.post(`/chat/groups/${props.groupId}/members`, {
            user_ids: selectedUsersToAdd.value
        });
        toast.success("Anggota berhasil ditambahkan!");
        
        // Reset form tambah
        searchQuery.value = "";
        searchResults.value = [];
        selectedUsersToAdd.value = [];
        
        // Refresh data member
        await fetchGroupData();
    } catch (error) {
        toast.error("Gagal menambahkan anggota.");
    } finally {
        isLoading.value = false;
    }
};

// D. Kick Member
const kickMember = async (userId: number, memberName: string) => {
    if (!confirm(`Yakin ingin mengeluarkan ${memberName} dari grup?`)) return;

    try {
        await axios.delete(`/chat/groups/${props.groupId}/members/${userId}`);
        
        // Hapus dari state lokal biar responsif
        members.value = members.value.filter(m => m.id !== userId);
        toast.success(`${memberName} dikeluarkan.`);
        emit('refresh'); // Refresh parent
    } catch (error) {
        toast.error("Gagal mengeluarkan anggota.");
    }
};

// Watcher: Saat modal dibuka (groupId berubah), load data
watch(() => props.groupId, (newVal) => {
    if (newVal) {
        activeTab.value = 'info'; // Reset ke tab info
        fetchGroupData();
    } else {
        // Reset form jika modal ditutup/buat baru
        form.value.name = "";
        members.value = [];
    }
}, { immediate: true });

</script>

<template>
    <div class="modal-overlay"> <div class="modal-content bg-body rounded shadow-lg w-100 mw-600px overflow-hidden">
            
            <div class="modal-header d-flex justify-content-between align-items-center p-4 border-bottom">
                <h2 class="fw-bold m-0">{{ modalTitle }}</h2>
                <div class="btn btn-sm btn-icon btn-active-color-primary" @click="$emit('close')">
                    <i class="fas fa-times fs-1"></i>
                </div>
            </div>

            <div v-if="props.groupId" class="d-flex border-bottom px-4">
                <button 
                    class="btn btn-active-light btn-color-gray-600 btn-active-color-primary px-4 py-3 rounded-0 fw-bold me-3"
                    :class="{ 'border-bottom border-3 border-primary text-primary': activeTab === 'info' }"
                    @click="activeTab = 'info'"
                >
                    Info Grup
                </button>
                <button 
                    class="btn btn-active-light btn-color-gray-600 btn-active-color-primary px-4 py-3 rounded-0 fw-bold"
                    :class="{ 'border-bottom border-3 border-primary text-primary': activeTab === 'members' }"
                    @click="activeTab = 'members'"
                >
                    Anggota ({{ members.length }})
                </button>
            </div>

            <div class="modal-body p-5 scroll-y" style="max-height: 60vh; overflow-y: auto;">
                
                <div v-if="isFetching" class="d-flex justify-content-center py-10">
                    <span class="spinner-border text-primary"></span>
                </div>

                <div v-else>
                    
                    <div v-if="activeTab === 'info'">
                        <form @submit.prevent="submitInfo" id="groupForm">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Nama Grup</label>
                                <input 
                                    v-model="form.name" 
                                    type="text" 
                                    class="form-control form-control-solid" 
                                    placeholder="Contoh: Tim IT" 
                                    required
                                />
                            </div>
                        </form>
                    </div>

                    <div v-if="activeTab === 'members'">
                        
                        <div class="mb-5 bg-light-primary p-4 rounded border border-dashed border-primary">
                            <label class="form-label fw-bold mb-2">Tambah Anggota Baru</label>
                            
                            <div class="input-group mb-2">
                                <input 
                                    v-model="searchQuery" 
                                    @input="searchUsers"
                                    type="text" 
                                    class="form-control" 
                                    placeholder="Cari nama atau email..."
                                />
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>

                            <div v-if="searchResults.length > 0" class="list-group mb-3 shadow-sm">
                                <div 
                                    v-for="user in searchResults" 
                                    :key="user.id"
                                    class="list-group-item list-group-item-action d-flex align-items-center cursor-pointer"
                                    @click="toggleUserSelection(user.id)"
                                >
                                    <div class="form-check me-3">
                                        <input class="form-check-input" type="checkbox" :checked="selectedUsersToAdd.includes(user.id)" readonly>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-35px symbol-circle me-2">
                                            <img :src="user.avatar || '/media/avatars/blank.png'" alt="pic">
                                        </div>
                                        <div class="fw-bold">{{ user.name }} <span class="text-muted fw-normal fs-7">({{ user.email }})</span></div>
                                    </div>
                                </div>
                            </div>

                            <button 
                                v-if="selectedUsersToAdd.length > 0" 
                                @click="addSelectedMembers" 
                                class="btn btn-sm btn-primary w-100" 
                                :disabled="isLoading"
                            >
                                <span v-if="isLoading" class="spinner-border spinner-border-sm me-1"></span>
                                Tambahkan {{ selectedUsersToAdd.length }} Orang Terpilih
                            </button>
                        </div>

                        <hr class="text-muted my-4">

                        <h5 class="fw-bold mb-3">Daftar Anggota ({{ members.length }})</h5>
                        
                        <div v-for="member in members" :key="member.id" class="d-flex align-items-center justify-content-between mb-3 p-2 rounded hover-bg-light">
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-40px symbol-circle me-3">
                                    <img :src="member.avatar || member.photo || '/media/avatars/blank.png'" alt="member">
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="text-gray-800 fw-bold">{{ member.name }}</span>
                                    <span class="text-muted fs-7">{{ member.email }}</span>
                                </div>
                                <span v-if="member.id === member.pivot?.is_admin" class="badge badge-light-success ms-2">Admin</span>
                            </div>

                            <button 
                                @click="kickMember(member.id, member.name)" 
                                class="btn btn-icon btn-sm btn-light-danger btn-active-danger"
                                title="Keluarkan dari grup"
                            >
                                <i class="fas fa-user-times"></i>
                            </button>
                        </div>

                        <div v-if="members.length === 0" class="text-center text-muted fst-italic py-4">
                            Belum ada anggota lain.
                        </div>

                    </div>
                </div>
            </div>

            <div class="modal-footer p-4 border-top bg-light-subtle d-flex justify-content-end">
                <button type="button" class="btn btn-light me-3" @click="$emit('close')">Tutup</button>
                
                <button 
                    v-if="activeTab === 'info'"
                    type="submit" 
                    form="groupForm" 
                    class="btn btn-primary" 
                    :disabled="isLoading || isFetching"
                >
                    <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
                    Simpan Perubahan
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
/* Styling Tambahan */
.mw-600px { max-width: 600px; }
.hover-bg-light:hover { background-color: #f8f9fa; }
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background-color: rgba(0, 0, 0, 0.5); z-index: 1050;
    display: flex; justify-content: center; align-items: center;
    backdrop-filter: blur(2px);
}
.modal-content { position: relative; display: flex; flex-direction: column; width: 100%; pointer-events: auto; background-color: #fff; background-clip: padding-box; border: 1px solid rgba(0, 0, 0, 0.2); border-radius: 0.475rem; outline: 0; }

/* Dark Mode Support */
[data-bs-theme="dark"] .bg-body { background-color: #1e1e2d !important; }
[data-bs-theme="dark"] .modal-header, 
[data-bs-theme="dark"] .modal-footer, 
[data-bs-theme="dark"] .border-bottom { border-color: #2b2b40 !important; }
[data-bs-theme="dark"] .text-gray-800 { color: #ffffff !important; }
[data-bs-theme="dark"] .bg-light-subtle { background-color: #151521 !important; }
[data-bs-theme="dark"] .hover-bg-light:hover { background-color: #2b2b40; }
</style>