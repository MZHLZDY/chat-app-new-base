<script setup lang="ts">
import { ref, onMounted, nextTick, computed, onUnmounted, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import { format, isToday, isYesterday, isSameDay } from 'date-fns';
import { id } from 'date-fns/locale'; 
import { Phone, Video } from 'lucide-vue-next';

// Component Form Kontak
import ContactForm from "./Form.vue";
import EditForm from "./Edit.vue";

// --- FIREBASE IMPORT ---
import { db, auth } from "@/libs/firebase"; 
import { 
    ref as firebaseRef, 
    onChildAdded,
    onChildRemoved, 
    onValue,
    off, 
    remove, 
    set, 
    onDisconnect,
    type Unsubscribe,
    query, 
    orderByChild, 
    limitToLast
} from "firebase/database";
import { onAuthStateChanged } from "firebase/auth";

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
const isEditContactOpen = ref(false);
const contactIdToEdit = ref<string | number | undefined>(undefined);
const editModalTitle = ref("Edit Kontak");
const isDeleteModalOpen = ref(false);
const messageToDelete = ref<any>(null);
const isLightboxOpen = ref(false);
const activeLightboxUrl = ref("");
const heartbeatInterval = ref<any>(null);
const showScrollButton = ref(false);

// --- FIREBASE STATE (Untuk Cleanup yang Benar) ---
let unsubscribeChats: Unsubscribe | null = null;
let unsubscribeNotif: Unsubscribe | null = null;
let unsubscribeOnlineAdded: Unsubscribe | null = null;
let unsubscribeOnlineRemoved: Unsubscribe | null = null;

// Ref untuk onValue (karena onValue cara cleanup-nya beda, pakai off)
let connectedRef: any = null; 
let onlineRef: any = null;

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

const handleScroll = () => {
    if (chatBodyRef.value) {
        const { scrollTop, scrollHeight, clientHeight } = chatBodyRef.value;
        const distanceToBottom = scrollHeight - (scrollTop + clientHeight);
        showScrollButton.value = distanceToBottom > 300;
    }
};

const formatTime = (dateStr: string) => {
    if (!dateStr) return "";
    const date = new Date(dateStr);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const formatDateLabel = (dateStr: string) => {
    const date = new Date(dateStr);
    if (isToday(date)) return "Hari Ini";
    if (isYesterday(date)) return "Kemarin";
    return format(date, "d MMMM yyyy", { locale: id });
};

const shouldShowDateDivider = (index: number) => {
    if (index === 0) return true;
    const currentMsgDate = new Date(messages.value[index].created_at);
    const prevMsgDate = new Date(messages.value[index - 1].created_at);
    return !isSameDay(currentMsgDate, prevMsgDate);
};

const fetchContacts = async () => {
    isLoadingContact.value = true;
    try {
        const response = await axios.get("/chat/contacts");
        contacts.value = response.data;
    } catch (error) {
        console.error("Gagal memuat kontak", error);
    } finally {
        isLoadingContact.value = false;
    }
};

const selectContact = async (contact: any) => {
    activeContact.value = contact;
    messages.value = [];
    
    const contactIndex = contacts.value.findIndex(c => c.id === contact.id);
    if (contactIndex !== -1) {
        contacts.value[contactIndex].unread_count = 0;
    }

    await getMessages(contact.id)
};

const getMessages = async (friendId: any) => {
    isLoadingMessages.value = true;
    try {
        const response = await axios.get(`/chat/messages/${friendId}`);
        messages.value = response.data.data ? response.data.data : response.data;
        scrollToBottom();
    } catch (error) {
        console.error(error);
    } finally {
        isLoadingMessages.value = false;
    }
};

const sendMessage = async () => {
    if (!newMessage.value.trim() && !fileInput.value?.files?.length) return;
    if (!activeContact.value) return;

    if (fileInput.value?.files?.length) {
        await uploadFile();
        return;
    }

    const tempId = Date.now();
    const tempMessage = {
        id: tempId,
        sender_id: currentUser.value?.id,
        receiver_id: activeContact.value.id,
        message: newMessage.value,
        created_at: new Date().toISOString(),
        read_at: null,
        type: 'text'
    };

    messages.value.push(tempMessage);
    const msgToSend = newMessage.value;
    newMessage.value = "";
    scrollToBottom();
    refreshContactOrder(activeContact.value.id);

    try {
       const response = await axios.post("/chat/send", {
            receiver_id: activeContact.value.id,
            message: msgToSend
        });
        const realMessage = response.data.data ? response.data.data : response.data;
        const index = messages.value.findIndex(m => m.id === tempId);
        if (index !== -1) {
            messages.value[index] = realMessage;
        }
    } catch (error) {
        console.error("Gagal kirim pesan", error);
        toast.error("Gagal mengirim pesan");
    }
};

const isTempId = (id: any) => {
    return typeof id === 'number' && id > 1000000000000;
};

const triggerFileUpload = () => {
    fileInput.value?.click();
};

const uploadFile = async () => {
    const file = fileInput.value?.files?.[0];
    if (!file || !activeContact.value) return;

    const formData = new FormData();
    formData.append("receiver_id", activeContact.value.id);
    formData.append("file", file);

    if (fileInput.value) fileInput.value.value = "";

    try {
        const response = await axios.post("/chat/send-file", formData, {
            headers: { "Content-Type": "multipart/form-data" }
        });
        messages.value.push(response.data);
        scrollToBottom();
        refreshContactOrder(activeContact.value.id);
    } catch (error) {
        console.error(error);
        toast.error("Gagal mengirim file");
    }
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
        document.body.removeChild(link);
    } catch (error) {
        console.error("Gagal download", error);
        toast.error("Gagal mengunduh file");
    }
};

const refreshContactOrder = (contactId: any) => {
    const idx = contacts.value.findIndex(c => c.id === contactId);
    if (idx !== -1) {
        const contact = contacts.value.splice(idx, 1)[0];
        contacts.value.unshift(contact);
    }
};

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
    try {
        await axios.delete(`/chat/delete/${messageToDelete.value.id}`, { data: { type } });
        messages.value = messages.value.filter(m => m.id !== messageToDelete.value.id);
        toast.success(type === 'everyone' ? "Pesan dihapus untuk semua" : "Pesan dihapus untuk saya");
        closeDeleteModal();
    } catch (error: any) {
        toast.error("Gagal menghapus pesan");
    }
};

const openLightbox = (path: string) => {
    activeLightboxUrl.value = `/storage/${path}`;
    isLightboxOpen.value = true;
};
const closeLightbox = () => {
    isLightboxOpen.value = false;
    activeLightboxUrl.value = "";
};

const openAddContactModal = () => {
    contactIdToEdit.value = undefined;
    isAddContactOpen.value = true;
};

const openSaveContactModal = (contact: any) => {
    contactIdToEdit.value = contact.id;
    if (!contact.is_saved) {
        editModalTitle.value = "Simpan Kontak";
    } else {
        editModalTitle.value = "Ubah Nama Kontak";
    }
    isEditContactOpen.value = true;
};

const handleContactUpdated = async () => {
    isEditContactOpen.value = false;
    await fetchContacts(); 
    if (activeContact.value && contactIdToEdit.value === activeContact.value.id) {
        const updatedContact = contacts.value.find(c => c.id === activeContact.value.id);
        if (updatedContact) {
            activeContact.value = updatedContact;
        }
    }
    toast.success("Kontak berhasil disimpan!");
};

const updateContactStatus = (userId: any, isOnline: boolean) => {
    const strUserId = String(userId);
    const contact = contacts.value.find(c => String(c.id) === strUserId);
    if (contact) {
        contact.is_online = isOnline;
        if (!isOnline) {
            contact.last_seen = new Date().toISOString();
        }
    }
    if (activeContact.value && String(activeContact.value.id) === strUserId) {
        activeContact.value.is_online = isOnline;
        if (!isOnline) {
            activeContact.value.last_seen = new Date().toISOString();
        }
    }
};

// --- SETUP LISTENER YANG BENAR ---
const setupFirebaseListeners = () => {
    if (!currentUser.value) return;
    const myId = currentUser.value.id;
    const chatRefRaw = firebaseRef(db, `chats/${myId}`);
    const chatQuery = query(chatRefRaw, limitToLast(50)); 
    
    const processedMessageIds = new Set<number>();
    
    unsubscribeChats = onChildAdded(chatQuery, async (snapshot) => {
        const messageKey = snapshot.key;
        const incomingMsg = snapshot.val();
        
        if (!incomingMsg || !messageKey) {
            return;
        }
        
        if (processedMessageIds.has(incomingMsg.id)) {
            return;
        }
        
        processedMessageIds.add(incomingMsg.id);
        
        const msgTime = new Date(incomingMsg.created_at).getTime();
        const now = Date.now();
        const ageInSeconds = (now - msgTime) / 1000;
        
        if (ageInSeconds > 60) {
            return;
        }
        
        if (activeContact.value && incomingMsg.sender_id === activeContact.value.id) {
            const exists = messages.value.some((m: any) => m.id === incomingMsg.id);
            
            if (!exists) {
                messages.value.push(incomingMsg);
                scrollToBottom();
                
                try {
                    await axios.put(`/chat/message/${incomingMsg.id}/read`);
                } catch (error) {
                }
            }
        }
        
        const contactIndex = contacts.value.findIndex(c => c.id === incomingMsg.sender_id);
        
        if (contactIndex !== -1) {
            const contact = contacts.value[contactIndex];
            contact.last_message = incomingMsg.message || 
                (incomingMsg.type === 'image' ? 'Gambar' : 
                 incomingMsg.type === 'video' ? 'Video' : 'File');
            contact.last_message_time = incomingMsg.created_at;
            
            if (!activeContact.value || activeContact.value.id !== incomingMsg.sender_id) {
                contact.unread_count = (contact.unread_count || 0) + 1;
            }
            
            contacts.value.splice(contactIndex, 1);
            contacts.value.unshift(contact);
        } else {
            await fetchContacts();
        }
    });

    const notifRefRaw = firebaseRef(db, `notifications/${myId}`);
    const processedNotifKeys = new Set<string>();
    
    unsubscribeNotif = onChildAdded(notifRefRaw, (snapshot) => {
        const notifKey = snapshot.key;
        const notif = snapshot.val();
        
        if (!notif || !notifKey || processedNotifKeys.has(notifKey)) {
            return;
        }
        
        processedNotifKeys.add(notifKey);
        
        if (notif.type === 'read_receipt') {
            if (activeContact.value && notif.reader_id === activeContact.value.id) {
                messages.value.forEach((msg: any) => {
                    if (msg.sender_id === myId && !msg.read_at) {
                        msg.read_at = notif.read_at;
                    }
                });
            }
        } else if (notif.type === 'message_deleted') {
            messages.value = messages.value.filter((m: any) => m.id !== notif.message_id);
        }
    });

    const myOnlineRef = firebaseRef(db, `online_users/${myId}`);
    connectedRef = firebaseRef(db, ".info/connected");
    
    onValue(connectedRef, (snap) => {
        if (snap.val() === true) {
            
            set(myOnlineRef, true)
                .then(() => {
                })
                .catch((error) => {
                });
            
            onDisconnect(myOnlineRef).remove();
        }
    });

    onlineRef = firebaseRef(db, 'online_users');
    
    unsubscribeOnlineAdded = onChildAdded(onlineRef, (snapshot) => {
        if (snapshot.key) {
            updateContactStatus(snapshot.key, true);
        }
    });

    unsubscribeOnlineRemoved = onChildRemoved(onlineRef, (snapshot) => {
        if (snapshot.key) {
            updateContactStatus(snapshot.key, false);
        }
    });
};

watch(messages, () => {
    scrollToBottom();
}, { deep: true });

watch(activeContact, (newVal, oldVal) => {
    if (newVal?.id !== oldVal?.id) {
        setTimeout(() => {
            scrollToBottom();
        }, 100);
    }
});

onMounted(async () => {
    await fetchContacts();

    if (currentUser.value) {
        onAuthStateChanged(auth, (firebaseUser) => {
            if (firebaseUser) {
                setupFirebaseListeners();
                axios.post('/chat/heartbeat').catch(() => {});
                heartbeatInterval.value = setInterval(() => {
                    axios.post('/chat/heartbeat').catch(() => {});
                }, 60000);
            } else {
            }
        });
    }
});


onUnmounted(() => {
    if (unsubscribeChats) unsubscribeChats();
    if (unsubscribeNotif) unsubscribeNotif();
    if (unsubscribeOnlineAdded) unsubscribeOnlineAdded();
    if (unsubscribeOnlineRemoved) unsubscribeOnlineRemoved();
    if (connectedRef) off(connectedRef);
    if (onlineRef) off(onlineRef);
    if (currentUser.value) {
        const myOnlineRef = firebaseRef(db, `online_users/${currentUser.value.id}`);
        remove(myOnlineRef);
    }

    if (heartbeatInterval.value) {
        clearInterval(heartbeatInterval.value);
    }
});
</script>

<template>
    <div class="d-flex flex-column flex-lg-row h-100">
        <div class="flex-column flex-lg-row-auto w-100 w-lg-350px w-xl-400px mb-10 mb-lg-0">
            <div class="card card-flush h-100">
                <div class="card-header pt-7" id="kt_chat_contacts_header">
                    <div class="d-flex align-items-center w-100">
                        <form class="w-100 position-relative me-3" autocomplete="off">
                            <KTIcon icon-name="magnifier" icon-class="fs-2 text-lg-1 text-gray-500 position-absolute top-50 ms-5 translate-middle-y" />
                            <input type="text" class="form-control form-control-solid px-15" placeholder="Cari kontak..." />
                        </form>
                        <button class="btn btn-sm btn-light-primary fw-bold" @click="openAddContactModal">
                            <KTIcon icon-name="plus" icon-class="fs-2" />
                        </button>
                    </div>
                </div>
                
                <div class="card-body pt-5" id="kt_chat_contacts_body">
                    <div class="scroll-y me-n5 pe-5 h-200px h-lg-auto" style="max-height: 60vh;">
                        <div v-if="isLoadingContact" class="text-center mt-5">
                            <span class="spinner-border spinner-border-sm text-primary"></span>
                        </div>
                        <div v-for="contact in contacts" :key="contact.id" @click="selectContact(contact)" class="d-flex align-items-center p-3 mb-2 rounded cursor-pointer contact-item position-relative overflow-hidden" :class="{ 'bg-light-primary': activeContact?.id === contact.id }">      
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-40px symbol-circle me-3">
                                    <img :src="contact.photo ? `/storage/${contact.photo}` : '/media/avatars/blank.png'" alt="image">
                                    <div v-if="contact.is_online" class="symbol-badge bg-success start-100 top-100 border-4 h-8px w-8px ms-n2 mt-n2"></div>
                                </div>
                                <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center overflow-hidden">
                                            <span class="fw-bold text-gray-800 text-hover-primary fs-6 text-truncate">
                                                {{ contact.display_name }}
                                            </span>
                                            
                                            <span v-if="!contact.is_saved" class="badge badge-light-warning ms-2 fs-9 flex-shrink-0">
                                                Unknown
                                            </span>
                                        </div>
                                        <span class="text-muted fs-8 time-stamp transition-opacity ms-2">
                                            {{ formatTime(contact.last_message_time) }}
                                        </span>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="text-muted fs-7 text-truncate pe-2" style="max-width: 150px;">
                                            <span v-if="contact.last_message && contact.last_message_sender_id === currentUser?.id" class="me-1">
                                                <i v-if="contact.last_message_read_at" class="fas fa-check-double text-primary fs-9"></i>
                                                <i v-else class="fas fa-check-double text-gray-400 fs-9"></i>
                                            </span>
                                            {{ contact.last_message || 'Belum ada pesan' }}
                                        </span>
                                        <span v-if="contact.unread_count > 0" class="badge badge-circle badge-primary w-20px h-20px fs-9">
                                            {{ contact.unread_count }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-lg-row-fluid ms-lg-7 ms-xl-10" style="min-width: 0;">
            <div class="card h-100 overflow-hidden" id="kt_chat_messenger">
                <div v-if="!activeContact" class="card-body d-flex flex-column justify-content-center align-items-center h-100">
                    <div class="symbol symbol-100px mb-5">
                        <img src="/media/illustrations/sketchy-1/2.png" alt="Welcome" />
                    </div>
                    <h3 class="fw-bold text-gray-800">Selamat Datang</h3>
                    <p class="text-muted">Silakan pilih kontak untuk mulai mengobrol.</p>
                </div>

                <div v-else class="d-flex flex-column h-100">
                    <div class="card-header d-flex align-items-center p-3 border-bottom" style="min-height: 70px;" v-if="activeContact">
                    <div class="d-flex align-items-center flex-grow-1">
                        <button class="btn btn-icon btn-sm btn-active-light-primary d-lg-none me-3" @click="activeContact = null">
                            <i class="fas fa-arrow-left fs-2"></i>
                        </button>
                        
                        <div class="symbol symbol-40px symbol-circle me-3">
                            <img :src="activeContact.photo ? `/storage/${activeContact.photo}` : '/media/avatars/blank.png'" alt="image">
                        </div>
                        
                        <div class="d-flex flex-column">
                            <span class="fw-bold text-gray-800 fs-6">
                                {{ activeContact.display_name }}
                            </span>
                            <span class="text-muted fs-8">
                                {{ activeContact.is_online ? 'Online' : (activeContact.last_seen ? 'Terakhir dilihat ' + formatTime(activeContact.last_seen) : 'Offline') }}
                            </span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button 
                            v-if="!activeContact.is_saved"
                            @click="openSaveContactModal(activeContact)" 
                            class="btn btn-sm btn-light-primary d-none d-sm-inline-flex align-items-center"
                        >
                            <i class="fas fa-user-plus fs-7 me-1"></i> Simpan
                        </button>

                        <button class="btn btn-icon btn-sm text-gray-500"><Phone class="w-20px h-20px" /></button>
                        <button class="btn btn-icon btn-sm text-gray-500"><Video class="w-20px h-20px" /></button>
                        
                        <button class="btn btn-icon btn-sm text-gray-500">
                            <i class="fas fa-ellipsis-v fs-4"></i>
                        </button>
                    </div>
                </div>

                    <div class="card-body p-4 chat-body-custom" ref="chatBodyRef" @scroll="handleScroll">
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
                                            <img :src="`/storage/${msg.file_path}`" class="rounded w-100 cursor-pointer border" @click="openLightbox(msg.file_path)" style="max-height: 250px; object-fit: cover;">
                                            <button @click.stop="downloadAttachment(msg)" class="btn btn-icon btn-sm btn-dark position-absolute bottom-0 end-0 m-2 shadow-sm download-btn"style="width: 30px; height: 30px; background-color: rgba(0,0,0,0.6); border: none;" title="Download Gambar"><i class="fas fa-download fs-8 text-white"></i></button>
                                        </div>
                                        <div v-else-if="msg.type === 'file'" class="d-flex align-items-center p-2 rounded mb-2" :class="msg.sender_id === currentUser?.id ? 'bg-white bg-opacity-20' : 'bg-light'">
                                            <div class="symbol symbol-35px me-2"
                                            ><span class="symbol-label fw-bold text-primary bg-white">FILE</span>
                                        </div>
                                            <div class="text-truncate" style="max-width: 150px;">
                                                <a href="#" @click.prevent="downloadAttachment(msg)" class="fw-bold fs-7 d-block text-truncate" :class="msg.sender_id === currentUser?.id ? 'text-white' : 'text-gray-800'">{{ msg.file_name }}</a>
                                                <div class="fs-8" :class="msg.sender_id === currentUser?.id ? 'text-white text-opacity-75' : 'text-muted'">{{ (msg.file_size / 1024).toFixed(0) }} KB</div>
                                            </div>
                                            <button @click="downloadAttachment(msg)" class="btn btn-sm btn-icon ms-auto" :class="msg.sender_id === currentUser?.id ? 'btn-white text-primary' : 'btn-light text-gray-600'">
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
                                            <div v-if="msg.sender_id === currentUser?.id" class="ms-1">
                                                <span v-if="isTempId(msg.id)" title="Mengirim...">
                                                    <i class="fas fa-check text-white text-opacity-50 fs-9"></i>
                                                </span>
                                                <span v-else-if="msg.read_at" title="Dibaca">
                                                    <i class="fas fa-check-double tick-read fs-9"></i> 
                                                </span>
                                                <span v-else title="Terkirim">
                                                    <i class="fas fa-check-double text-white text-opacity-50 fs-9"></i>
                                                </span>
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
                    <transition name="fade">
                        <button 
                            v-if="showScrollButton" 
                            @click="scrollToBottom" 
                            class="btn btn-primary btn-icon shadow-sm rounded-circle position-absolute"
                            style="bottom: 100px; right: 30px; z-index: 10; width: 30px; height: 30px;"
                        >
                            <i class="fas fa-arrow-down fs-4"></i>
                        </button>
                    </transition>
                    <div class="card-footer pt-4 pb-4" style="min-height: 80px;">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-sm btn-icon btn-active-light-primary me-2" @click="triggerFileUpload"><KTIcon icon-name="paper-clip" icon-class="fs-3" /></button>
                            <input type="file" ref="fileInput" class="d-none" @change="sendMessage" />
                            <input v-model="newMessage" @keyup.enter="sendMessage" type="text" class="form-control form-control-solid me-3" placeholder="Ketik pesan..." />
                            <button class="btn btn-primary btn-icon" @click="sendMessage"><KTIcon icon-name="send" icon-class="fs-2" /></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="isAddContactOpen" class="modal-overlay">
        <div class="modal-content-wrapper bg-white rounded shadow p-0 overflow-hidden" style="max-width: 500px; width: 100%;">
            <ContactForm @close="isAddContactOpen = false" @refresh="fetchContacts" />
        </div>
    </div>
    <div v-if="isEditContactOpen" class="modal-overlay">
        <div class="modal-content-wrapper bg-white rounded shadow p-0 overflow-hidden" style="max-width: 500px; width: 100%;">
            <EditForm v-if="isEditContactOpen" :contactId="contactIdToEdit" :title="editModalTitle"  @close="isEditContactOpen = false" @updated="handleContactUpdated" @refresh="fetchContacts" />
        </div>
    </div>
    <div v-if="isLightboxOpen" class="lightbox-overlay" @click.self="closeLightbox">
        <div class="lightbox-content position-relative text-center">
            <button @click="closeLightbox" class="btn btn-icon btn-sm btn-dark position-absolute top-0 end-0 m-3 shadow z-index-10"><i class="fas fa-times fs-2"></i></button>
            <img :src="activeLightboxUrl" class="img-fluid rounded shadow-lg" style="max-height: 85vh; max-width: 90vw;" />
        </div>
    </div>
    <div v-if="isDeleteModalOpen" class="modal-overlay">
        <div class="modal-content bg-white rounded shadow p-5 text-center" style="width: 350px;">
            <div class="bg-light-danger mb-4"><i class="fas fa-trash fs-2 text-danger p-3"></i></div>
            <h3 class="fw-bold text-gray-800 mb-1">Hapus Pesan?</h3>
            <p class="text-muted fs-7 mb-4">Pesan yang dihapus tidak dapat dikembalikan.</p>
            <div class="d-grid gap-2">
                <button @click="confirmDelete('me')" class="btn btn-light-primary">Hapus untuk saya</button>
                <button v-if="messageToDelete?.sender_id === currentUser?.id" @click="confirmDelete('everyone')" class="btn btn-light-danger">Hapus untuk semua orang</button>
                <button @click="closeDeleteModal" class="btn btn-link text-muted btn-sm">Batal</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Scrollbar Customization */
.scroll-y {
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Warna Centang Read */
.tick-read {
    color: #69f0ae !important;
    animation: fadeIn 0.3s ease;
}

.fa-check, .fa-check-double {
    transition: all 0.2s ease;
}

/* Hover Effects */
.hover-effect {
    transition: background-color 0.2s ease;
}

.hover-effect:hover {
    background-color: #b6c3f0;
}

/* Modal & Lightbox Overlays */
.modal-overlay,
.lightbox-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
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
    transition: opacity 0.2s ease;
}

.group-hover:hover .delete-btn-wrapper {
    opacity: 1;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Chat Body Layout */
.chat-body-custom {
    height: calc(100vh - 265px);
    overflow-y: auto;
    background-color: #f9f9f9; /* Default Light Mode */
    scroll-behavior: smooth;
}

/* Message Bubble Colors */
.receiver-bubble {
    background-color: #ffffff;
    color: #3f4254; /* Text Dark */
}
/* Dark Mode Styles */
[data-bs-theme="dark"] .chat-body-custom {
    background-color: #151521 !important; /* Background Gelap */
}

[data-bs-theme="dark"] .card-header,
[data-bs-theme="dark"] .card-footer {
    background-color: #1e1e2d !important;
    border-bottom: 1px solid #2b2b40 !important;
    border-top: 1px solid #2b2b40 !important;
}

[data-bs-theme="dark"] .receiver-bubble {
    background-color: #2b2b40 !important;
    color: #ffffff !important;
}

[data-bs-theme="dark"] .form-control-solid {
    background-color: #1b1b29 !important;
    border-color: #2b2b40 !important;
    color: #ffffff !important;
}

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

/* fix button video / voice call agar transparan */
/* Target button di card-header yang wrap Video & Phone icon */
.card-header .d-flex button {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 8px 10px !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
    cursor: pointer;
}

.card-header .d-flex button:hover {
    background: rgba(0, 0, 0, 0.05) !important;
    transform: scale(1.1);
}

.card-header .d-flex button:active {
    transform: scale(0.95);
}

/* Icon color */
.card-header .d-flex button svg {
    width: 20px !important;
    height: 20px !important;
    color: #7e8299 !important;
    transition: color 0.2s ease !important;
}

/* Video icon hover - hijau */
.card-header .d-flex button:first-child:hover svg {
    color: #10b981 !important;
}

/* Phone icon hover - biru */
.card-header .d-flex button:last-child:hover svg {
    color: #3b82f6 !important;
}

/* Dark mode */
[data-bs-theme="dark"] .card-header .d-flex button {
    background: transparent !important;
}

[data-bs-theme="dark"] .card-header .d-flex button:hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

[data-bs-theme="dark"] .card-header .d-flex button svg {
    color: #a1a5b7 !important;
}

[data-bs-theme="dark"] .card-header .d-flex button:first-child:hover svg {
    color: #34d399 !important;
}

[data-bs-theme="dark"] .card-header .d-flex button:last-child:hover svg {
    color: #60a5fa !important;
}
</style>