<script setup lang="ts">
import { ref, onMounted, nextTick, computed, onUnmounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import { format, isToday, isYesterday } from 'date-fns';

// --- IMPORT FORM KONTAK ---
import ContactForm from "./Form.vue"; 

declare global {
    interface Window {
        Echo: any;
    }
}

// --- STATE ---
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const contacts = ref<any[]>([]);
const messages = ref<any[]>([]);
const activeContact = ref<any>(null);
const newMessage = ref("");
const isLoadingContact = ref(false);
const chatBodyRef = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

// --- STATE MODAL FORM ---
const isAddContactOpen = ref(false);
const contactIdToEdit = ref<string | number | undefined>(undefined)  

// --- HELPER: CHANNEL NAMING ---
const getChatChannel = (otherId: any) => {
    if (!currentUser.value) return "";
    const myId = parseInt(String(currentUser.value.id));
    const friendId = parseInt(String(otherId));
    const ids = [myId, friendId].sort((a, b) => a - b);
    return `chat.${ids[0]}.${ids[1]}`;
};

// --- 1. FETCH CONTACTS ---
const fetchContacts = async () => {
    isLoadingContact.value = true;
    try {
        const { data } = await axios.get("/chat/contacts");
        console.log("Data Kontak Diterima:", data);
        contacts.value = data.data ? data.data : data;
    } catch (err: any) {
        console.error("Gagal load kontak:", err);
        if (err.response) {
            console.error("Status:", err.response.status);
            console.error("Response:", err.response.data);
            toast.error(`Gagal memuat kontak: ${err.response.status}`);
        }
    } finally {
        isLoadingContact.value = false;
    }
};

// --- 2. SELECT CONTACT ---
const selectContact = async (contact: any) => {
    console.log("Membuka chat:", contact.name);

    if (activeContact.value && window.Echo) {
        try {
            const oldChannel = getChatChannel(activeContact.value.id);
            window.Echo.leave(oldChannel);
        } catch (e) { console.warn(e); }
    }

    activeContact.value = contact;
    messages.value = []; 
    contact.unread_count = 0;

    try {
        const { data } = await axios.get(`/chat/messages/${contact.id}`);
        messages.value = data.data ? data.data : data;
        scrollToBottom();
    } catch (err) {
        console.error(err);
        toast.error("Gagal memuat pesan.");
    }

    listenForActiveChat(contact);
};

// --- 3. LISTENER REALTIME ---
const listenForActiveChat = (contact: any) => {
    if (!window.Echo) return;
    const channelName = getChatChannel(contact.id);

    // Pastikan channels.php return array, bukan boolean
    window.Echo.join(channelName)
        .listen('.MessageSent', (e: any) => {
            messages.value.push(e.message);
            scrollToBottom();
        })
        .listen('.FileMessageSent', (e: any) => {
            messages.value.push(e.message);
            scrollToBottom();
        })
        .listen('.message.deleted', (e: any) => {
            messages.value = messages.value.filter((m: any) => m.id !== e.messageId);
        });
};

const listenForGlobalNotifications = () => {
    if (!window.Echo || !currentUser.value) return;

    window.Echo.private(`notifications.${currentUser.value.id}`)
        .listen('.MessageSent', (e: any) => handleSidebarUpdate(e.message))
        .listen('.FileMessageSent', (e: any) => handleSidebarUpdate(e.message));
};

const handleSidebarUpdate = (message: any) => {
    if (activeContact.value && activeContact.value.id === message.sender_id) return;

    const index = contacts.value.findIndex(c => c.id === message.sender_id);
    if (index !== -1) {
        const sender = contacts.value[index];
        sender.latest_message = message;
        sender.unread_count = (sender.unread_count || 0) + 1;
        contacts.value.splice(index, 1);
        contacts.value.unshift(sender);
    } else {
        fetchContacts();
    }
};

// --- 4. SEND MESSAGE ---
const sendMessage = async () => {
    if ((!newMessage.value.trim() && !fileInput.value?.files?.length) || !activeContact.value) return;

    const formData = new FormData();
    formData.append("receiver_id", activeContact.value.id);
    if (newMessage.value.trim()) formData.append("message", newMessage.value);
    if (fileInput.value?.files?.length) formData.append("file", fileInput.value.files[0]);

    const tempId = Date.now();
    if (!fileInput.value?.files?.length) {
        messages.value.push({
            id: tempId,
            sender_id: currentUser.value.id,
            message: newMessage.value,
            created_at: new Date().toISOString(),
            is_sending: true
        });
    }

    newMessage.value = "";
    scrollToBottom();

    try {
        const { data } = await axios.post("/chat/send", formData, {
            headers: { "Content-Type": "multipart/form-data" }
        });

        if (!fileInput.value?.files?.length) {
            const idx = messages.value.findIndex((m: any) => m.id === tempId);
            if (idx !== -1) messages.value[idx] = data;
        } else {
             messages.value.push(data);
             if (fileInput.value) fileInput.value.value = "";
             scrollToBottom();
        }
    } catch (err) {
        console.error(err);
        toast.error("Gagal kirim pesan");
    }
};

// --- UTILS ---
const triggerFileUpload = () => fileInput.value?.click();

const scrollToBottom = () => {
    nextTick(() => {
        if (chatBodyRef.value) chatBodyRef.value.scrollTop = chatBodyRef.value.scrollHeight;
    });
};

const formatTime = (dateStr: string) => {
    if (!dateStr) return "";
    const date = new Date(dateStr);
    if (isToday(date)) return format(date, "HH:mm");
    return format(date, "dd/MM/yy");
};

// --- MODAL FUNCTIONS ---
const openAddContactModal = () => {
    contactIdToEdit.value = undefined; // Mode Tambah
    isAddContactOpen.value = true;
};

// Lifecycle
onMounted(() => {
    fetchContacts();
    const checkEcho = setInterval(() => {
        if (window.Echo) {
            listenForGlobalNotifications();
            clearInterval(checkEcho);
        }
    }, 500);
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave(`notifications.${currentUser.value.id}`);
        if (activeContact.value) {
            try { window.Echo.leave(getChatChannel(activeContact.value.id)); } catch(e){}
        }
    }
});
</script>

<template>
    <div class="d-flex flex-column flex-lg-row" style="height: calc(100vh - 110px);">
        
        <div class="flex-column flex-lg-row-auto w-100 w-lg-300px w-xl-400px mb-10 mb-lg-0">
            <div class="card card-flush h-100">
                <div class="card-header pt-7">
                    <div class="d-flex align-items-center w-100">
                        <form class="w-100 position-relative me-3" autocomplete="off">
                            <KTIcon icon-name="magnifier" icon-class="fs-2 text-lg-1 text-gray-500 position-absolute top-50 ms-5 translate-middle-y" />
                            <input type="text" class="form-control form-control-solid px-15" placeholder="Cari kontak..." />
                        </form>
                        
                        <button class="btn btn-icon btn-light-primary w-40px h-40px" @click="openAddContactModal" title="Tambah Kontak Baru">
                            <KTIcon icon-name="plus" icon-class="fs-2" />
                        </button>
                    </div>
                </div>

                <div class="card-body pt-5">
                    <div class="scroll-y me-n5 pe-5 h-100">
                        <div v-for="contact in contacts" :key="contact.id" 
                             class="d-flex flex-stack py-4 cursor-pointer hover-effect px-2 rounded"
                             :class="{ 'bg-light-primary': activeContact && activeContact.id === contact.id }"
                             @click="selectContact(contact)">
                            
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-45px symbol-circle">
                                    <img :src="contact.photo || `https://ui-avatars.com/api/?name=${contact.name}`" alt="img" />
                                </div>
                                <div class="ms-5">
                                    <span class="fs-5 fw-bold text-gray-900 text-hover-primary mb-2 d-block">{{ contact.name }}</span>
                                    <div class="fw-semibold text-muted text-truncate w-150px">
                                        {{ contact.latest_message ? (contact.latest_message.message || 'File Lampiran') : 'Belum ada pesan' }}
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-end ms-2">
                                <span class="text-muted fs-7 mb-1">{{ contact.latest_message ? formatTime(contact.latest_message.created_at) : '' }}</span>
                                <span v-if="contact.unread_count > 0" class="badge badge-circle badge-success w-20px h-20px d-flex align-items-center justify-content-center text-white">
                                    {{ contact.unread_count }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-lg-row-fluid ms-lg-7 ms-xl-10 h-100">
            <div class="card w-100 border-0 rounded-0 h-100 d-flex flex-column">
                <div class="card-header pe-5 flex-shrink-0">
                    <div class="card-title">
                        <div class="d-flex justify-content-center flex-column me-3">
                            <span class="fs-4 fw-bold text-gray-900 text-hover-primary me-1 mb-2 lh-1">
                                {{ activeContact ? activeContact.name : 'Pilih Kontak' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-body flex-grow-1 overflow-hidden p-0">
                    <div class="scroll-y me-n5 pe-5 h-100 p-5" ref="chatBodyRef">
                        <div v-if="!activeContact" class="d-flex flex-column align-items-center justify-content-center h-100 text-gray-400">
                            <p>Silakan pilih kontak untuk memulai chat.</p>
                        </div>

                        <div v-else v-for="msg in messages" :key="msg.id" 
                             class="d-flex mb-10" 
                             :class="msg.sender_id === currentUser.id ? 'justify-content-end' : 'justify-content-start'">
                            
                            <div class="d-flex flex-column align-items-start" :class="msg.sender_id === currentUser.id ? 'align-items-end' : 'align-items-start'">
                                <div class="p-5 rounded" 
                                     :class="msg.sender_id === currentUser.id ? 'bg-light-primary text-dark' : 'bg-light-info text-dark'">
                                    
                                    <div v-if="msg.file_path" class="mb-2">
                                        <img v-if="['jpg','jpeg','png'].includes(msg.file_path.split('.').pop())" 
                                             :src="`/storage/${msg.file_path}`" class="rounded w-200px d-block border">
                                        <a v-else :href="`/storage/${msg.file_path}`" target="_blank" class="text-primary fw-bold">
                                            Download File
                                        </a>
                                    </div>
                                    <p class="fw-semibold mb-0" style="max-width: 400px;">{{ msg.message }}</p>
                                </div>
                                <span class="text-muted fs-7 mt-1">{{ formatTime(msg.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer pt-4 flex-shrink-0" v-if="activeContact">
                    <textarea class="form-control form-control-flush mb-3" rows="1" placeholder="Ketik pesan..." v-model="newMessage" @keydown.enter.prevent="sendMessage"></textarea>
                    <div class="d-flex flex-stack">
                        <button class="btn btn-sm btn-icon btn-active-light-primary me-1" @click="triggerFileUpload">
                            <KTIcon icon-name="paper-clip" icon-class="fs-3" />
                        </button>
                        <input type="file" ref="fileInput" class="d-none" @change="sendMessage" />
                        <button class="btn btn-primary" @click="sendMessage">
                            Kirim <KTIcon icon-name="send" icon-class="fs-2 ms-2" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="isAddContactOpen" class="modal-overlay">
        <div class="modal-content-wrapper">
            <ContactForm 
                :selected="contactIdToEdit" 
                @close="isAddContactOpen = false" 
                @refresh="fetchContacts" 
            />
        </div>
    </div>
</template>

<style scoped>
.scroll-y { overflow-y: auto; scrollbar-width: thin; }
.hover-effect:hover { background-color: #f1faff; }

/* CSS Modal */
.modal-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
}
.modal-content-wrapper {
    background: white;
    border-radius: 8px;
    width: 100%;
    max-width: 600px;
    padding: 0;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
</style>