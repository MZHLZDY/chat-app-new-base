<script setup lang="ts">
import { ref, onMounted, nextTick, computed, onUnmounted, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import { format, isToday, isYesterday, isSameDay } from 'date-fns';
import { id } from 'date-fns/locale'; 
import { Phone, Video, Users } from 'lucide-vue-next'; // Tambah icon Users

// Component Form (Asumsi Anda punya form untuk buat grup)
import GroupForm from "./Form.vue";
import GroupEdit from "./EditGroup.vue";

// --- FIREBASE IMPORT ---
import { db, auth } from "@/libs/firebase"; 
import { 
    ref as firebaseRef, 
    onChildAdded,
    onChildRemoved, 
    onValue,
    off, 
    set, 
    onDisconnect,
    type Unsubscribe,
    query,
    limitToLast
} from "firebase/database";
import { onAuthStateChanged } from "firebase/auth";

// --- STATE UTAMA ---
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const groups = ref<any[]>([]); // Ganti contacts jadi groups
const messages = ref<any[]>([]);
const activeGroup = ref<any>(null); // Ganti activeContact
const newMessage = ref("");
const isLoadingGroups = ref(false);
const isLoadingMessages = ref(false);

// Refs DOM
const chatBodyRef = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

// --- STATE MODAL ---
const isCreateGroupOpen = ref(false); // Ganti isAddContactOpen
const isEditGroupOpen = ref(false);
const groupIdToEdit = ref<string | number | undefined>(undefined);
const editModalTitle = ref("Edit Info Grup");
const isDeleteModalOpen = ref(false);
const messageToDelete = ref<any>(null);
const isLightboxOpen = ref(false);
const activeLightboxUrl = ref("");
const showScrollButton = ref(false);
const replyingTo = ref<any>(null);
const isHeaderMenuOpen = ref(false);
const isInfoModalOpen = ref(false);

// Typing State untuk Group (Bisa banyak orang)
const typingUsers = ref<string[]>([]); 
let typingTimeout: ReturnType<typeof setTimeout> | null = null;
let groupTypingListenerOff: Unsubscribe | null = null;

// --- FIREBASE STATE ---
let unsubscribeGroupChats: Unsubscribe | null = null;
let unsubscribeOnline: Unsubscribe | null = null;

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

const scrollToMessage = (id: number) => {
    const el = document.getElementById('msg-' + id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('bg-light-warning');
        setTimeout(() => el.classList.remove('bg-light-warning'), 1000);
    }
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

// --- FETCH GROUPS ---
const fetchGroups = async () => {
    isLoadingGroups.value = true;
    try {
        // Asumsi endpoint untuk list grup
        const response = await axios.get("/chat/groups"); 
        groups.value = response.data;
    } catch (error) {
        console.error("Gagal memuat grup", error);
    } finally {
        isLoadingGroups.value = false;
    }
};

const selectGroup = async (group: any) => {
    if (activeGroup.value?.id === group.id) return;
    
    activeGroup.value = group;
    messages.value = [];
    
    // Reset unread count lokal
    const idx = groups.value.findIndex(g => g.id === group.id);
    if (idx !== -1) {
        groups.value[idx].unread_count = 0;
    }

    // Setup listener firebase khusus untuk grup ini
    setupGroupListener(group.id);
    
    await getMessages(group.id);
};

const getMessages = async (groupId: any) => {
    isLoadingMessages.value = true;
    try {
        const response = await axios.get(`/chat/group-messages/${groupId}`);
        const rawData = response.data.data ? response.data.data : response.data;
        messages.value = rawData.filter((m: any) => {
            if (!m.deleted_by) return true;
            return !m.deleted_by.includes(currentUser.value?.id);
        });
        
        scrollToBottom();
    } catch (error) {
        console.error(error);
    } finally {
        isLoadingMessages.value = false;
    }
};

const sendMessage = async () => {
    const textContent = newMessage.value.trim();
    const file = fileInput.value?.files?.[0];

    if (!textContent && !file) return;
    if (!activeGroup.value) return;

    const tempId = Date.now();
    
    // Structure message object
    const tempMessage = {
        id: tempId,
        sender_id: currentUser.value?.id,
        group_id: activeGroup.value.id, 
        message: textContent,
        file_path: file ? URL.createObjectURL(file) : null, 
        type: file ? (file.type.startsWith('image') ? 'image' : 'file') : 'text',
        created_at: new Date().toISOString(),
        read_at: null, 
        reply_to: replyingTo.value ? replyingTo.value : null,
        sender: {
            name: currentUser.value?.name,
            photo: currentUser.value?.photo
        },
        is_temp: true
    };

    messages.value.push(tempMessage);
    scrollToBottom();
    
    const formData = new FormData();
    formData.append("group_id", activeGroup.value.id); // API Param
    
    if (textContent) formData.append("message", textContent);
    if (file) formData.append("file", file);
    
    if (replyingTo.value) {
        formData.append("reply_to_id", replyingTo.value.id);
    }

    const tempReply = replyingTo.value;
    newMessage.value = "";
    replyingTo.value = null; 
    if (fileInput.value) fileInput.value.value = "";

    try {
        // Endpoint send group
        const response = await axios.post("/chat/group/send", formData, {
            headers: { "Content-Type": "multipart/form-data" }
        });
        const realMessage = response.data.data ? response.data.data : response.data;
        const index = messages.value.findIndex(m => m.id === tempId);
        if (index !== -1) {
            messages.value[index] = realMessage;
        }

        refreshGroupOrder(activeGroup.value.id);

    } catch (error) {
        console.error("Gagal kirim pesan grup", error);
        toast.error("Gagal mengirim pesan");
        messages.value = messages.value.filter(m => m.id !== tempId);
        replyingTo.value = tempReply; 
    }
};

const setReply = (msg: any) => {
    replyingTo.value = msg; 
    nextTick(() => {
        const input = document.querySelector('textarea'); // atau input text
        if(input) (input as HTMLElement).focus();
    });
};

const cancelReply = () => {
    replyingTo.value = null;
};

const isTempId = (id: any) => {
    return typeof id === 'number' && id > 1000000000000;
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
        document.body.removeChild(link);
    } catch (error) {
        toast.error("Gagal mengunduh file");
    }
};

const refreshGroupOrder = (groupId: any) => {
    const idx = groups.value.findIndex(c => c.id === groupId);
    if (idx !== -1) {
        const group = groups.value.splice(idx, 1)[0];
        groups.value.unshift(group);
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
    const isForEveryone = (type === 'everyone');

    try {
        await axios.delete(`/chat/group/delete/${messageToDelete.value.id}`, { 
            data: { 
                delete_for_everyone: isForEveryone 
            } 
        });

        messages.value = messages.value.filter(m => m.id !== messageToDelete.value.id);
        
        toast.success(isForEveryone ? "Pesan dihapus untuk semua orang" : "Pesan dihapus untuk saya");
        closeDeleteModal();

    } catch (error: any) {
        console.error("Error delete:", error);
        const errorMsg = error.response?.data?.message || "Gagal menghapus pesan";
        toast.error(errorMsg);
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

const openCreateGroupModal = () => {
    isCreateGroupOpen.value = true;
};

const openEditGroupModal = () => {
    isHeaderMenuOpen.value = false;
    if (!activeGroup.value) return;
    groupIdToEdit.value = activeGroup.value.id; 
    editModalTitle.value = "Edit Grup";
    isEditGroupOpen.value = true; 
};

const handleGroupUpdated = async () => {
    isEditGroupOpen.value = false;
    await fetchGroups(); 
    if (activeGroup.value && groupIdToEdit.value === activeGroup.value.id) {
        const updated = groups.value.find(c => c.id === activeGroup.value.id);
        if (updated) activeGroup.value = updated;
    }
    toast.success("Info grup diperbarui!");
};

const toggleHeaderMenu = () => {
    isHeaderMenuOpen.value = !isHeaderMenuOpen.value;
};

const openInfoModal = () => {
    isHeaderMenuOpen.value = false;
    isInfoModalOpen.value = true;
};

const handleExitGroup = async () => {
    if (!activeGroup.value) return;
    if (!confirm("Keluar dari grup ini?")) return;

    try {
        await axios.post(`/chat/group/${activeGroup.value.id}/leave`);
        activeGroup.value = null;
        messages.value = [];
        await fetchGroups();
        toast.success("Berhasil keluar grup");
    } catch (error) {
        toast.error("Gagal keluar grup");
    }
};

// --- TYPING LOGIC GROUP ---
const handleTyping = () => {
    if (!activeGroup.value || !currentUser.value) return;
    
    // Path: typing_status_groups/{groupId}/{userId}
    const myTypingRef = firebaseRef(db, `typing_status_groups/${activeGroup.value.id}/${currentUser.value.id}`);
    
    set(myTypingRef, {
        name: currentUser.value.name,
        is_typing: true
    });
    
    onDisconnect(myTypingRef).remove();
    
    if (typingTimeout) clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        set(myTypingRef, null); 
    }, 2000);
};

const listenToGroupTyping = (groupId: number) => {
    if (groupTypingListenerOff) {
        groupTypingListenerOff(); 
        groupTypingListenerOff = null;
    }

    const groupRef = firebaseRef(db, `typing_status_groups/${groupId}`);
    groupTypingListenerOff = onValue(groupRef, (snapshot) => {
        const data = snapshot.val();
        typingUsers.value = [];
        
        if (data) {
            Object.keys(data).forEach((userId) => {
                if (String(userId) !== String(currentUser.value?.id)) {
                    typingUsers.value.push(data[userId].name);
                }
            });
            if(typingUsers.value.length > 0) {
                 setTimeout(() => scrollToBottom(), 100);
            }
        }
    });
};

// --- LISTENER GROUP CHAT ---
const setupGroupListener = (groupId: number) => {
    if (unsubscribeGroupChats) {
        unsubscribeGroupChats();
        unsubscribeGroupChats = null;
    }

    const groupChatRef = firebaseRef(db, `group_messages/${groupId}`);
    const chatQuery = query(groupChatRef, limitToLast(50));
    
    unsubscribeGroupChats = onChildAdded(chatQuery, (snapshot) => {
        const incomingMsg = snapshot.val();
        if (!incomingMsg) return;

        if (incomingMsg.type === 'delete_notify') {
            messages.value = messages.value.filter(m => m.id !== incomingMsg.target_message_id);
            return;
        }

        const realExists = messages.value.some((m: any) => m.id === incomingMsg.id && !m.is_temp);
        if (realExists) return;
        if (incomingMsg.sender_id === currentUser.value.id) {
            const tempIndex = messages.value.findIndex(m => 
                m.is_temp === true && 
                m.message === incomingMsg.message &&
                m.type === incomingMsg.type
            );

            if (tempIndex !== -1) {
                messages.value[tempIndex] = incomingMsg;
                return; 
            }
        }
        messages.value.push(incomingMsg);
        scrollToBottom();
    });
    listenToGroupTyping(groupId);
};


watch(messages, () => {
    scrollToBottom();
}, { deep: true });

onMounted(async () => {
    await fetchGroups();
    
    if (currentUser.value) {
        onAuthStateChanged(auth, (firebaseUser) => {
            if (!firebaseUser) {
            }
        });
    }
});

onUnmounted(() => {
    if (unsubscribeGroupChats) unsubscribeGroupChats();
    if (groupTypingListenerOff) groupTypingListenerOff();
    if (activeGroup.value && currentUser.value) {
         const myRef = firebaseRef(db, `typing_status_groups/${activeGroup.value.id}/${currentUser.value.id}`);
         set(myRef, null);
    }
});
</script>

<template>
    <div class="d-flex flex-column flex-lg-row h-100">
        <div class="flex-column flex-lg-row-auto w-100 w-lg-350px w-xl-400px mb-10 mb-lg-0">
            <div class="card card-flush h-100">
                <div class="card-header pt-7">
                    <div class="d-flex align-items-center w-100">
                        <form class="w-100 position-relative me-3" autocomplete="off">
                            <KTIcon icon-name="magnifier" icon-class="fs-2 text-lg-1 text-gray-500 position-absolute top-50 ms-5 translate-middle-y" />
                            <input type="text" class="form-control form-control-solid px-15" placeholder="Cari grup..." />
                        </form>
                        <button class="btn btn-sm btn-light-primary fw-bold" @click="openCreateGroupModal">
                            <KTIcon icon-name="plus" icon-class="fs-2" />
                        </button>
                    </div>
                </div>
                
                <div class="card-body pt-5">
                    <div class="scroll-y me-n5 pe-5 h-200px h-lg-auto" style="max-height: 60vh;">
                        <div v-if="isLoadingGroups" class="text-center mt-5">
                            <span class="spinner-border spinner-border-sm text-primary"></span>
                        </div>
                        <div v-for="group in groups" :key="group.id" @click="selectGroup(group)" class="d-flex align-items-center p-3 mb-2 rounded cursor-pointer contact-item position-relative overflow-hidden" :class="{ 'bg-light-primary': activeGroup?.id === group.id }">      
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-40px symbol-circle me-3">
                                    <img :src="group.photo ? `/storage/${group.photo}` : '/media/avatars/group-blank.png'" alt="grup">
                                </div>
                                <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center overflow-hidden">
                                            <span class="fw-bold text-gray-800 text-hover-primary fs-6 text-truncate">
                                                {{ group.name }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="text-muted fs-7 text-truncate pe-2" style="max-width: 150px;">
                                            {{ group.members_count }} Anggota
                                        </span>
                                        <span v-if="group.unread_count > 0" class="badge badge-circle badge-primary w-20px h-20px fs-9">
                                            {{ group.unread_count }}
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
                <div v-if="!activeGroup" class="card-body d-flex flex-column justify-content-center align-items-center h-100">
                    <div class="symbol symbol-100px mb-5">
                        <Users class="w-100px h-100px text-gray-300" />
                    </div>
                    <h3 class="fw-bold text-gray-800">Grup Chat</h3>
                    <p class="text-muted">Pilih grup untuk mulai berdiskusi.</p>
                </div>

                <div v-else class="d-flex flex-column h-100">
                    <div class="card-header d-flex align-items-center p-3 border-bottom" style="min-height: 70px;">
                        <div class="d-flex align-items-center flex-grow-1">
                            <button class="btn btn-icon btn-sm btn-active-light-primary d-lg-none me-3" @click="activeGroup = null">
                                <i class="fas fa-arrow-left fs-2"></i>
                            </button>
                            
                            <div class="symbol symbol-40px symbol-circle me-3">
                                <img :src="activeGroup.photo ? `/storage/${activeGroup.photo}` : '/media/avatars/group-blank.png'" alt="image">
                            </div>
                            
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-gray-800 fs-6">
                                    {{ activeGroup.name }}
                                </span>
                                <span class="text-muted fs-8">
                                    {{ activeGroup.members_count || 0 }} Anggota
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            
                            <button class="btn btn-icon btn-sm text-gray-500"><Phone class="w-20px h-20px" /></button>
                            <button class="btn btn-icon btn-sm text-gray-500"><Video class="w-20px h-20px" /></button>
                            
                            <div class="position-relative">
                                <button class="btn btn-icon btn-sm text-gray-500" @click.stop="toggleHeaderMenu">
                                    <i class="fas fa-ellipsis-v fs-4"></i>
                                </button>

                                <div v-if="isHeaderMenuOpen" class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-bold w-200px py-3 show position-absolute end-0 mt-2 shadow bg-white" style="z-index: 105;">
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" @click.prevent="openInfoModal">
                                            <i class="fas fa-info-circle me-2"></i> Info Grup
                                        </a>
                                    </div>
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" @click.prevent="openEditGroupModal">
                                            <i class="fas fa-edit me-2"></i> Edit Grup
                                        </a>
                                    </div>
                                    <div class="separator my-2"></div>
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3 text-danger" @click.prevent="handleExitGroup">
                                            <i class="fas fa-sign-out-alt me-2"></i> Keluar Grup
                                        </a>
                                    </div>
                                </div>
                                <div v-if="isHeaderMenuOpen" @click="isHeaderMenuOpen = false" class="position-fixed top-0 start-0 w-100 h-100" style="z-index: 104;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4 chat-body-custom" ref="chatBodyRef" @scroll="handleScroll">
                        <div v-if="isLoadingMessages" class="d-flex justify-content-center align-items-center h-100">
                            <span class="spinner-border text-primary"></span>
                        </div>
                        <div v-else v-for="(msg, index) in messages" :key="msg.id" :id="'msg-' + msg.id">
                            <div v-if="shouldShowDateDivider(index)" class="d-flex justify-content-center my-4">
                                <span class="badge badge-light-primary text-primary px-3 py-2 rounded-pill shadow-sm fs-9 fw-bold border">
                                    {{ formatDateLabel(msg.created_at) }}
                                </span>
                            </div>

                            <div class="d-flex mb-4" :class="msg.sender_id === currentUser?.id ? 'justify-content-end' : 'justify-content-start'">
                                
                                <div v-if="msg.sender_id !== currentUser?.id" class="me-3 d-flex flex-column align-items-end justify-content-end pb-1">
                                    <div class="symbol symbol-35px symbol-circle">
                                        <img :src="msg.sender?.photo ? `/storage/${msg.sender.photo}` : '/media/avatars/blank.png'" alt="pic" />
                                    </div>
                                </div>

                                <div class="d-flex flex-column" :class="msg.sender_id === currentUser?.id ? 'align-items-end' : 'align-items-start'">
                                    
                                    <div class="p-3 rounded shadow-sm position-relative group-hover"
                                        :class="msg.sender_id === currentUser?.id 
                                            ? 'bg-primary text-white rounded-bottom-end-0' 
                                            : 'receiver-bubble rounded-bottom-start-0'"
                                        style="max-width: 320px; min-width: 120px;">

                                        <div v-if="msg.sender_id !== currentUser?.id" class="mb-1">
                                            <span class="fw-bolder fs-9" :style="{ color: '#d97706' }">
                                                {{ msg.sender?.name || 'Unknown' }}
                                            </span>
                                        </div>

                                        <div v-if="msg.reply_to" 
                                            class="mb-2 p-2 rounded border-start border-4 cursor-pointer d-flex flex-column"
                                            :class="msg.sender_id === currentUser?.id ? 'bg-black bg-opacity-10 border-white' : 'bg-secondary bg-opacity-25 border-primary'"
                                            @click="scrollToMessage(msg.reply_to.id)">
                                            
                                            <span class="fw-bold fs-8 mb-1" 
                                                :class="msg.sender_id === currentUser?.id ? 'text-white' : 'text-primary'">
                                                {{ msg.reply_to.sender_id === currentUser?.id ? 'Anda' : (msg.reply_to.sender?.name || 'Member') }}
                                            </span>

                                            <span class="fs-8 text-truncate" 
                                                :class="msg.sender_id === currentUser?.id ? 'text-white text-opacity-75' : 'text-gray-600'">
                                                {{ msg.reply_to.message || (msg.reply_to.type === 'image' ? 'Foto' : 'File') }}
                                            </span>
                                        </div>

                                        <div v-if="msg.type === 'image'" class="mb-2 position-relative">
                                            <img :src="msg.file_path.startsWith('blob') ? msg.file_path : `/storage/${msg.file_path}`" 
                                                class="rounded w-100 cursor-pointer border" 
                                                @click="openLightbox(msg.file_path)" 
                                                style="max-height: 250px; object-fit: cover;">
                                            <button @click.stop="downloadAttachment(msg)" class="btn btn-icon btn-sm btn-dark position-absolute bottom-0 end-0 m-2 shadow-sm" style="width: 30px; height: 30px; background-color: rgba(0,0,0,0.6);"><i class="fas fa-download fs-8 text-white"></i></button>
                                        </div>

                                        <div v-else-if="msg.type === 'file'" class="d-flex align-items-center p-2 rounded mb-2" :class="msg.sender_id === currentUser?.id ? 'bg-white bg-opacity-20' : 'bg-light'">
                                            <div class="symbol symbol-35px me-2">
                                                <span class="symbol-label fw-bold text-primary bg-white">FILE</span>
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
                                                <span v-if="isTempId(msg.id)">
                                                    <i class="fas fa-clock text-white text-opacity-50 fs-9"></i>
                                                </span>
                                                <span v-else>
                                                    <i class="fas fa-check text-white text-opacity-50 fs-9"></i>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="position-absolute top-0 end-0 mt-n2 me-n2 d-flex gap-1" 
                                            style="opacity: 0; transition: opacity 0.2s;" 
                                            onmouseover="this.style.opacity=1" 
                                            onmouseout="this.style.opacity=0">
                                            <button @click="setReply(msg)" class="btn btn-sm btn-icon btn-circle btn-white shadow w-20px h-20px" title="Balas">
                                                <i class="fas fa-reply fs-9 text-warning"></i>
                                            </button>
                                            <button v-if="msg.sender_id === currentUser?.id" @click="openDeleteModal(msg)" class="btn btn-sm btn-icon btn-circle btn-white shadow w-20px h-20px" title="Hapus">
                                                <i class="fas fa-trash fs-9 text-danger"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="typingUsers.length > 0" class="d-flex justify-content-start mb-5 ps-12">
                            <div class="text-muted fs-8 fst-italic">
                                <span v-if="typingUsers.length === 1">{{ typingUsers[0] }} sedang mengetik...</span>
                                <span v-else>{{ typingUsers.length }} orang sedang mengetik...</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="replyingTo" class="px-4 py-2 bg-light border-top d-flex justify-content-between align-items-center">
                        <div class="d-flex flex-column border-start border-4 border-primary ps-2">
                            <span class="text-primary fw-bold small">
                                Balas ke: {{ replyingTo.sender_id === currentUser.id ? 'Anda' : replyingTo.sender?.name }}
                            </span>
                            <span class="text-muted small text-truncate" style="max-width: 300px;">
                                {{ replyingTo.message || 'Lampiran' }}
                            </span>
                        </div>
                        <button @click="cancelReply" class="btn btn-sm btn-icon btn-light-danger">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <transition name="fade">
                        <button v-if="showScrollButton" @click="scrollToBottom" class="btn btn-primary btn-icon shadow-sm rounded-circle position-absolute" style="bottom: 100px; right: 30px; z-index: 10; width: 30px; height: 30px;">
                            <i class="fas fa-arrow-down fs-4"></i>
                        </button>
                    </transition>

                    <div class="card-footer pt-4 pb-4" style="min-height: 80px;">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-sm btn-icon btn-active-light-primary me-2" @click="triggerFileUpload"><KTIcon icon-name="paper-clip" icon-class="fs-3" /></button>
                            <input type="file" ref="fileInput" class="d-none" @change="sendMessage" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" />
                            <input v-model="newMessage" @keyup.enter="sendMessage" type="text" @input="handleTyping" class="form-control form-control-solid me-3" placeholder="Ketik pesan..." />
                            <button class="btn btn-primary btn-icon" @click="sendMessage"><KTIcon icon-name="send" icon-class="fs-2" /></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="isCreateGroupOpen" class="modal-overlay">
        <div class="modal-content-wrapper bg-white rounded shadow p-0 overflow-hidden" style="max-width: 500px; width: 100%;">
            <GroupForm @close="isCreateGroupOpen = false" @refresh="fetchGroups" />
        </div>
    </div>

    <div v-if="isEditGroupOpen" class="modal-overlay">
        <div class="modal-content-wrapper bg-white rounded shadow p-0 overflow-hidden" style="max-width: 500px; width: 100%;">
            <GroupEdit v-if="isEditGroupOpen" :groupId="groupIdToEdit" :title="editModalTitle"  @close="isEditGroupOpen = false" @updated="handleGroupUpdated" />
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

    <div v-if="isInfoModalOpen" class="modal-overlay" @click.self="isInfoModalOpen = false">
        <div class="modal-content bg-white rounded shadow p-0 overflow-hidden" style="max-width: 400px; width: 100%;">
            <div class="modal-header p-4 border-bottom d-flex justify-content-between align-items-center">
                <h3 class="fw-bold m-0">Info Grup</h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" @click="isInfoModalOpen = false">
                    <i class="fas fa-times fs-2"></i>
                </div>
            </div>
            <div class="modal-body p-5 text-center">
                <div class="symbol symbol-100px symbol-circle mb-4">
                    <img :src="activeGroup?.photo ? `/storage/${activeGroup.photo}` : '/media/avatars/group-blank.png'" alt="image" style="object-fit: cover;" />
                </div>
                
                <h4 class="fw-bold text-gray-800">{{ activeGroup?.name }}</h4>
                <div class="text-muted fs-7">{{ activeGroup?.members_count }} Anggota</div>
                
                <div class="text-start bg-light rounded p-4 mt-4 overflow-auto" style="max-height: 200px;">
                    <h6 class="text-gray-600 mb-3">Anggota</h6>
                    <div v-for="member in activeGroup?.members" :key="member.id" class="d-flex align-items-center mb-2">
                        <div class="symbol symbol-30px symbol-circle me-2">
                             <img :src="member.photo ? `/storage/${member.photo}` : '/media/avatars/blank.png'">
                        </div>
                        <span class="fs-7 fw-bold text-gray-800">{{ member.name }}</span>
                        <span v-if="member.is_admin" class="badge badge-light-success ms-auto fs-9">Admin</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Gunakan style yang sama persis dengan Index.vue untuk konsistensi */
.scroll-y {
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
}

.chat-body-custom {
    height: calc(100vh - 265px);
    overflow-y: auto;
    background-color: #f9f9f9;
    scroll-behavior: smooth;
}

.receiver-bubble {
    background-color: #ffffff;
    color: #3f4254;
}

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
}

.lightbox-overlay {
    background-color: rgba(0, 0, 0, 0.9);
    z-index: 10000;
}

[data-bs-theme="dark"] .chat-body-custom {
    background-color: #151521 !important;
}

[data-bs-theme="dark"] .receiver-bubble {
    background-color: #2b2b40 !important;
    color: #ffffff !important;
}
</style>