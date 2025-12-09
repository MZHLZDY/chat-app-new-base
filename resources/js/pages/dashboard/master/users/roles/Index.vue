<script setup lang="ts">
import { ref, onMounted, nextTick, computed, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import axios from "@/libs/axios";
import { getAssetPath } from "@/core/helpers/assets";
import { toast } from "vue3-toastify";
import { format, isToday, isYesterday } from 'date-fns';
import { id as localeId } from 'date-fns/locale'; // Bahasa Indonesia

// Import Form Kontak
import ContactForm from "./Form.vue";

// --- STATE ---
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const contacts = ref<any[]>([]);
const messages = ref<any[]>([]);
const activeContact = ref<any>(null);
const newMessage = ref("");
const isLoadingContact = ref(false);
const isLoadingMessage = ref(false);
const chatBodyRef = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null); // Ref untuk input file

// State Modal Tambah Kontak
const isAddContactOpen = ref(false);

// --- API ACTIONS ---

// 1. Fetch Contacts (Dengan Last Message & Unread)
const fetchContacts = async () => {
    isLoadingContact.value = true;
    try {
        // Kita panggil endpoint yang sudah disesuaikan di ChatController
        const { data } = await axios.get("/chat/contacts"); 
        contacts.value = data.data ? data.data : data;
    } catch (err) {
        console.error(err);
        // Fallback jika endpoint chat/contacts belum siap
        const { data } = await axios.get("/master/users");
        const allUsers = data.data ? data.data : data;
        contacts.value = allUsers.filter((u: any) => u.id !== currentUser.value.id);
    } finally {
        isLoadingContact.value = false;
    }
};

// 2. Select Contact
const selectContact = async (contact: any) => {
    activeContact.value = contact;
    messages.value = [];
    isLoadingMessage.value = true;

    // Reset unread count di UI secara instan
    contact.unread_count = 0;

    try {
        const { data } = await axios.get(`/chat/messages/${contact.id}`);
        messages.value = data;
        scrollToBottom();
    } catch (err) {
        console.error(err);
        toast.error("Gagal memuat pesan");
    } finally {
        isLoadingMessage.value = false;
    }
};

// 3. Send Message (Text & File)
const sendMessage = async () => {
    if ((!newMessage.value.trim() && !selectedFile.value) || !activeContact.value) return;

    // Cek apakah ini kirim file atau text biasa
    if (selectedFile.value) {
        await sendFileMessage();
    } else {
        await sendTextMessage();
    }
};

const sendTextMessage = async () => {
    const payload = {
        receiver_id: activeContact.value.id,
        message: newMessage.value,
    };

    // Optimistic UI
    const tempMsg = {
        id: Date.now(),
        sender_id: currentUser.value.id,
        message: newMessage.value,
        type: 'text',
        created_at: new Date().toISOString(),
        is_sending: true
    };
    messages.value.push(tempMsg);
    newMessage.value = "";
    scrollToBottom();

    try {
        const { data } = await axios.post("/chat/send", payload);
        // Update pesan asli dari server
        const index = messages.value.findIndex(m => m.id === tempMsg.id);
        if (index !== -1) messages.value[index] = data;
        
        // Refresh kontak agar 'last message' di sidebar berubah
        fetchContacts(); 
    } catch (err) {
        console.error(err);
        toast.error("Gagal mengirim pesan");
    }
};

// Logic Upload File (Diambil dari Chat.vue lama)
const selectedFile = ref<File | null>(null);

const triggerFileUpload = () => {
    fileInput.value?.click();
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        selectedFile.value = target.files[0];
        // Langsung kirim saat file dipilih (seperti WA)
        sendMessage();
    }
};

const sendFileMessage = async () => {
    if (!selectedFile.value) return;

    const formData = new FormData();
    formData.append('receiver_id', activeContact.value.id.toString());
    formData.append('file', selectedFile.value);
    if (newMessage.value) formData.append('text', newMessage.value);

    // Optimistic UI (Loading placeholder)
    const tempMsg = {
        id: Date.now(),
        sender_id: currentUser.value.id,
        message: 'Mengirim file...',
        type: 'text', // Placeholder
        created_at: new Date().toISOString(),
        is_sending: true
    };
    messages.value.push(tempMsg);
    scrollToBottom();

    try {
        const { data } = await axios.post("/chat/send-file", formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        // Ganti placeholder dengan data asli (gambar/file)
        const index = messages.value.findIndex(m => m.id === tempMsg.id);
        if (index !== -1) messages.value[index] = data;

        selectedFile.value = null; // Reset
        newMessage.value = "";
        if (fileInput.value) fileInput.value.value = ""; // Reset input file
        
        fetchContacts();
    } catch (err: any) {
        console.error(err);
        toast.error(err.response?.data?.message || "Gagal mengirim file (Max 25MB)");
        messages.value.pop(); // Hapus pesan gagal
        selectedFile.value = null;
    }
};

// 4. Delete Message
const deleteMessage = async (messageId: number) => {
    if (!confirm("Hapus pesan ini?")) return;

    try {
        await axios.delete(`/chat/delete/${messageId}`);
        messages.value = messages.value.filter(m => m.id !== messageId);
        toast.success("Pesan dihapus");
    } catch (err) {
        toast.error("Gagal menghapus pesan");
    }
};

// --- HELPER FUNCTIONS ---

const scrollToBottom = () => {
    nextTick(() => {
        if (chatBodyRef.value) {
            chatBodyRef.value.scrollTop = chatBodyRef.value.scrollHeight;
        }
    });
};

const getAvatar = (user: any) => {
    if (user.profile_photo_url) return user.profile_photo_url;
    if (user.photo) return getAssetPath(user.photo);
    return getAssetPath("media/avatars/300-3.jpg");
};

// Format Tanggal Canggih (Hari ini, Kemarin, dll)
const formatMessageTime = (dateString: string) => {
    if (!dateString) return "";
    const date = new Date(dateString);
    
    if (isToday(date)) {
        return format(date, 'HH:mm');
    } else if (isYesterday(date)) {
        return 'Kemarin ' + format(date, 'HH:mm');
    } else {
        return format(date, 'd MMM HH:mm', { locale: localeId });
    }
};

// Helper untuk menampilkan gambar dari storage
const getStorageUrl = (path: string) => {
    return `/storage/${path}`;
};

onMounted(() => {
    fetchContacts();
});
</script>

<template>
    <div class="d-flex flex-column flex-lg-row position-relative" style="height: calc(100vh - 140px);">
        
        <input 
            type="file" 
            ref="fileInput" 
            class="d-none" 
            @change="handleFileSelect"
            accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip"
        />

        <div v-if="isAddContactOpen" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(0,0,0,0.5); z-index: 9999;">
            <div class="w-100 mw-600px bg-white rounded shadow-lg p-1">
                <ContactForm @close="isAddContactOpen = false" @refresh="fetchContacts" />
            </div>
        </div>

        <div class="flex-column flex-lg-row-auto w-100 w-lg-300px w-xl-400px mb-10 mb-lg-0 h-100">
            <div class="card card-flush h-100">
                <div class="card-header pt-7">
                    <div class="d-flex align-items-center w-100">
                        <form class="w-100 position-relative me-3" autocomplete="off">
                            <KTIcon icon-name="magnifier" icon-class="fs-2 text-gray-500 position-absolute top-50 ms-5 translate-middle-y" />
                            <input type="text" class="form-control form-control-solid px-13" placeholder="Cari..." />
                        </form>
                        <button class="btn btn-icon btn-primary w-40px h-40px" @click="isAddContactOpen = true">
                            <KTIcon icon-name="message-add" icon-class="fs-2" />
                        </button>
                    </div>
                </div>

                <div class="card-body pt-5">
                    <div class="scroll-y me-n5 pe-5 h-100" style="max-height: 100%;">
                        <template v-if="contacts.length > 0">
                            <div 
                                v-for="user in contacts" 
                                :key="user.id" 
                                class="d-flex flex-stack py-4 cursor-pointer border-bottom border-dashed border-gray-300"
                                :class="{ 'bg-light-primary rounded px-2': activeContact?.id === user.id }"
                                @click="selectContact(user)"
                            >
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-45px symbol-circle">
                                        <img :src="getAvatar(user)" alt="" />
                                    </div>
                                    <div class="ms-5">
                                        <div class="d-flex align-items-center">
                                            <a href="#" class="fs-5 fw-bold text-gray-900 text-hover-primary mb-2 me-2">{{ user.name }}</a>
                                            <span v-if="user.unread_count > 0" class="badge badge-circle badge-primary w-20px h-20px fs-9">
                                                {{ user.unread_count }}
                                            </span>
                                        </div>
                                        <div class="fw-semibold text-muted fs-7 text-truncate w-150px">
                                            <span v-if="user.latest_message">
                                                <i v-if="user.latest_message.type === 'image'" class="la la-image me-1"></i>
                                                <i v-else-if="user.latest_message.type === 'file'" class="la la-file me-1"></i>
                                                {{ user.latest_message.message || (user.latest_message.type === 'image' ? 'Foto' : 'File') }}
                                            </span>
                                            <span v-else>Belum ada pesan</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column align-items-end" v-if="user.latest_message">
                                    <span class="text-muted fs-9">{{ formatMessageTime(user.latest_message.created_at) }}</span>
                                </div>
                            </div>
                        </template>
                        <div v-else class="text-center text-muted mt-5">
                            Belum ada kontak.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-lg-row-fluid ms-lg-7 ms-xl-10 h-100">
            <div class="card h-100" id="kt_chat_messenger">
                <div class="card-header" id="kt_chat_messenger_header">
                    <div class="card-title">
                        <div class="d-flex justify-content-center flex-column me-3">
                            <template v-if="activeContact">
                                <a href="#" class="fs-4 fw-bold text-gray-900 text-hover-primary me-1 mb-2 lh-1">{{ activeContact.name }}</a>
                                <div class="mb-0 lh-1">
                                    <span class="badge badge-success badge-circle w-10px h-10px me-1"></span>
                                    <span class="fs-7 fw-semibold text-muted">Online</span>
                                </div>
                            </template>
                            <template v-else>
                                <span class="fs-4 fw-bold text-gray-500">Pilih kontak untuk chat</span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="card-body" id="kt_chat_messenger_body">
                    <div class="scroll-y me-n5 pe-5 h-100" ref="chatBodyRef" style="max-height: 100%;">
                        <template v-if="activeContact">
                            <template v-if="messages.length > 0">
                                <div 
                                    v-for="msg in messages" 
                                    :key="msg.id" 
                                    class="d-flex mb-10 position-relative group-chat-item"
                                    :class="msg.sender_id === currentUser.id ? 'justify-content-end' : 'justify-content-start'"
                                >
                                    <div class="d-flex flex-column align-items-start" :class="msg.sender_id === currentUser.id ? 'align-items-end' : 'align-items-start'">
                                        
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="text-muted fs-7 mb-1">{{ formatMessageTime(msg.created_at) }}</span>
                                            <span v-if="msg.sender_id === currentUser.id" @click="deleteMessage(msg.id)" class="ms-2 cursor-pointer text-hover-danger text-muted fs-8">
                                                <i class="la la-trash"></i>
                                            </span>
                                        </div>

                                        <div 
                                            class="p-5 rounded fw-semibold text-start shadow-sm" 
                                            :class="msg.sender_id === currentUser.id ? 'bg-primary text-white' : 'bg-light text-dark'"
                                            style="max-width: 400px;"
                                        >
                                            <div v-if="msg.type === 'image'">
                                                <a :href="getStorageUrl(msg.file_path)" target="_blank">
                                                    <img :src="getStorageUrl(msg.file_path)" class="rounded w-100 mb-2" style="max-height: 200px; object-fit: cover;" />
                                                </a>
                                                <div v-if="msg.message">{{ msg.message }}</div>
                                            </div>

                                            <div v-else-if="msg.type === 'file' || msg.type === 'video'" class="d-flex align-items-center">
                                                <KTIcon icon-name="file" icon-class="fs-1 me-3" :class="msg.sender_id === currentUser.id ? 'text-white' : 'text-primary'" />
                                                <div class="d-flex flex-column">
                                                    <a :href="getStorageUrl(msg.file_path)" target="_blank" class="fw-bold text-hover-underline" :class="msg.sender_id === currentUser.id ? 'text-white' : 'text-gray-900'">
                                                        {{ msg.file_name || 'Dokumen' }}
                                                    </a>
                                                    <span class="fs-8 opacity-75">{{ msg.file_size ? (msg.file_size / 1024).toFixed(1) + ' KB' : '' }}</span>
                                                </div>
                                            </div>

                                            <span v-else>{{ msg.message }}</span>
                                        </div>

                                    </div>
                                </div>
                            </template>
                            <div v-else class="text-center mt-20 text-muted">
                                <KTIcon icon-name="message-text-2" icon-class="fs-1 text-gray-300 mb-3" />
                                <p>Belum ada pesan. Mulai obrolan!</p>
                            </div>
                        </template>
                        <div v-else class="d-flex flex-column align-items-center justify-content-center h-100">
                            <div class="symbol symbol-100px mb-5">
                                <div class="symbol-label fs-2 fw-semibold text-primary bg-light-primary">
                                    <KTIcon icon-name="messages" icon-class="fs-1" />
                                </div>
                            </div>
                            <h3 class="text-gray-900 fw-bold">Selamat Datang, {{ currentUser.name }}</h3>
                            <p class="text-gray-400">Silakan pilih kontak di samping untuk memulai chat.</p>
                        </div>
                    </div>
                </div>

                <div class="card-footer pt-4" v-if="activeContact">
                    <textarea 
                        class="form-control form-control-flush mb-3" 
                        rows="1" 
                        placeholder="Ketik pesan..."
                        v-model="newMessage"
                        @keydown.enter.prevent="sendMessage"
                    ></textarea>
                    
                    <div class="d-flex flex-stack">
                        <div class="d-flex align-items-center me-2">
                            <button class="btn btn-sm btn-icon btn-active-light-primary me-1" type="button" @click="triggerFileUpload" title="Kirim File/Gambar">
                                <KTIcon icon-name="paper-clip" icon-class="fs-3" />
                            </button>
                        </div>
                        <button class="btn btn-primary" type="button" @click="sendMessage">
                            Kirim
                            <KTIcon icon-name="send" icon-class="fs-2 ms-2" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>