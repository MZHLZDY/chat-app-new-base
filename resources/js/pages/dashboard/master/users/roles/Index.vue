<script setup lang="ts">
import { ref, onMounted, nextTick, computed, onUnmounted, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import { format, isToday, isYesterday, isSameDay, formatDistanceToNow } from 'date-fns';
import { id } from 'date-fns/locale'; // Pastikan install: npm install date-fns
import { Phone, Video } from 'lucide-vue-next';

// Component Form Kontak (Edit/Add)
import ContactForm from "./Form.vue"; 

// Definisi Global Echo
declare global {
    interface Window {
        Echo: any;
    }
}

// --- STATE UTAMA ---
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const contacts = ref<any[]>([]);
const messages = ref<any[]>([]);
const activeContact = ref<any>(null);
const newMessage = ref("");
const isLoadingContact = ref(false);
const isLoadingMessages = ref(false);

// Refs DOM
const chatBodyRef = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

// --- STATE MODAL ---
const isAddContactOpen = ref(false);
const contactIdToEdit = ref<string | number | undefined>(undefined);

// State Modal Hapus
const isDeleteModalOpen = ref(false);
const messageToDelete = ref<any>(null);

// State Lightbox (Zoom Gambar)
const isLightboxOpen = ref(false);
const activeLightboxUrl = ref("");

const getChatChannel = (otherId: any) => {
    if (!currentUser.value) return "";
    const myId = parseInt(String(currentUser.value.id));
    const friendId = parseInt(String(otherId));
    const ids = [myId, friendId].sort((a, b) => a - b);
    return `chat.${ids[0]}.${ids[1]}`;
};

const scrollToBottom = () => {
    nextTick(() => {
        if (chatBodyRef.value) {
            chatBodyRef.value.scrollTo({
                top: chatBodyRef.value.scrollHeight,
                behavior: 'smooth'
            });
        }
    });
};

watch(messages, () => {
        scrollToBottom();
    }, { deep: true });

watch(activeContact, () => {
        setTimeout(() => {
            scrollToBottom();
        }, 100);
    });

const formatTime = (dateStr: string) => {
    return format(new Date(dateStr), 'HH:mm');
};

// Logic Header Status (Online / Last Seen)
const getContactStatus = (contact: any) => {
    if (!contact) return "";
    if (contact.is_online) return "Online";
    if (!contact.last_seen_at) return "Offline";
    return "Terakhir dilihat " + formatDistanceToNow(new Date(contact.last_seen_at), { addSuffix: true, locale: id });
};

// Logic Pembatas Tanggal
const shouldShowDateDivider = (index: number) => {
    if (index === 0) return true;
    const currentMsgDate = new Date(messages.value[index].created_at);
    const prevMsgDate = new Date(messages.value[index - 1].created_at);
    return !isSameDay(currentMsgDate, prevMsgDate);
};

const formatDateLabel = (dateString: string) => {
    const date = new Date(dateString);
    if (isToday(date)) return "Hari Ini";
    if (isYesterday(date)) return "Kemarin";
    return format(date, "d MMMM yyyy", { locale: id });
};


const fetchContacts = async () => {
    isLoadingContact.value = true;
    try {
        const response = await axios.get("/chat/contacts");
        let contactsData = response.data;
        
        if (!Array.isArray(contactsData) && contactsData.data) {
             contactsData = contactsData.data;
        }

        if (Array.isArray(contactsData)) {
            contacts.value = contactsData.map((c: any) => ({
                ...c,
                unread_count: c.unread_count || 0,
                is_online: false
            }));
        } else {
            console.warn("Format respon kontak tidak valid:", contactsData);
            contacts.value = [];
        }

    } catch (error) {
        console.error("Gagal load kontak", error);
        toast.error("Gagal memuat kontak");
    } finally {
        isLoadingContact.value = false;
    }
};

const selectContact = async (contact: any) => {
    if (activeContact.value && activeContact.value.id === contact.id) return;

    if (activeContact.value && window.Echo) {
        try {
            window.Echo.leave(getChatChannel(activeContact.value.id));
        } catch (e) {}
    }

    activeContact.value = contact;
    messages.value = [];
    isLoadingMessages.value = true;

    const idx = contacts.value.findIndex(c => c.id === contact.id);
    if (idx !== -1) contacts.value[idx].unread_count = 0;

    try {
        const { data } = await axios.get(`/chat/messages/${contact.id}`);
        messages.value = Array.isArray(data) ? data : (data.data || []);
        scrollToBottom();
    } catch (error) {
        toast.error("Gagal memuat pesan.");
    } finally {
        isLoadingMessages.value = false;
    }

    // 4. Listen Realtime
    listenForActiveChat(contact);
};

// =========================================================================
// 3. LOGIKA KIRIM & DOWNLOAD
// =========================================================================

const sendMessage = async () => {
    const file = fileInput.value?.files?.[0];
    if (!newMessage.value.trim() && !file) return;

    const formData = new FormData();
    formData.append("receiver_id", activeContact.value.id);
    if (newMessage.value.trim()) formData.append("message", newMessage.value);
    
    if (file) {
        formData.append("file", file);
        // Reset input file visual
        if (fileInput.value) fileInput.value.value = "";
    }

    try {
        // Optimistic UI (opsional): Bisa push dulu ke messages.value biar cepet
        const { data } = await axios.post("/chat/send", formData, {
            headers: { "Content-Type": "multipart/form-data" }
        });

        // Tambahkan ke list (jika tidak pakai echo listener u/ diri sendiri)
        messages.value.push(data);
        newMessage.value = "";
        scrollToBottom();

        // Pindahkan kontak ke paling atas
        refreshContactOrder(activeContact.value.id, data.message);

    } catch (error: any) {
        console.error(error);
        if (error.response?.status === 422) {
            toast.error("File terlalu besar atau format salah.");
        } else {
            toast.error("Gagal mengirim pesan.");
        }
    }
};

const triggerFileUpload = () => {
    fileInput.value?.click();
};

const downloadAttachment = async (msg: any) => {
    try {
        const response = await axios.get(`/chat/download/${msg.id}`, { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', msg.file_name || 'download');
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        toast.error("Gagal mendownload file.");
    }
};

// =========================================================================
// 4. LOGIKA REALTIME (ECHO)
// =========================================================================

const listenForActiveChat = (contact: any) => {
    if (!window.Echo) return;
    const channelName = getChatChannel(contact.id);

    // Pastikan channels.php return array, bukan boolean
    window.Echo.join(channelName)
        .listen('.MessageSent', (e: any) => {
            if (e.message.sender_id !== currentUser.value?.id) {
                const exists = messages.value.some((m: any) => m.id === e.message.id);
                if (!exists) {
                messages.value.push(e.message);
                scrollToBottom();
                }
            }
        })
        .listen('.FileMessageSent', (e: any) => {
             if (e.message.sender_id !== currentUser.value?.id) {
                const exists = messages.value.some((m: any) => m.id === e.message.id);
                if (!exists) {
                messages.value.push(e.message);
                scrollToBottom();
                }
            }
        })
        .listen('.message.deleted', (e: any) => {
            messages.value = messages.value.filter((m: any) => m.id !== e.messageId);
        });
};

const listenGlobalNotifications = () => {
    if (!currentUser.value) return;
    
    window.Echo.private(`notifications.${currentUser.value.id}`)
        .listen('.MessageSent', (e: any) => {
            if (!activeContact.value || activeContact.value.id !== e.message.sender_id) {
                const idx = contacts.value.findIndex(c => c.id === e.message.sender_id);
                if (idx !== -1) {
                    contacts.value[idx].unread_count += 1;
                    const contact = contacts.value.splice(idx, 1)[0];
                    contacts.value.unshift(contact);
                }
            }
        });
};

const refreshContactOrder = (contactId: any, lastMsg: string) => {
    const idx = contacts.value.findIndex(c => c.id === contactId);
    if (idx !== -1) {
        const contact = contacts.value[idx];
        // contact.last_message = lastMsg; // jika ada field ini
        contacts.value.splice(idx, 1);
        contacts.value.unshift(contact);
    }
};

// Delete Message
const openDeleteModal = (msg: any) => {
    messageToDelete.value = msg;
    isDeleteModalOpen.value = true;
};
const closeDeleteModal = () => {
    isDeleteModalOpen.value = false;
    messageToDelete.value = null;
};
const confirmDelete = async (type: 'me' | 'everyone') => {
    if (!messageToDelete.value) return;
    const msgId = messageToDelete.value.id;

    try {
        await axios.delete(`/chat/delete/${msgId}`, { data: { type } });
        messages.value = messages.value.filter(m => m.id !== msgId);
        toast.success("Pesan dihapus");
    } catch (e) {
        toast.error("Gagal menghapus pesan");
    } finally {
        closeDeleteModal();
    }
};

// Lightbox
const openLightbox = (path: string) => {
    activeLightboxUrl.value = `/storage/${path}`;
    isLightboxOpen.value = true;
};
const closeLightbox = () => {
    isLightboxOpen.value = false;
    activeLightboxUrl.value = "";
};

// Add Contact
const openAddContactModal = () => {
    contactIdToEdit.value = undefined;
    isAddContactOpen.value = true;
};
const openEditContactModal = (contact: any) => {
    contactIdToEdit.value = contact.id;
    isAddContactOpen.value = true;
};

// LIFECYCLE
onMounted(() => {
    fetchContacts();
    if (window.Echo) {
        listenGlobalNotifications();
    }
});

onUnmounted(() => {
    if (window.Echo && activeContact.value) {
        window.Echo.leave(getChatChannel(activeContact.value.id));
    }
});
</script>

<template>
    <div class="d-flex flex-column flex-lg-row h-100">
        <div class="flex-column flex-lg-row-auto w-100 w-lg-350px w-xl-400px mb-10 mb-lg-0">
            <div class="card card-flush h-100">
                <div class="card-header pt-7" id="kt_chat_contacts_header">
                    <form class="w-100 position-relative" autocomplete="off">
                        <KTIcon icon-name="magnifier" icon-class="fs-2 text-lg-1 text-gray-500 position-absolute top-50 ms-5 translate-middle-y" />
                        <input type="text" class="form-control form-control-solid px-15" placeholder="Cari kontak..." />
                    </form>
                </div>
                
                <div class="card-body pt-5" id="kt_chat_contacts_body">
                    <div class="scroll-y me-n5 pe-5 h-200px h-lg-auto" style="max-height: 60vh;">
                        
                        <div v-if="isLoadingContact" class="text-center mt-5">
                            <span class="spinner-border spinner-border-sm text-primary"></span>
                        </div>

                        <div v-for="contact in contacts" :key="contact.id" 
                             @click="selectContact(contact)"
                             class="d-flex flex-stack py-4 px-2 cursor-pointer hover-effect rounded"
                             :class="{ 'bg-light-primary': activeContact && activeContact.id === contact.id }">
                            
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-45px symbol-circle">
                                    <img :src="contact.photo ? `/storage/${contact.photo}` : '/media/avatars/blank.png'" alt="pic" />
                                    <div v-if="contact.is_online" class="symbol-badge bg-success start-100 top-100 border-4 h-8px w-8px ms-n2 mt-n2"></div>
                                </div>
                                <div class="ms-5">
                                    <span class="fs-5 fw-bold text-gray-900 mb-2 d-block">{{ contact.name }}</span>
                                    <div class="fw-semibold text-muted">{{ contact.email }}</div>
                                </div>
                            </div>

                            <div class="d-flex flex-column align-items-end ms-2">
                                <span class="text-muted fs-7 mb-1">{{ contact.contact_added_at ? formatTime(contact.contact_added_at) : '' }}</span>
                                <span v-if="contact.unread_count > 0" class="badge badge-circle badge-primary w-20px h-20px">
                                    {{ contact.unread_count }}
                                </span>
                            </div>
                        </div>

                    </div>
                    
                    <button class="btn btn-primary w-100 mt-4" @click="openAddContactModal">
                        <KTIcon icon-name="plus" icon-class="fs-2" /> Tambah Kontak
                    </button>
                </div>
            </div>
        </div>

<div class="flex-lg-row-fluid ms-lg-7 ms-xl-10">
            <div class="card h-100 overflow-hidden" id="kt_chat_messenger">
                
                <div v-if="!activeContact" class="card-body d-flex flex-column justify-content-center align-items-center h-100">
                    <div class="symbol symbol-100px mb-5">
                        <img src="/media/illustrations/sketchy-1/2.png" alt="Welcome" />
                    </div>
                    <h3 class="fw-bold text-gray-800">Selamat Datang</h3>
                    <p class="text-muted">Silakan pilih kontak untuk mulai mengobrol.</p>
                </div>

                <div v-else class="d-flex flex-column h-100">
                    
                    <div class="card-header d-flex align-items-center p-3 border-bottom sticky-top" style="min-height: 70px;">
                        <div class="symbol symbol-45px symbol-circle me-3">
                            <img :src="activeContact.photo ? `/storage/${activeContact.photo}` : '/media/avatars/blank.png'" alt="image" />
                            <div v-if="activeContact.is_online" class="symbol-badge bg-success start-100 top-100 border-4 h-10px w-10px ms-n2 mt-n2"></div>
                        </div>

                        <div class="d-flex flex-column flex-grow-1">
                            <div class="d-flex align-items-center">
                                <span class="fw-bold fs-5 text-gray-900 me-2">{{ activeContact.name }}</span>
                                <button @click="openEditContactModal(activeContact)" class="btn btn-sm btn-icon btn-light-primary w-20px h-20px">
                                    <i class="fas fa-pen fs-9"></i>
                                </button>
                            </div>
                            <span class="fs-8" :class="activeContact.is_online ? 'text-success fw-bold' : 'text-muted'">
                                {{ getContactStatus(activeContact) }}
                            </span>
                        </div>
                        <button>
                            <Video class="w-5 h-5 text-gray-700 dark:text-gray-300"/>
                        </button>
                        <button>
                            <Phone class="w-5 h-5 text-gray-700 dark:text-gray-300"/>
                        </button>
                    </div>

                    <div class="card-body p-4 chat-body-custom" ref="chatBodyRef">
                        
                        <div v-if="isLoadingMessages" class="d-flex justify-content-center align-items-center h-100">
                            <span class="spinner-border text-primary"></span>
                        </div>

                        <div v-else v-for="(msg, index) in messages" :key="msg.id">
                            
                            <div v-if="shouldShowDateDivider(index)" class="d-flex justify-content-center my-4">
                                <span class="badge badge-light-primary text-primary px-3 py-2 rounded-pill shadow-sm fs-9 fw-bold border">
                                    {{ formatDateLabel(msg.created_at) }}
                                </span>
                            </div>

                            <div class="d-flex mb-4" :class="msg.sender_id === currentUser?.id ? 'justify-content-end' : 'justify-content-start'">
                                <div class="d-flex flex-column" :class="msg.sender_id === currentUser?.id ? 'align-items-end' : 'align-items-start'">
                                    
                                    <div class="p-3 rounded shadow-sm position-relative group-hover"
                                         :class="msg.sender_id === currentUser?.id 
                                            ? 'bg-primary text-white rounded-bottom-end-0' 
                                            : 'receiver-bubble rounded-bottom-start-0'"
                                         style="max-width: 320px; min-width: 120px;">
                                        
                                        <div v-if="msg.type === 'image'" class="mb-2 position-relative">
                                            <img :src="`/storage/${msg.file_path}`" class="rounded w-100 cursor-pointer border" 
                                                 @click="openLightbox(msg.file_path)" 
                                                 style="max-height: 250px; object-fit: cover;">
                                        </div>

                                        <div v-else-if="msg.type === 'file'" class="d-flex align-items-center p-2 rounded mb-2"
                                             :class="msg.sender_id === currentUser?.id ? 'bg-white bg-opacity-20' : 'bg-light'">
                                            <div class="symbol symbol-35px me-2">
                                                <span class="symbol-label fw-bold text-primary bg-white">FILE</span>
                                            </div>
                                            <div class="text-truncate" style="max-width: 150px;">
                                                <a href="#" @click.prevent="downloadAttachment(msg)" 
                                                   class="fw-bold fs-7 d-block text-truncate"
                                                   :class="msg.sender_id === currentUser?.id ? 'text-white' : 'text-gray-800'">
                                                    {{ msg.file_name }}
                                                </a>
                                                <div class="fs-8" :class="msg.sender_id === currentUser?.id ? 'text-white text-opacity-75' : 'text-muted'">
                                                    {{ (msg.file_size / 1024).toFixed(0) }} KB
                                                </div>
                                            </div>
                                            <button @click="downloadAttachment(msg)" class="btn btn-sm btn-icon ms-auto"
                                                    :class="msg.sender_id === currentUser?.id ? 'btn-white text-primary' : 'btn-light text-gray-600'">
                                                <i class="fas fa-download fs-7"></i>
                                            </button>
                                        </div>

                                        <div v-if="msg.message" class="fs-6 px-1 text-break">
                                            {{ msg.message }}
                                        </div>

                                        <div class="d-flex justify-content-end align-items-center mt-1">
                                            <span class="fs-9 me-1" :class="msg.sender_id === currentUser?.id ? 'text-white text-opacity-75' : 'text-muted'">
                                                {{ formatTime(msg.created_at) }}
                                            </span>
                                            <div v-if="msg.sender_id === currentUser?.id">
                                                <i class="fas fa-check-double fs-9" :class="msg.read_at ? 'text-white' : 'text-white text-opacity-50'"></i>
                                            </div>
                                        </div>

                                        <div class="position-absolute top-0 end-0 mt-n2 me-n2 delete-btn-wrapper">
                                            <button @click="openDeleteModal(msg)" class="btn btn-sm btn-icon btn-circle btn-white shadow w-20px h-20px">
                                                <i class="fas fa-trash fs-9 text-danger"></i>
                                            </button>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer pt-4 pb-4" style="min-height: 80px;">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-sm btn-icon btn-active-light-primary me-2" @click="triggerFileUpload">
                                <KTIcon icon-name="paper-clip" icon-class="fs-3" />
                            </button>
                            <input type="file" ref="fileInput" class="d-none" @change="sendMessage" />
                            
                            <input v-model="newMessage" 
                                   @keyup.enter="sendMessage"
                                   type="text" 
                                   class="form-control form-control-solid me-3" 
                                   placeholder="Ketik pesan..." />
                            
                            <button class="btn btn-primary btn-icon" @click="sendMessage">
                                <KTIcon icon-name="send" icon-class="fs-2" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="isAddContactOpen" class="modal-overlay">
        <div class="modal-content-wrapper bg-white rounded shadow p-0 overflow-hidden" style="max-width: 500px; width: 100%;">
            <ContactForm 
                :selected="contactIdToEdit" 
                @close="isAddContactOpen = false" 
                @refresh="fetchContacts" 
            />
        </div>
    </div>

    <div v-if="isLightboxOpen" class="lightbox-overlay" @click.self="closeLightbox">
        <div class="lightbox-content position-relative text-center">
            <button @click="closeLightbox" class="btn btn-icon btn-sm btn-dark position-absolute top-0 end-0 m-3 shadow z-index-10">
                <i class="fas fa-times fs-2"></i>
            </button>
            <img :src="activeLightboxUrl" class="img-fluid rounded shadow-lg" style="max-height: 85vh; max-width: 90vw;" />
        </div>
    </div>

    <div v-if="isDeleteModalOpen" class="modal-overlay">
        <div class="modal-content bg-white rounded shadow p-5 text-center" style="width: 350px;">
            <div class="symbol symbol-50px symbol-circle bg-light-danger mb-4">
                <i class="fas fa-trash fs-2 text-danger p-3"></i>
            </div>
            <h3 class="fw-bold text-gray-800 mb-1">Hapus Pesan?</h3>
            <p class="text-muted fs-7 mb-4">Pesan yang dihapus tidak dapat dikembalikan.</p>

            <div class="d-grid gap-2">
                <button @click="confirmDelete('me')" class="btn btn-light-primary">
                    Hapus untuk saya
                </button>
                <button v-if="messageToDelete?.sender_id === currentUser?.id" 
                        @click="confirmDelete('everyone')" 
                        class="btn btn-light-danger">
                    Hapus untuk semua orang
                </button>
                <button @click="closeDeleteModal" class="btn btn-link text-muted btn-sm">
                    Batal
                </button>
            </div>
        </div>
    </div>

</template>

<style scoped>
.scroll-y { 
    overflow-y: auto; 
    scrollbar-width: thin; 
    scrollbar-color: #d1d5db transparent;
}

/* Hover Effect Kontak */
.hover-effect { transition: background-color 0.2s; }
.hover-effect:hover { background-color: #f1faff; }

/* Modal Overlay Base */
.modal-overlay, .lightbox-overlay {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex; justify-content: center; align-items: center;
    backdrop-filter: blur(2px);
    animation: fadeIn 0.2s ease-out;
}
.lightbox-overlay { 
    background-color: rgba(0, 0, 0, 0.9); 
    z-index: 10000; 
}

/* Delete Button Visibility */
.delete-btn-wrapper { 
    opacity: 0; 
    transition: opacity 0.2s; 
}
.group-hover:hover .delete-btn-wrapper { 
    opacity: 1; 
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Default (Light Mode) */
.chat-body-custom {
    height: calc(100vh - 265px);
    overflow-y: auto;
    background-color: #f9f9f9; /* Default Light Mode */
    scroll-behavior: smooth;
}
.receiver-bubble {
    background-color: #ffffff;
    color: #3f4254; /* Text Dark */
}

/* === DARK MODE OVERRIDES (Metronic Standard) === */
[data-bs-theme="dark"] .chat-body-custom {
    background-color: #151521 !important; /* Background Gelap */
}

[data-bs-theme="dark"] .card-header,
[data-bs-theme="dark"] .card-footer {
    background-color: #1e1e2d !important;
    border-bottom: 1px solid #2b2b40 !important;
    border-top: 1px solid #2b2b40 !important;
}

/* Bubble Teman di Dark Mode */
[data-bs-theme="dark"] .receiver-bubble {
    background-color: #2b2b40 !important; /* Abu-abu gelap */
    color: #ffffff !important;
}

/* Form Control di Dark Mode */
[data-bs-theme="dark"] .form-control-solid {
    background-color: #1b1b29 !important;
    border-color: #2b2b40 !important;
    color: #ffffff !important;
}

/* Modal di Dark Mode */
[data-bs-theme="dark"] .bg-white { 
    background-color: #1e1e2d !important; 
    color: #fff !important; 
}
[data-bs-theme="dark"] .text-gray-900 { 
    color: #fff !important; 
}
[data-bs-theme="dark"] .text-muted { 
    color: #7e8299 !important; 
}
[data-bs-theme="dark"] .hover-effect:hover { 
    background-color: #2b2b40 !important; 
}
</style>