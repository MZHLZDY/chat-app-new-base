// Ini file index.vue untuk halaman group chat
<script setup lang="ts">
import { ref, onMounted, nextTick, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import axios from "@/libs/axios";
import { getAssetPath } from "@/core/helpers/assets";
import { toast } from "vue3-toastify";

// --- STATE MANAGEMENT ---
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const groups = ref<any[]>([]);
const messages = ref<any[]>([]);
const activeGroup = ref<any>(null);
const newMessage = ref("");
const chatBodyRef = ref<HTMLElement | null>(null);

// State Modal Create Group
const isCreateModalOpen = ref(false);
const newGroupName = ref("");
const availableUsers = ref<any[]>([]); // List teman untuk dimasukkan ke grup
const selectedUserIds = ref<number[]>([]); // ID teman yang dipilih

// --- API ACTIONS ---

// 1. Ambil Daftar Group
const fetchGroups = async () => {
    try {
        const { data } = await axios.get("/chat/groups");
        groups.value = data;
    } catch (err) {
        console.error(err);
    }
};

// 2. Ambil Daftar User (Untuk Modal Create Group)
const fetchUsersForModal = async () => {
    try {
        const { data } = await axios.get("/master/users");
        const allUsers = data.data ? data.data : data;
        // Filter diri sendiri
        availableUsers.value = allUsers.filter((u: any) => u.id !== currentUser.value.id);
    } catch (err) {
        console.error(err);
    }
};

// 3. Submit Buat Group Baru
const createGroup = async () => {
    if (!newGroupName.value || selectedUserIds.value.length === 0) {
        return toast.warning("Nama grup dan anggota wajib diisi!");
    }

    try {
        await axios.post("/chat/group/create", {
            name: newGroupName.value,
            member_ids: selectedUserIds.value // Sesuai validasi di controller: 'member_ids'
        });
        
        toast.success("Grup berhasil dibuat!");
        isCreateModalOpen.value = false;
        newGroupName.value = "";
        selectedUserIds.value = [];
        
        // Refresh list
        fetchGroups();
    } catch (err) {
        console.error(err);
        toast.error("Gagal membuat grup");
    }
};

// 4. Pilih Group & Load Pesan
const selectGroup = async (group: any) => {
    activeGroup.value = group;
    messages.value = [];
    
    try {
        const { data } = await axios.get(`/chat/group/${group.id}/messages`);
        messages.value = data;
        scrollToBottom();
    } catch (err) {
        console.error(err);
        toast.error("Gagal memuat pesan grup");
    }
};

// 5. Kirim Pesan Group
const sendGroupMessage = async () => {
    if (!newMessage.value.trim() || !activeGroup.value) return;

    const payload = {
        group_id: activeGroup.value.id,
        message: newMessage.value
    };

    // Optimistic UI
    messages.value.push({
        id: Date.now(),
        sender_id: currentUser.value.id,
        sender: currentUser.value, // Mock sender data biar nama/foto muncul
        message: newMessage.value,
        created_at: new Date().toISOString(),
    });

    const tempMsg = newMessage.value;
    newMessage.value = "";
    scrollToBottom();

    try {
        await axios.post("/chat/group/send", payload);
        // Bisa refresh messages kalau mau data real dari server, tapi optimistic UI biasanya cukup
    } catch (err) {
        console.error(err);
        toast.error("Gagal kirim pesan");
        newMessage.value = tempMsg; // Balikin pesan kalau gagal
    }
};

// --- HELPER ---

const scrollToBottom = () => {
    nextTick(() => {
        if (chatBodyRef.value) {
            chatBodyRef.value.scrollTop = chatBodyRef.value.scrollHeight;
        }
    });
};

const getAvatar = (user: any) => {
    if (!user) return getAssetPath("media/avatars/300-3.jpg");
    if (user.profile_photo_url) return user.profile_photo_url;
    if (user.photo) return getAssetPath(user.photo);
    return getAssetPath("media/avatars/300-3.jpg");
};

// Initials Avatar untuk Group (Contoh: "Team A" -> "TA")
const getGroupInitials = (name: string) => {
    return name.substring(0, 2).toUpperCase();
};

const formatTime = (dateString: string) => {
    if (!dateString) return "";
    const date = new Date(dateString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    fetchGroups();
    fetchUsersForModal();
});
</script>

<template>
    <div class="d-flex flex-column flex-lg-row position-relative" style="height: calc(100vh - 120px);">
        
        <div v-if="isCreateModalOpen" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(0,0,0,0.5); z-index: 9999;">
            <div class="card w-100 mw-500px shadow-lg animate__animated animate__fadeInDown">
                <div class="card-header">
                    <h3 class="card-title">Buat Grup Baru</h3>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-sm btn-icon btn-light-danger" @click="isCreateModalOpen = false">
                            <i class="la la-close"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-5">
                        <label class="form-label fw-bold">Nama Grup</label>
                        <input v-model="newGroupName" type="text" class="form-control" placeholder="Contoh: Tim IT Support" />
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label fw-bold">Pilih Anggota</label>
                        <div class="border rounded p-3 scroll-y" style="max-height: 200px;">
                            <div v-for="user in availableUsers" :key="user.id" class="form-check form-check-custom form-check-solid mb-3">
                                <input class="form-check-input" type="checkbox" :value="user.id" v-model="selectedUserIds" />
                                <label class="form-check-label d-flex align-items-center ms-3">
                                    <div class="symbol symbol-30px me-2">
                                        <img :src="getAvatar(user)" alt="" />
                                    </div>
                                    <span class="fw-semibold text-gray-800">{{ user.name }}</span>
                                </label>
                            </div>
                            <div v-if="availableUsers.length === 0" class="text-muted text-center fs-7">
                                Tidak ada user lain untuk ditambahkan.
                            </div>
                        </div>
                        <div class="form-text">Kamu otomatis menjadi admin grup ini.</div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end pt-3 pb-3">
                    <button class="btn btn-light me-3" @click="isCreateModalOpen = false">Batal</button>
                    <button class="btn btn-primary" @click="createGroup">
                        <KTIcon icon-name="plus-square" icon-class="fs-2 me-1" /> Buat Grup
                    </button>
                </div>
            </div>
        </div>
        <div class="flex-column flex-lg-row-auto w-100 w-lg-300px w-xl-400px mb-10 mb-lg-0 h-100">
            <div class="card card-flush h-100">
                <div class="card-header pt-7">
                    <div class="d-flex align-items-center w-100 justify-content-between">
                        <h3 class="card-title fw-bold text-gray-800">Grup Saya</h3>
                        
                        <button class="btn btn-sm btn-light-primary fw-bold" @click="isCreateModalOpen = true">
                            <KTIcon icon-name="plus" icon-class="fs-2" /> Baru
                        </button>
                    </div>
                </div>

                <div class="card-body pt-5">
                    <div class="scroll-y me-n5 pe-5 h-100" style="max-height: 100%;">
                        
                        <template v-if="groups.length > 0">
                            <div 
                                v-for="group in groups" 
                                :key="group.id" 
                                class="d-flex flex-stack py-4 cursor-pointer border-bottom border-dashed border-gray-300"
                                :class="{ 'bg-light-primary rounded px-2': activeGroup?.id === group.id }"
                                @click="selectGroup(group)"
                            >
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-45px symbol-circle">
                                        <span class="symbol-label bg-light-warning text-warning fs-6 fw-bolder">
                                            {{ getGroupInitials(group.name) }}
                                        </span>
                                    </div>
                                    <div class="ms-5">
                                        <a href="#" class="fs-5 fw-bold text-gray-900 text-hover-primary mb-2">
                                            {{ group.name }}
                                        </a>
                                        <div class="fw-semibold text-muted fs-7">
                                            {{ group.members_count }} Anggota
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <div v-else class="text-center text-muted mt-10">
                            Belum bergabung di grup manapun.
                            <br>Buat grup baru sekarang!
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="flex-lg-row-fluid ms-lg-7 ms-xl-10 h-100">
            <div class="card h-100">
                
                <div class="card-header" id="kt_chat_messenger_header">
                    <div class="card-title">
                        <div class="d-flex justify-content-center flex-column me-3">
                            <template v-if="activeGroup">
                                <a href="#" class="fs-4 fw-bold text-gray-900 text-hover-primary me-1 mb-2 lh-1">
                                    {{ activeGroup.name }}
                                </a>
                                <div class="mb-0 lh-1">
                                    <span class="badge badge-primary badge-circle w-10px h-10px me-1"></span>
                                    <span class="fs-7 fw-semibold text-muted">{{ activeGroup.members_count }} Anggota</span>
                                </div>
                            </template>
                            <template v-else>
                                <span class="fs-4 fw-bold text-gray-500">
                                    Group Chat Area
                                </span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="card-body" id="kt_chat_messenger_body">
                    <div 
                        class="scroll-y me-n5 pe-5 h-100" 
                        ref="chatBodyRef"
                        style="max-height: 100%;"
                    >
                        <template v-if="activeGroup">
                            <template v-if="messages.length > 0">
                                <div 
                                    v-for="msg in messages" 
                                    :key="msg.id" 
                                    class="d-flex mb-10"
                                    :class="msg.sender_id === currentUser.id ? 'justify-content-end' : 'justify-content-start'"
                                >
                                    <div 
                                        class="d-flex flex-column align-items-start"
                                        :class="msg.sender_id === currentUser.id ? 'align-items-end' : 'align-items-start'"
                                    >
                                        <div class="d-flex align-items-center mb-2">
                                            <div v-if="msg.sender_id !== currentUser.id" class="symbol symbol-35px symbol-circle me-3">
                                                <img :src="getAvatar(msg.sender)" alt="image">
                                            </div>
                                            <div class="d-flex flex-column" :class="msg.sender_id === currentUser.id ? 'align-items-end' : 'align-items-start'">
                                                <span class="text-gray-900 fs-7 fw-bold" v-if="msg.sender_id !== currentUser.id">
                                                    {{ msg.sender?.name || 'Unknown' }}
                                                </span>
                                                <span class="text-muted fs-8">
                                                    {{ formatTime(msg.created_at) }}
                                                </span>
                                            </div>
                                        </div>

                                        <div 
                                            class="p-5 rounded fw-semibold text-start shadow-sm" 
                                            :class="msg.sender_id === currentUser.id ? 'bg-primary text-white' : 'bg-light text-dark'"
                                            style="max-width: 400px;"
                                        >
                                            {{ msg.message }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center mt-20 text-muted">
                                <KTIcon icon-name="messages" icon-class="fs-1 text-gray-300 mb-3" />
                                <p>Grup masih sepi. Kirim pesan pertama!</p>
                            </div>
                        </template>
                        
                        <div v-else class="d-flex flex-column align-items-center justify-content-center h-100">
                            <div class="symbol symbol-100px mb-5">
                                <div class="symbol-label fs-2 fw-semibold text-warning bg-light-warning">
                                    <KTIcon icon-name="people" icon-class="fs-1" />
                                </div>
                            </div>
                            <h3 class="text-gray-900 fw-bold">Pilih Grup</h3>
                            <p class="text-gray-400">Pilih salah satu grup di sidebar kiri untuk mulai diskusi.</p>
                        </div>
                    </div>
                </div>

                <div class="card-footer pt-4" v-if="activeGroup">
                    <textarea 
                        class="form-control form-control-flush mb-3" 
                        rows="1" 
                        placeholder="Ketik pesan ke grup..."
                        v-model="newMessage"
                        @keydown.enter.prevent="sendGroupMessage"
                    ></textarea>
                    
                    <div class="d-flex flex-stack">
                        <div class="d-flex align-items-center me-2">
                            <button class="btn btn-sm btn-icon btn-active-light-primary me-1" type="button">
                                <KTIcon icon-name="paper-clip" icon-class="fs-3" />
                            </button>
                        </div>
                        <button class="btn btn-primary" type="button" @click="sendGroupMessage">
                            Kirim
                            <KTIcon icon-name="send" icon-class="fs-2 ms-2" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>