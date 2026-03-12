<script setup lang="ts">
import { ref, onMounted, nextTick, computed, onUnmounted, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import { format, isToday, isYesterday, isSameDay } from "date-fns";
import { id } from "date-fns/locale";
import {
    Phone,
    Video,
    Users,
    Loader2,
    CheckCheck,
    ArrowLeft,
} from "lucide-vue-next";
import { useGlobalChatStore } from "@/stores/globalChat";
import { onBeforeRouteLeave } from "vue-router";
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
    limitToLast,
} from "firebase/database";
import { onAuthStateChanged } from "firebase/auth";

// --- Group Call Import ---
import { useCallStore } from "@/stores/callStore";
import { useVoiceGroupCall } from "@/composables/useVoiceGroupCall";
import VoiceGroupCallingModal from "@/components/call/voice/VoiceGroupCallingModal.vue";
import VoiceGroupIncomingModal from "@/components/call/voice/VoiceGroupIncomingModal.vue";
import VoiceGroupCallModal from "@/components/call/voice/VoiceGroupCallModal.vue";
import VoiceGroupFloating from "@/components/call/voice/VoiceGroupFloating.vue";

// --- STATE UTAMA ---
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const groups = ref<any[]>([]);
const messages = ref<any[]>([]);
const activeGroup = ref<any>(null);
const newMessage = ref("");
const isLoadingGroups = ref(false);
const isLoadingMessages = ref(false);
const globalChatStore = useGlobalChatStore();
// Refs DOM
const chatBodyRef = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

// --- STATE MODAL ---
const isCreateGroupOpen = ref(false);
const isEditGroupOpen = ref(false);
const sidebarUnsubscribes: Record<string, Function> = {};
const pageLoadTime = Date.now();
const groupIdToEdit = ref<string | number | undefined>(undefined);
const editModalTitle = ref("Edit Info Grup");
const isDeleteModalOpen = ref(false);
const messageInputRef = ref<HTMLTextAreaElement | null>(null);
const isLeaveGroupModalOpen = ref(false);
const isClearChatModalOpen = ref(false);
const messageToDelete = ref<any>(null);
const isLightboxOpen = ref(false);
const activeLightboxUrl = ref("");
const showScrollButton = ref(false);
const replyingTo = ref<any>(null);
const isHeaderMenuOpen = ref(false);
const isInfoModalOpen = ref(false);
const searchQuery = ref("");
const chatDrafts = ref<Record<string | number, string>>({});
const showMobileChat = ref(false);
const openMessageMenuId = ref<number | string | null>(null);

// Group Call State
const callStore = useCallStore();
const {
    startGroupVoiceCall,
    answerGroupVoiceCall,
    rejectGroupVoiceCall,
    leaveGroupVoiceCall,
} = useVoiceGroupCall();

// Typing State untuk Group
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
                behavior: "smooth",
            });
        }
    });
};

const scrollToMessage = (id: number) => {
    const el = document.getElementById("msg-" + id);
    if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "center" });
        el.classList.add("bg-light-warning");
        setTimeout(() => el.classList.remove("bg-light-warning"), 1000);
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
    return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
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
    try {
        isLoadingGroups.value = true;
        const response = await axios.get("/chat/groups");
        groups.value = response.data.map((g: any) => ({
            ...g,
            unread_count: Number(g.unread_count) || 0,
        }));
    } catch (error) {
        console.error("Gagal memuat grup:", error);
    } finally {
        isLoadingGroups.value = false;
    }
};

const selectGroup = async (group: any) => {
    // 1. Cek jika grup yang diklik sama dengan yang aktif (hindari reload)
    if (activeGroup.value?.id === group.id) return;

    // 2. Simpan Draft Pesan Grup sebelumnya (jika ada)
    if (activeGroup.value) {
        chatDrafts.value[activeGroup.value.id] = newMessage.value;
    }

    // 3. Reset State UI
    showMobileChat.value = true;
    activeGroup.value = group;
    messages.value = [];
    globalChatStore.setActiveGroup(group.id);
    try {
        const fullGroupResponse = await axios.get(`/chat/groups/${group.id}`);
        if (fullGroupResponse.data.success) {
            activeGroup.value = fullGroupResponse.data.data;
        } else {
            activeGroup.value = fullGroupResponse.data;
        }
    } catch (err) {
        console.error("Gagal memuat detail grup", err);
    }

    newMessage.value = chatDrafts.value[String(group.id)] || "";
    const idx = groups.value.findIndex((g) => g.id === group.id);
    if (idx !== -1) {
        groups.value[idx].unread_count = 0;
    }
    try {
        axios.post(`/chat/groups/${group.id}/read`);
    } catch (error) {
        console.error("Gagal update status read:", error);
    }
    setupGroupListener(group.id);
    await getMessages(group.id);

    await nextTick();
    if (messageInputRef.value) {
        messageInputRef.value.focus();
    }
    scrollToBottom();
};

const closeMobileChat = () => {
    showMobileChat.value = false;
    activeGroup.value = null;
};

onBeforeRouteLeave((to, from, next) => {
    globalChatStore.setActiveGroup(null);
    next();
});

const filteredGroups = computed(() => {
    1;
    if (!searchQuery.value) {
        return groups.value;
    }

    const query = searchQuery.value.toLowerCase();

    return groups.value.filter((group: any) => {
        const name = (group.name || "").toLowerCase();

        return name.includes(query);
    });
});

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
    let tempType = "text";
    if (file) {
        if (file.type.startsWith("image/")) tempType = "image";
        else if (file.type.startsWith("video/"))
            tempType = "video"; // Tambahkan ini
        else tempType = "file";
    }

    // Structure message object
    const tempMessage = {
        id: tempId,
        sender_id: currentUser.value?.id,
        group_id: activeGroup.value.id,
        message: textContent,
        file_path: file ? URL.createObjectURL(file) : null,
        type: tempType,
        file_size: file ? file.size : 0,
        file_name: file ? file.name : null,
        created_at: new Date().toISOString(),
        read_at: null,
        reply_to: replyingTo.value ? replyingTo.value : null,
        sender: {
            name: currentUser.value?.name,
            photo: currentUser.value?.photo,
        },
        is_temp: true,
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
    if (activeGroup.value) {
        delete chatDrafts.value[activeGroup.value.id];
    }
    if (fileInput.value) fileInput.value.value = "";

    try {
        // Endpoint send group
        const response = await axios.post("/chat/group/send", formData, {
            headers: { "Content-Type": "multipart/form-data" },
        });
        const realMessage = response.data.data
            ? response.data.data
            : response.data;
        const index = messages.value.findIndex((m) => m.id === tempId);
        if (index !== -1) {
            messages.value[index] = realMessage;
        }

        refreshGroupOrder(activeGroup.value.id);
    } catch (error) {
        console.error("Gagal kirim pesan grup", error);
        toast.error("Gagal mengirim pesan");
        messages.value = messages.value.filter((m) => m.id !== tempId);
        replyingTo.value = tempReply;
    }
};

const groupMembersHeader = computed(() => {
    if (
        !activeGroup.value ||
        !activeGroup.value.members ||
        activeGroup.value.members.length === 0
    ) {
        return "Memuat anggota...";
    }

    const names = activeGroup.value.members.map((m: any) =>
        m.id === currentUser.value?.id ? "Anda" : m.name.split(" ")[0]
    );

    const limit = 5;
    if (names.length <= limit) {
        return names.join(", ");
    } else {
        const visibleNames = names.slice(0, limit).join(", ");
        const remaining = names.length - limit;
        return `${visibleNames}, +${remaining} lainnya`;
    }
});

const setReply = (msg: any) => {
    replyingTo.value = msg;
    nextTick(() => {
        const input = document.querySelector("textarea"); // atau input text
        if (input) (input as HTMLElement).focus();
    });
};

const cancelReply = () => {
    replyingTo.value = null;
};

const isTempId = (id: any) => {
    return typeof id === "number" && id > 1000000000000;
};

const triggerFileUpload = () => {
    fileInput.value?.click();
};

const STORAGE_URL = import.meta.env.VITE_API_URL || window.location.origin;

const getFileUrl = (path: string) => {
    if (!path) return "";
    if (path.startsWith("blob:")) return path;
    if (path.startsWith("http")) return path;
    return `/storage/${path}`;
};

const downloadAttachment = (msg: any) => {
    // Cek jika path kosong
    if (!msg.file_path) return;

    let finalUrl = "";
    const rawPath = msg.file_path;

    const baseUrl = STORAGE_URL.endsWith("/")
        ? STORAGE_URL.slice(0, -1)
        : STORAGE_URL;

    if (rawPath.startsWith("http")) {
        finalUrl = rawPath;
    } else {
        let cleanPath = rawPath.startsWith("/")
            ? rawPath.substring(1)
            : rawPath;

        if (cleanPath.startsWith("storage/")) {
            finalUrl = `${baseUrl}/${cleanPath}`;
        } else {
            finalUrl = `${baseUrl}/storage/${cleanPath}`;
        }
    }

    const link = document.createElement("a");
    link.href = finalUrl;
    link.target = "_blank";
    link.setAttribute("download", msg.file_name || "download");

    document.body.appendChild(link);
    link.click();

    document.body.removeChild(link);
};

const refreshGroupOrder = (groupId: any) => {
    const idx = groups.value.findIndex((c) => c.id === groupId);
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

const confirmDelete = async (type: "me" | "everyone") => {
    if (!messageToDelete.value) return;
    const isForEveryone = type === "everyone";

    try {
        await axios.delete(`/chat/group/delete/${messageToDelete.value.id}`, {
            data: {
                delete_for_everyone: isForEveryone,
            },
        });

        messages.value = messages.value.filter(
            (m) => m.id !== messageToDelete.value.id
        );

        toast.success(
            isForEveryone
                ? "Pesan dihapus untuk semua orang"
                : "Pesan dihapus untuk saya"
        );
        closeDeleteModal();
    } catch (error: any) {
        console.error("Error delete:", error);
        const errorMsg =
            error.response?.data?.message || "Gagal menghapus pesan";
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

const openClearChatModal = () => {
    isHeaderMenuOpen.value = false;
    if (!activeGroup.value) return;
    isClearChatModalOpen.value = true;
};

const processClearChat = async () => {
    if (!activeGroup.value) return;

    try {
        await axios.delete(`/chat/group/${activeGroup.value.id}/clear`);
        messages.value = [];

        isClearChatModalOpen.value = false;
        toast.success("Riwayat chat berhasil dibersihkan");
    } catch (error) {
        console.error("Gagal clear chat", error);
        toast.error("Gagal membersihkan chat");
        isClearChatModalOpen.value = false;
    }
};

const openEditGroupModal = () => {
    isHeaderMenuOpen.value = false;
    if (!activeGroup.value) return;
    groupIdToEdit.value = activeGroup.value.id;
    editModalTitle.value = "Edit Grup";
    isEditGroupOpen.value = true;
};

// ✅ KODE YANG DIPERBAIKI
const handleStartVoiceGroupCall = async () => {
    if (!activeGroup.value) {
        toast.error("Tidak ada grup yang dipilih.");
        return;
    }

    // 1. Debugging: Cek apa isi sebenarnya dari activeGroup
    console.log("Data activeGroup:", activeGroup.value);

    // 2. Amankan dengan optional chaining (?.) dan beri array kosong jika undefined
    // PERHATIKAN: Ganti '.users' dengan key yang benar dari database/backend kamu
    // (Bisa jadi .members, .participants, dll)
    const groupMembers =
        activeGroup.value.users || activeGroup.value.members || [];

    const participantIds = groupMembers
        .filter((u: any) => u.id !== currentUser.value?.id) // Pastikan ID kita tidak ikut ditelepon
        .map((u: any) => u.id);

    if (participantIds.length === 0) {
        toast.warning("Tidak ada peserta lain di dalam grup untuk ditelepon.");
        return;
    }

    // Panggil fungsi composable-nya
    await startGroupVoiceCall(activeGroup.value.id, participantIds);
};

// --- [TS FIX] Format Data agar sesuai dengan Modal ---
const formattedGroupParticipants = computed(() => {
    return callStore.groupParticipants.map((p: any) => ({
        id: p.user_id || p.id,
        name: p.user?.name || "Unknown",
        avatar: p.user?.photo || p.user?.profile_photo_url || "",
        status: p.status,
    }));
});

// --- [TS FIX] Cast incomingCall ke any untuk mengakses custom properti ---
const incomingCallAsAny = computed(() => callStore.incomingCall as any);

const handleGroupUpdated = (updatedGroup: any) => {
    if (activeGroup.value && activeGroup.value.id === updatedGroup.id) {
        activeGroup.value.name = updatedGroup.name;
    }
    const index = groups.value.findIndex((g) => g.id === updatedGroup.id);
    if (index !== -1) {
        groups.value[index].name = updatedGroup.name;
    }
};

const toggleHeaderMenu = () => {
    isHeaderMenuOpen.value = !isHeaderMenuOpen.value;
};

const openInfoModal = () => {
    isHeaderMenuOpen.value = false;
    isInfoModalOpen.value = true;
};

const handleExitGroup = () => {
    if (!activeGroup.value) return;
    isHeaderMenuOpen.value = false;
    isLeaveGroupModalOpen.value = true;
};

const processLeaveGroup = async () => {
    if (!activeGroup.value) return;

    try {
        await axios.post(`/chat/group/${activeGroup.value.id}/leave`);

        activeGroup.value = null;
        messages.value = [];
        isLeaveGroupModalOpen.value = false;

        await fetchGroups();
        toast.success("Berhasil keluar grup");
    } catch (error) {
        toast.error("Gagal keluar grup");
        isLeaveGroupModalOpen.value = false;
    }
};

// --- TYPING LOGIC GROUP ---
const handleTyping = () => {
    if (!activeGroup.value || !currentUser.value) return;

    // Path: typing_status_groups/{groupId}/{userId}
    const myTypingRef = firebaseRef(
        db,
        `typing_status_groups/${activeGroup.value.id}/${currentUser.value.id}`
    );

    set(myTypingRef, {
        name: currentUser.value.name,
        is_typing: true,
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
            if (typingUsers.value.length > 0) {
                setTimeout(() => scrollToBottom(), 100);
            }
        }
    });
};

const toggleMessageMenu = (id: number | string) => {
    if (openMessageMenuId.value === id) {
        openMessageMenuId.value = null;
    } else {
        openMessageMenuId.value = id;
    }
};

const handleMessageAction = (action: Function, msg: any) => {
    action(msg);
    openMessageMenuId.value = null;
};

const handleEscKey = (e: KeyboardEvent) => {
    if (e.key === "Escape") {
        if (isLightboxOpen.value) {
            closeLightbox();
            return;
        }
        if (
            isCreateGroupOpen.value ||
            isEditGroupOpen.value ||
            isDeleteModalOpen.value ||
            isInfoModalOpen.value ||
            isHeaderMenuOpen.value
        ) {
            if (isHeaderMenuOpen.value) isHeaderMenuOpen.value = false;
            return;
        }
        if (activeGroup.value) {
            activeGroup.value = null;
        }
        if (isLeaveGroupModalOpen.value) {
            isLeaveGroupModalOpen.value = false;
            return;
        }
        if (isClearChatModalOpen.value) {
            isClearChatModalOpen.value = false;
            return;
        }
    }
};

const setupSidebarListeners = () => {
    // Kurangi 2 detik untuk toleransi waktu server vs client
    const timeThreshold = pageLoadTime - 2000;

    groups.value.forEach((group) => {
        // Cek duplikasi listener
        if (sidebarUnsubscribes[group.id]) return;

        const messagesRef = firebaseRef(db, `group_messages/${group.id}`);
        const q = query(messagesRef, limitToLast(1));

        const unsub = onChildAdded(q, (snapshot) => {
            const msg = snapshot.val();
            if (!msg) return;

            // 1. Cari Index Grup di Array (Bukan find object biasa)
            const index = groups.value.findIndex((g) => g.id == group.id);
            if (index === -1) return;

            // 2. Filter Pesan Lama (History)
            const msgTime = new Date(msg.created_at).getTime();
            if (msgTime <= timeThreshold) return;

            // --- UPDATE REALTIME ---
            groups.value[index].last_message_preview =
                msg.type === "text"
                    ? msg.message
                    : msg.type === "image"
                    ? "📷 Foto"
                    : "📎 Berkas";
            groups.value[index].last_message_time = msg.created_at;
            const isMyMessage = msg.sender_id === authStore.user.id;
            const isGroupOpen =
                activeGroup.value && activeGroup.value.id === group.id;

            if (!isMyMessage && !isGroupOpen) {
                const currentCount =
                    Number(groups.value[index].unread_count) || 0;

                // Tambah 1
                const newCount = currentCount + 1;

                groups.value[index].unread_count = newCount;
                const movedGroup = groups.value.splice(index, 1)[0];
                groups.value.unshift(movedGroup);
            }
        });

        sidebarUnsubscribes[group.id] = unsub;
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

        if (activeGroup.value && activeGroup.value.last_cleared_at) {
            const msgTime = new Date(incomingMsg.created_at).getTime();
            const clearTime = new Date(
                activeGroup.value.last_cleared_at
            ).getTime();
            if (msgTime <= clearTime) {
                return;
            }
        }

        if (incomingMsg.type === "delete_notify") {
            messages.value = messages.value.filter(
                (m) => m.id !== incomingMsg.target_message_id
            );
            return;
        }

        const realExists = messages.value.some(
            (m: any) => m.id === incomingMsg.id && !m.is_temp
        );
        if (realExists) return;
        if (incomingMsg.sender_id === currentUser.value.id) {
            const tempIndex = messages.value.findIndex((m) => {
                if (!m.is_temp) return false;

                if (m.type !== incomingMsg.type) return false;

                const localCaption = m.message || "";
                const serverCaption = incomingMsg.message || "";

                return localCaption === serverCaption;
            });

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

watch(
    messages,
    () => {
        scrollToBottom();
    },
    { deep: true }
);

onMounted(async () => {
    await fetchGroups();
    setupSidebarListeners();
    if (currentUser.value) {
        onAuthStateChanged(auth, (firebaseUser) => {
            if (!firebaseUser) {
            }
        });
    }

    window.addEventListener("keydown", handleEscKey);
});

onUnmounted(() => {
    if (unsubscribeGroupChats) unsubscribeGroupChats();
    if (groupTypingListenerOff) groupTypingListenerOff();
    if (activeGroup.value && currentUser.value) {
        const myRef = firebaseRef(
            db,
            `typing_status_groups/${activeGroup.value.id}/${currentUser.value.id}`
        );
        set(myRef, null);
    }
    globalChatStore.setActiveGroup(null);
    Object.values(sidebarUnsubscribes).forEach((unsub) => unsub());
    window.removeEventListener("keydown", handleEscKey);
});
</script>

<template>
    <div class="d-flex flex-column flex-lg-row h-100">
        <div
            class="flex-column flex-lg-row-auto w-100 w-lg-350px w-xl-400px mb-10 mb-lg-0"
            :class="showMobileChat ? 'd-none d-lg-block' : 'd-block'"
        >
            <div class="card card-flush h-100">
                <div class="card-header pt-7">
                    <div class="d-flex align-items-center w-100">
                        <form
                            class="w-100 position-relative me-3"
                            autocomplete="off"
                        >
                            <KTIcon
                                icon-name="magnifier"
                                icon-class="fs-2 text-lg-1 text-gray-500 position-absolute top-50 ms-5 translate-middle-y"
                            />
                            <input
                                type="text"
                                class="form-control form-control-solid px-15"
                                placeholder="Cari grup..."
                                v-model="searchQuery"
                            />
                        </form>
                        <button
                            class="btn btn-sm btn-light-primary fw-bold"
                            @click="openCreateGroupModal"
                        >
                            <KTIcon icon-name="plus" icon-class="fs-2" />
                        </button>
                    </div>
                </div>

                <div class="card-body pt-5">
                    <div
                        class="scroll-y me-n5 pe-5 h-200px h-lg-auto"
                        style="max-height: 60vh"
                    >
                        <div v-if="isLoadingGroups" class="text-center mt-5">
                            <span
                                class="spinner-border spinner-border-sm text-primary"
                            ></span>
                        </div>
                        <div
                            v-for="group in filteredGroups"
                            :key="group.id"
                            @click="selectGroup(group)"
                            class="d-flex align-items-center p-3 mb-2 rounded cursor-pointer contact-item position-relative overflow-hidden"
                            :class="{
                                'bg-light-primary':
                                    activeGroup?.id === group.id,
                            }"
                        >
                            <div class="d-flex align-items-center">
                                <div
                                    class="symbol symbol-40px symbol-circle me-3"
                                >
                                    <img
                                        :src="
                                            group.photo
                                                ? `/storage/${group.photo}`
                                                : '/media/avatars/group-blank.png'
                                        "
                                        alt="grup"
                                    />
                                </div>
                                <div
                                    class="d-flex flex-column flex-grow-1 overflow-hidden"
                                >
                                    <div
                                        class="d-flex justify-content-between align-items-center"
                                    >
                                        <div
                                            class="d-flex align-items-center overflow-hidden"
                                        >
                                            <span
                                                class="fw-bold text-gray-800 text-hover-primary fs-6 text-truncate"
                                            >
                                                {{ group.name }}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        class="d-flex align-items-center justify-content-between"
                                    >
                                        <span
                                            class="text-muted fs-7 text-truncate pe-2"
                                            style="max-width: 150px"
                                        >
                                            <span
                                                v-if="
                                                    chatDrafts[group.id]?.trim()
                                                "
                                                class="text-danger fst-italic"
                                            >
                                                <span class="me-1">Draft:</span>
                                                <span class="text-gray-800">{{
                                                    chatDrafts[group.id]
                                                }}</span>
                                            </span>

                                            <span v-else>
                                                {{
                                                    group.last_message ||
                                                    group.members_count +
                                                        " Anggota"
                                                }}
                                            </span>
                                        </span>

                                        <span
                                            v-if="group.unread_count > 0"
                                            class="badge badge-circle badge-primary w-20px h-20px fs-9"
                                        >
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

        <Teleport to="body">
            <VoiceGroupFloating />

            <VoiceGroupCallingModal
                v-if="
                    callStore.isGroupCall &&
                    callStore.currentCall &&
                    callStore.callStatus === 'calling' &&
                    !callStore.isMinimized
                "
                :groupName="activeGroup?.name || 'Group Call'"
                :groupPhoto="activeGroup?.photo || activeGroup?.avatar || ''"
                :participants="formattedGroupParticipants"
                :callStatus="callStore.callStatus"
                @cancel="leaveGroupVoiceCall(callStore.currentCall.id)"
            />

            <VoiceGroupIncomingModal
                v-if="callStore.incomingCall && callStore.incomingCall.isGroup"
                :groupName="incomingCallAsAny.groupName || 'Group Call'"
                :groupPhoto="incomingCallAsAny.groupAvatar || ''"
                :inviterName="callStore.incomingCall.caller?.name"
                :participants="incomingCallAsAny.participants || []"
                @accept="() => answerGroupVoiceCall(callStore.incomingCall!.id)"
                @reject="() => rejectGroupVoiceCall(callStore.incomingCall!.id)"
            />

            <VoiceGroupCallModal
                v-if="
                    callStore.isGroupCall &&
                    callStore.currentCall &&
                    callStore.callStatus === 'ongoing' &&
                    !callStore.isMinimized
                "
            />

            <!-- <div v-if="callStore.isGroupCall" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.85); color: #00ff00; padding: 15px; border-radius: 8px; z-index: 999999; font-family: monospace;">
            <b>🛠️ CALL STATE DEBUGGER</b><br/>
            isGroupCall: {{ callStore.isGroupCall }}<br/>
            Status: {{ callStore.callStatus }}<br/>
            CurrentCall: {{ !!callStore.currentCall }}<br/>
            isMinimized: {{ callStore.isMinimized }}
        </div> -->
        </Teleport>

        <div
            class="flex-lg-row-fluid ms-lg-7 ms-xl-10"
            style="min-width: 0"
            :class="showMobileChat ? 'd-block' : 'd-none d-lg-block'"
        >
            <div class="card h-100 overflow-hidden" id="kt_chat_messenger">
                <div
                    v-if="!activeGroup"
                    class="card-body d-flex flex-column justify-content-center align-items-center h-100"
                >
                    <div class="symbol symbol-100px mb-5">
                        <Users class="w-100px h-100px text-gray-300" />
                    </div>
                    <h3 class="fw-bold text-gray-800">Grup Chat</h3>
                    <p class="text-muted">Pilih grup untuk mulai berdiskusi.</p>
                </div>

                <div v-else class="d-flex flex-column h-100">
                    <div
                        class="card-header d-flex align-items-center p-3 border-bottom"
                        style="min-height: 70px"
                    >
                        <div class="d-flex align-items-center flex-grow-1">
                            <button
                                class="btn btn-icon btn-sm btn-active-light-primary d-lg-none me-3"
                                @click="closeMobileChat"
                            >
                                <ArrowLeft
                                    class="w-20px h-20px text-gray-700"
                                />
                            </button>

                            <div class="symbol symbol-40px symbol-circle me-3">
                                <img
                                    :src="
                                        activeGroup.photo
                                            ? `/storage/${activeGroup.photo}`
                                            : '/media/avatars/group-blank.png'
                                    "
                                    alt="image"
                                />
                            </div>

                            <div class="d-flex flex-column">
                                <span class="fw-bold text-gray-800 fs-6">
                                    {{ activeGroup.name }}
                                </span>
                                <span
                                    class="text-muted fs-8 d-block text-truncate"
                                    style="max-width: 100%; cursor: pointer"
                                    :title="activeGroup?.members?.map((m:any) => m.name).join(', ')"
                                >
                                    {{ groupMembersHeader }}
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button
                                @click="handleStartVoiceGroupCall"
                                class="btn btn-icon btn-sm text-gray-500"
                            >
                                <Phone class="w-20px h-20px" />
                            </button>
                            <button class="btn btn-icon btn-sm text-gray-500">
                                <Video class="w-20px h-20px" />
                            </button>

                            <div class="position-relative">
                                <button
                                    class="btn btn-icon btn-sm text-gray-500"
                                    @click.stop="toggleHeaderMenu"
                                >
                                    <i class="fas fa-ellipsis-v fs-4"></i>
                                </button>

                                <div
                                    v-if="isHeaderMenuOpen"
                                    class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-bold w-200px py-3 show position-absolute end-0 mt-2 shadow bg-white"
                                    style="z-index: 105"
                                >
                                    <div class="menu-item px-3">
                                        <a
                                            href="#"
                                            class="menu-link px-3"
                                            @click.prevent="openInfoModal"
                                        >
                                            <i
                                                class="fas fa-info-circle me-2"
                                            ></i>
                                            Info Grup
                                        </a>
                                    </div>
                                    <div class="menu-item px-3">
                                        <a
                                            href="#"
                                            class="menu-link px-3"
                                            @click.prevent="openEditGroupModal"
                                        >
                                            <i class="fas fa-edit me-2"></i>
                                            Edit Grup
                                        </a>
                                    </div>
                                    <div class="menu-item px-3">
                                        <a
                                            href="#"
                                            class="menu-link px-3"
                                            @click.prevent="openClearChatModal"
                                        >
                                            <i
                                                class="fas fa-trash-alt me-2"
                                            ></i>
                                            Bersihkan Chat
                                        </a>
                                    </div>
                                    <div class="separator my-2"></div>
                                    <div class="menu-item px-3">
                                        <a
                                            href="#"
                                            class="menu-link px-3 text-danger"
                                            @click.prevent="handleExitGroup"
                                        >
                                            <i
                                                class="fas fa-sign-out-alt me-2"
                                            ></i>
                                            Keluar Grup
                                        </a>
                                    </div>
                                </div>
                                <div
                                    v-if="isHeaderMenuOpen"
                                    @click="isHeaderMenuOpen = false"
                                    class="position-fixed top-0 start-0 w-100 h-100"
                                    style="z-index: 104"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="card-body p-4 chat-body-custom"
                        ref="chatBodyRef"
                        @scroll="handleScroll"
                    >
                        <div
                            v-if="isLoadingMessages"
                            class="d-flex justify-content-center align-items-center h-100"
                        >
                            <span class="spinner-border text-primary"></span>
                        </div>
                        <div
                            v-else
                            v-for="(msg, index) in messages"
                            :key="msg.id"
                            :id="'msg-' + msg.id"
                        >
                            <div
                                v-if="shouldShowDateDivider(index)"
                                class="d-flex justify-content-center my-4"
                            >
                                <span
                                    class="badge badge-light-primary text-primary px-3 py-2 rounded-pill shadow-sm fs-9 fw-bold border"
                                >
                                    {{ formatDateLabel(msg.created_at) }}
                                </span>
                            </div>

                            <div
                                class="d-flex mb-4"
                                :class="
                                    msg.sender_id === currentUser?.id
                                        ? 'justify-content-end'
                                        : 'justify-content-start'
                                "
                            >
                                <div
                                    v-if="msg.sender_id !== currentUser?.id"
                                    class="me-3 d-flex flex-column align-items-end justify-content-end pb-1"
                                >
                                    <div
                                        class="symbol symbol-35px symbol-circle"
                                    >
                                        <img
                                            :src="
                                                msg.sender?.photo
                                                    ? `/storage/${msg.sender.photo}`
                                                    : '/media/avatars/blank.png'
                                            "
                                            alt="pic"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="d-flex flex-column"
                                    :class="
                                        msg.sender_id === currentUser?.id
                                            ? 'align-items-end'
                                            : 'align-items-start'
                                    "
                                >
                                    <div
                                        class="p-3 rounded shadow-sm position-relative group-hover"
                                        :class="
                                            msg.sender_id === currentUser?.id
                                                ? 'bg-primary text-white rounded-bottom-end-0'
                                                : 'receiver-bubble rounded-bottom-start-0'
                                        "
                                        style="
                                            max-width: 320px;
                                            min-width: 120px;
                                        "
                                    >
                                        <div
                                            v-if="
                                                msg.sender_id !==
                                                currentUser?.id
                                            "
                                            class="fw-bold mb-1 small text-primary ms-1"
                                        >
                                            {{
                                                msg.sender?.name ||
                                                "Anggota Group"
                                            }}
                                        </div>

                                        <div
                                            v-if="msg.reply_to"
                                            class="mb-2 p-2 rounded border-start border-4 cursor-pointer d-flex flex-column"
                                            :class="
                                                msg.sender_id ===
                                                currentUser?.id
                                                    ? 'bg-black bg-opacity-10 border-white'
                                                    : 'bg-secondary bg-opacity-25 border-primary'
                                            "
                                            @click="
                                                scrollToMessage(msg.reply_to.id)
                                            "
                                        >
                                            <span
                                                class="fw-bold fs-8 mb-1"
                                                :class="
                                                    msg.sender_id ===
                                                    currentUser?.id
                                                        ? 'text-white'
                                                        : 'text-primary'
                                                "
                                            >
                                                {{
                                                    msg.reply_to.sender_id ===
                                                    currentUser?.id
                                                        ? "Anda"
                                                        : msg.reply_to.sender
                                                              ?.name ||
                                                          "Anggota Lain"
                                                }}
                                            </span>

                                            <span
                                                class="fs-8 text-truncate"
                                                :class="
                                                    msg.sender_id ===
                                                    currentUser?.id
                                                        ? 'text-white text-opacity-75'
                                                        : 'text-gray-600'
                                                "
                                            >
                                                <i
                                                    v-if="
                                                        msg.reply_to.type ===
                                                        'image'
                                                    "
                                                    class="fas fa-camera me-1"
                                                ></i>
                                                <i
                                                    v-else-if="
                                                        msg.reply_to.type ===
                                                        'video'
                                                    "
                                                    class="fas fa-video me-1"
                                                ></i>
                                                <i
                                                    v-else-if="
                                                        msg.reply_to.type ===
                                                        'file'
                                                    "
                                                    class="fas fa-file me-1"
                                                ></i>
                                                {{
                                                    msg.reply_to.message ||
                                                    (msg.reply_to.type ===
                                                    "text"
                                                        ? ""
                                                        : "Lampiran")
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            v-if="msg.type === 'video'"
                                            class="mb-2"
                                        >
                                            <div
                                                class="ratio ratio-16x9 rounded overflow-hidden bg-black mb-1 border border-secondary border-opacity-25"
                                                style="min-width: 260px"
                                            >
                                                <video
                                                    controls
                                                    preload="metadata"
                                                    class="w-100 h-100 object-fit-contain"
                                                >
                                                    <source
                                                        :src="
                                                            getFileUrl(
                                                                msg.file_path ||
                                                                    msg.message
                                                            )
                                                        "
                                                        type="video/mp4"
                                                    />
                                                    Browser Anda tidak mendukung
                                                    tag video.
                                                </video>
                                            </div>
                                            <div
                                                class="d-flex justify-content-between align-items-center px-1"
                                            >
                                                <span
                                                    class="fs-9"
                                                    :class="
                                                        msg.sender_id ===
                                                        currentUser?.id
                                                            ? 'text-white text-opacity-75'
                                                            : 'text-gray-600'
                                                    "
                                                >
                                                    <i
                                                        class="fas fa-film me-1"
                                                    ></i>
                                                    {{
                                                        msg.file_size
                                                            ? (
                                                                  msg.file_size /
                                                                  1024 /
                                                                  1024
                                                              ).toFixed(1) +
                                                              " MB"
                                                            : "Video"
                                                    }}
                                                </span>
                                                <button
                                                    @click.prevent="
                                                        downloadAttachment(msg)
                                                    "
                                                    class="btn btn-sm btn-icon p-0 h-20px w-20px"
                                                    :class="
                                                        msg.sender_id ===
                                                        currentUser?.id
                                                            ? 'btn-active-color-white text-white'
                                                            : 'btn-active-color-primary text-gray-600'
                                                    "
                                                    title="Unduh Video"
                                                >
                                                    <i
                                                        class="fas fa-download fs-7"
                                                    ></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div
                                            v-else-if="msg.type === 'image'"
                                            class="mb-2 position-relative"
                                        >
                                            <img
                                                :src="getFileUrl(msg.file_path)"
                                                class="rounded w-100 cursor-pointer border"
                                                @click="
                                                    openLightbox(msg.file_path)
                                                "
                                                style="
                                                    max-height: 250px;
                                                    object-fit: cover;
                                                "
                                            />

                                            <button
                                                @click.stop="
                                                    downloadAttachment(msg)
                                                "
                                                class="btn btn-icon btn-sm btn-dark position-absolute bottom-0 end-0 m-2 shadow-sm"
                                                style="
                                                    width: 30px;
                                                    height: 30px;
                                                    background-color: rgba(
                                                        0,
                                                        0,
                                                        0,
                                                        0.6
                                                    );
                                                    border: none;
                                                "
                                                title="Unduh Gambar"
                                            >
                                                <i
                                                    class="fas fa-download fs-8 text-white"
                                                ></i>
                                            </button>
                                        </div>

                                        <div
                                            v-else-if="msg.type === 'file'"
                                            class="d-flex align-items-center p-2 rounded mb-2"
                                            :class="
                                                msg.sender_id ===
                                                currentUser?.id
                                                    ? 'bg-white bg-opacity-20'
                                                    : 'bg-light'
                                            "
                                        >
                                            <div
                                                class="symbol symbol-35px me-2"
                                            >
                                                <span
                                                    class="symbol-label fw-bold text-primary bg-white"
                                                    >FILE</span
                                                >
                                            </div>
                                            <div
                                                class="text-truncate"
                                                style="max-width: 150px"
                                            >
                                                <a
                                                    href="#"
                                                    @click.prevent="
                                                        downloadAttachment(msg)
                                                    "
                                                    class="fw-bold fs-7 d-block text-truncate"
                                                    :class="
                                                        msg.sender_id ===
                                                        currentUser?.id
                                                            ? 'text-white'
                                                            : 'text-gray-800'
                                                    "
                                                >
                                                    {{ msg.file_name }}
                                                </a>
                                                <div
                                                    class="fs-8"
                                                    :class="
                                                        msg.sender_id ===
                                                        currentUser?.id
                                                            ? 'text-white text-opacity-75'
                                                            : 'text-muted'
                                                    "
                                                >
                                                    {{
                                                        msg.file_size
                                                            ? (
                                                                  msg.file_size /
                                                                  1024
                                                              ).toFixed(0)
                                                            : "0"
                                                    }}
                                                    KB
                                                </div>
                                            </div>
                                            <button
                                                @click="downloadAttachment(msg)"
                                                class="btn btn-sm btn-icon ms-auto"
                                                :class="
                                                    msg.sender_id ===
                                                    currentUser?.id
                                                        ? 'btn-white text-primary'
                                                        : 'btn-light text-gray-600'
                                                "
                                            >
                                                <i
                                                    class="fas fa-download fs-7"
                                                ></i>
                                            </button>
                                        </div>

                                        <div
                                            v-if="msg.message"
                                            class="fs-6 px-1 text-break"
                                        >
                                            {{ msg.message }}
                                        </div>

                                        <div
                                            class="d-flex justify-content-end align-items-center mt-1"
                                        >
                                            <span
                                                class="fs-9 me-1"
                                                :class="
                                                    msg.sender_id ===
                                                    currentUser?.id
                                                        ? 'text-white text-opacity-75'
                                                        : 'text-muted'
                                                "
                                            >
                                                {{ formatTime(msg.created_at) }}
                                            </span>

                                            <div
                                                v-if="
                                                    msg.sender_id ===
                                                    currentUser?.id
                                                "
                                                class="ms-1"
                                            >
                                                <span
                                                    v-if="
                                                        msg.is_temp ||
                                                        (typeof isTempId ===
                                                            'function' &&
                                                            isTempId(msg.id))
                                                    "
                                                    title="Mengirim..."
                                                >
                                                    <Loader2
                                                        class="spin-animation text-white text-opacity-75"
                                                        :size="14"
                                                    />
                                                </span>

                                                <span v-else title="Terkirim">
                                                    <i
                                                        class="fas fa-check-double text-white text-opacity-50 fs-9"
                                                    ></i>
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            class="position-absolute top-0 end-0 mt-n2 me-n2"
                                            :class="
                                                msg.sender_id ===
                                                currentUser?.id
                                                    ? 'start-0 ms-n2'
                                                    : 'end-0 me-n2'
                                            "
                                        >
                                            <button
                                                @click.stop="
                                                    toggleMessageMenu(msg.id)
                                                "
                                                class="btn btn-sm btn-icon btn-circle shadow-sm w-20px h-20px"
                                                style="
                                                    z-index: 10;
                                                    background-color: rgba(
                                                        255,
                                                        255,
                                                        255,
                                                        0.85
                                                    );
                                                "
                                            >
                                                <i
                                                    class="fas fa-ellipsis-v fs-9 text-gray-600"
                                                ></i>
                                            </button>

                                            <div
                                                v-if="
                                                    openMessageMenuId === msg.id
                                                "
                                                class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-bold w-150px py-2 show position-absolute end-0 mt-1 shadow-lg bg-white"
                                                style="z-index: 105"
                                            >
                                                <div class="menu-item px-3">
                                                    <a
                                                        href="#"
                                                        class="menu-link px-3 fs-7"
                                                        @click.prevent="
                                                            handleMessageAction(
                                                                setReply,
                                                                msg
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="fas fa-reply me-2 text-warning fs-8"
                                                        ></i>
                                                        Balas
                                                    </a>
                                                </div>

                                                <div class="menu-item px-3">
                                                    <a
                                                        href="#"
                                                        class="menu-link px-3 fs-7 text-danger"
                                                        @click.prevent="
                                                            handleMessageAction(
                                                                openDeleteModal,
                                                                msg
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            class="fas fa-trash me-2 text-danger fs-8"
                                                        ></i>
                                                        Hapus
                                                    </a>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    openMessageMenuId === msg.id
                                                "
                                                @click.stop="
                                                    openMessageMenuId = null
                                                "
                                                class="position-fixed top-0 start-0 w-100 h-100"
                                                style="
                                                    z-index: 104;
                                                    cursor: default;
                                                "
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="typingUsers.length > 0"
                            class="d-flex justify-content-start mb-5 ps-12"
                        >
                            <div class="text-muted fs-8 fst-italic">
                                <span v-if="typingUsers.length === 1"
                                    >{{ typingUsers[0] }} sedang
                                    mengetik...</span
                                >
                                <span v-else
                                    >{{ typingUsers.length }} orang sedang
                                    mengetik...</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Reply Bar -->
                    <transition name="reply-slide">
                        <div v-if="replyingTo" class="reply-bar">
                            <div class="reply-bar-accent"></div>
                            <div class="reply-bar-icon">
                                <i class="fas fa-reply"></i>
                            </div>
                            <div class="reply-bar-content">
                                <span class="reply-bar-name">
                                    Balas ke:
                                    {{
                                        replyingTo.sender_id === currentUser.id
                                            ? "Anda"
                                            : replyingTo.sender?.name
                                    }}
                                </span>
                                <span class="reply-bar-preview">{{
                                    replyingTo.message || "Lampiran"
                                }}</span>
                            </div>
                            <button
                                @click="cancelReply"
                                class="reply-bar-close"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </transition>

                    <!-- Scroll to Bottom -->
                    <transition name="scroll-btn">
                        <button
                            v-if="showScrollButton"
                            @click="scrollToBottom"
                            class="scroll-down-btn"
                        >
                            <i class="fas fa-arrow-down"></i>
                        </button>
                    </transition>

                    <!-- Footer Input -->
                    <div class="chat-footer">
                        <input
                            type="file"
                            ref="fileInput"
                            class="d-none"
                            @change="sendMessage"
                            accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                        />
                        <button
                            class="footer-attach-btn"
                            @click="triggerFileUpload"
                            title="Lampirkan file"
                        >
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <input
                            v-model="newMessage"
                            @keyup.enter="sendMessage"
                            type="text"
                            @input="
                                (e) => {
                                    handleTyping();
                                    if (activeGroup)
                                        chatDrafts[activeGroup.id] = newMessage;
                                }
                            "
                            class="footer-input"
                            placeholder="Ketik pesan..."
                            ref="messageInputRef"
                        />
                        <button
                            class="footer-send-btn"
                            @click="sendMessage"
                            :class="{ active: newMessage.trim() }"
                        >
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- ═══ MODAL: Buat Grup ═══ -->
    <transition name="modal-pop">
        <div
            v-if="isCreateGroupOpen"
            class="modal-backdrop"
            @click.self="isCreateGroupOpen = false"
        >
            <div class="modal-card" style="max-width: 520px">
                <GroupForm
                    @close="isCreateGroupOpen = false"
                    @refresh="fetchGroups"
                />
            </div>
        </div>
    </transition>

    <!-- ═══ MODAL: Edit Grup ═══ -->
    <transition name="modal-pop">
        <div
            v-if="isEditGroupOpen"
            class="modal-backdrop"
            @click.self="isEditGroupOpen = false"
        >
            <div class="modal-card" style="max-width: 560px">
                <GroupEdit
                    v-if="isEditGroupOpen"
                    :groupId="groupIdToEdit"
                    :title="editModalTitle"
                    @close="isEditGroupOpen = false"
                    @refresh="fetchGroups"
                    @group-updated="handleGroupUpdated"
                />
            </div>
        </div>
    </transition>

    <!-- ═══ LIGHTBOX ═══ -->
    <transition name="lightbox-fade">
        <div
            v-if="isLightboxOpen"
            class="lightbox-backdrop"
            @click.self="closeLightbox"
        >
            <button class="lightbox-close-btn" @click="closeLightbox">
                <i class="fas fa-times"></i>
            </button>
            <div class="lightbox-img-wrap">
                <img :src="activeLightboxUrl" class="lightbox-img" />
            </div>
        </div>
    </transition>

    <!-- ═══ MODAL: Hapus Pesan ═══ -->
    <transition name="modal-pop">
        <div
            v-if="isDeleteModalOpen"
            class="modal-backdrop"
            @click.self="closeDeleteModal"
        >
            <div class="modal-card confirm-card" style="max-width: 360px">
                <div class="confirm-icon-wrap danger">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h4 class="confirm-title">Hapus Pesan?</h4>
                <p class="confirm-desc">
                    Pesan yang dihapus tidak dapat dikembalikan.
                </p>
                <div class="confirm-actions">
                    <button
                        @click="confirmDelete('me')"
                        class="confirm-btn secondary"
                    >
                        <i class="fas fa-user me-2"></i>Hapus untuk saya
                    </button>
                    <button
                        v-if="messageToDelete?.sender_id === currentUser?.id"
                        @click="confirmDelete('everyone')"
                        class="confirm-btn danger"
                    >
                        <i class="fas fa-users me-2"></i>Hapus untuk semua
                    </button>
                    <button @click="closeDeleteModal" class="confirm-btn ghost">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </transition>

    <!-- ═══ MODAL: Info Grup ═══ -->
    <transition name="modal-pop">
        <div
            v-if="isInfoModalOpen"
            class="modal-backdrop"
            @click.self="isInfoModalOpen = false"
        >
            <div class="modal-card info-card" style="max-width: 420px">
                <div class="info-header">
                    <button
                        class="info-close-btn"
                        @click="isInfoModalOpen = false"
                    >
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="info-avatar-wrap">
                        <img
                            :src="
                                activeGroup?.photo
                                    ? `/storage/${activeGroup.photo}`
                                    : '/media/avatars/group-blank.png'
                            "
                            class="info-avatar"
                        />
                    </div>
                    <h4 class="info-name">{{ activeGroup?.name }}</h4>
                    <p class="info-username">
                        {{ activeGroup?.members_count }} Anggota
                    </p>
                </div>
                <div class="info-body">
                    <div class="info-section-title">
                        <i class="fas fa-users me-2"></i>Anggota ({{
                            activeGroup?.members?.length || 0
                        }})
                    </div>
                    <div
                        class="info-members-list"
                        v-if="activeGroup?.members?.length"
                    >
                        <div
                            v-for="member in activeGroup.members"
                            :key="member.id"
                            class="info-member-row"
                        >
                            <div class="info-member-avatar">
                                <img
                                    :src="
                                        member.photo
                                            ? `/storage/${member.photo}`
                                            : '/media/avatars/blank.png'
                                    "
                                />
                            </div>
                            <div class="info-member-detail">
                                <span class="info-member-name">
                                    {{ member.name }}
                                    <span
                                        v-if="member.id === currentUser?.id"
                                        class="info-member-you"
                                        >(Anda)</span
                                    >
                                </span>
                                <span
                                    v-if="!member.is_admin"
                                    class="info-member-sub"
                                    >{{
                                        member.phone ||
                                        member.phone_number ||
                                        "-"
                                    }}</span
                                >
                            </div>
                            <span
                                v-if="member.is_admin"
                                class="info-member-badge"
                                >Admin</span
                            >
                        </div>
                    </div>
                    <div v-else class="info-empty">
                        <i class="fas fa-exclamation-circle me-1"></i> Data
                        anggota belum dimuat.
                        <button @click="fetchGroups" class="info-refresh-btn">
                            Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </transition>

    <!-- ═══ MODAL: Keluar Grup ═══ -->
    <transition name="modal-pop">
        <div
            v-if="isLeaveGroupModalOpen"
            class="modal-backdrop"
            @click.self="isLeaveGroupModalOpen = false"
        >
            <div class="modal-card confirm-card" style="max-width: 360px">
                <div class="confirm-icon-wrap warning">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h4 class="confirm-title">Keluar Grup?</h4>
                <p class="confirm-desc">
                    Anda tidak akan bisa melihat atau mengirim pesan di grup
                    <strong>"{{ activeGroup?.name }}"</strong> lagi.
                </p>
                <div class="confirm-actions">
                    <button
                        @click="processLeaveGroup"
                        class="confirm-btn danger"
                    >
                        <i class="fas fa-sign-out-alt me-2"></i>Ya, Keluar
                    </button>
                    <button
                        @click="isLeaveGroupModalOpen = false"
                        class="confirm-btn ghost"
                    >
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </transition>

    <!-- ═══ MODAL: Bersihkan Chat ═══ -->
    <transition name="modal-pop">
        <div
            v-if="isClearChatModalOpen"
            class="modal-backdrop"
            @click.self="isClearChatModalOpen = false"
        >
            <div class="modal-card confirm-card" style="max-width: 360px">
                <div class="confirm-icon-wrap warning">
                    <i class="fas fa-eraser"></i>
                </div>
                <h4 class="confirm-title">Bersihkan Chat?</h4>
                <p class="confirm-desc">
                    Semua pesan di grup ini akan dihapus secara permanen dan
                    tidak dapat dikembalikan.
                </p>
                <div class="confirm-actions">
                    <button
                        @click="processClearChat"
                        class="confirm-btn danger"
                    >
                        <i class="fas fa-eraser me-2"></i>Ya, Bersihkan
                    </button>
                    <button
                        @click="isClearChatModalOpen = false"
                        class="confirm-btn ghost"
                    >
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
/* ═══ KEYFRAMES ═══════════════════════════════════════════════════════════ */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-14px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(14px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
@keyframes modalPop {
    from {
        opacity: 0;
        transform: scale(0.88) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
@keyframes popIn {
    0% {
        transform: scale(0.8);
        opacity: 0;
    }
    70% {
        transform: scale(1.05);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes typing {
    0%,
    80%,
    100% {
        transform: scale(0.5);
        opacity: 0.3;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}
@keyframes replySlide {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes scrollBtnIn {
    from {
        opacity: 0;
        transform: translateX(-50%) scale(0.7);
    }
    to {
        opacity: 1;
        transform: translateX(-50%) scale(1);
    }
}
@keyframes pulseGlow {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
    }
    50% {
        box-shadow: 0 0 0 8px rgba(102, 126, 234, 0);
    }
}

/* ═══ SCROLLBAR ═══════════════════════════════════════════════════════════ */
.scroll-y,
.chat-body-custom {
    scrollbar-width: thin;
    scrollbar-color: rgba(102, 126, 234, 0.2) transparent;
}
.scroll-y::-webkit-scrollbar,
.chat-body-custom::-webkit-scrollbar {
    width: 4px;
}
.scroll-y::-webkit-scrollbar-thumb,
.chat-body-custom::-webkit-scrollbar-thumb {
    background: rgba(102, 126, 234, 0.25);
    border-radius: 10px;
}

/* ═══ GROUP SIDEBAR ═══════════════════════════════════════════════════════ */
.contact-item {
    transition: background 0.2s, transform 0.18s, box-shadow 0.2s;
    animation: slideInLeft 0.28s ease both;
    border-radius: 10px !important;
    border-left: 3px solid transparent;
}
.contact-item:hover {
    background: rgba(102, 126, 234, 0.07) !important;
    transform: translateX(3px);
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.09);
}
.contact-item.bg-light-primary {
    background: rgba(102, 126, 234, 0.11) !important;
    border-left-color: #667eea !important;
}
.badge-circle.badge-primary {
    background: linear-gradient(135deg, #667eea, #764ba2) !important;
    box-shadow: 0 2px 6px rgba(102, 126, 234, 0.4);
    animation: popIn 0.3s ease;
}
.badge-light-primary {
    background: rgba(102, 126, 234, 0.1) !important;
    color: #667eea !important;
    border-color: rgba(102, 126, 234, 0.22) !important;
}

/* ═══ CHAT BODY ═══════════════════════════════════════════════════════════ */
.chat-body-custom {
    height: calc(100vh - 265px);
    overflow-y: auto;
    background: #f9f9f9;
    scroll-behavior: smooth;
    position: relative;
}
.receiver-bubble {
    background: #fff;
    color: #3f4254;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}
.justify-content-end > .d-flex {
    animation: slideInRight 0.22s ease both;
}
.justify-content-start > .d-flex {
    animation: slideInLeft 0.22s ease both;
}
.tick-read {
    color: #69f0ae !important;
    animation: fadeIn 0.3s ease;
}
.fa-check,
.fa-check-double {
    transition: all 0.2s ease;
}
.hover-effect {
    transition: background 0.2s;
}
.hover-effect:hover {
    background: rgba(102, 126, 234, 0.1);
}
.border-primary {
    border-color: #667eea !important;
}
.text-primary {
    color: #667eea !important;
}
.delete-btn-wrapper {
    opacity: 0;
    transition: opacity 0.2s;
}
.group-hover:hover .delete-btn-wrapper {
    opacity: 1;
}
.spin-animation {
    animation: spin 1s linear infinite;
}

/* ═══ TYPING INDICATOR ════════════════════════════════════════════════════ */
.typing-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    height: 10px;
}
.typing-indicator span {
    display: block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    animation: typing 1.4s infinite ease-in-out both;
}
.typing-indicator span:nth-child(1) {
    animation-delay: -0.32s;
}
.typing-indicator span:nth-child(2) {
    animation-delay: -0.16s;
}

/* ═══ REPLY BAR ═══════════════════════════════════════════════════════════ */
.reply-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    background: #fff;
    border-top: 1px solid #f0f0f0;
    animation: replySlide 0.2s ease;
    position: relative;
    overflow: hidden;
}
.reply-bar-accent {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #667eea, #764ba2);
    border-radius: 0 2px 2px 0;
}
.reply-bar-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(102, 126, 234, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
    font-size: 0.75rem;
    flex-shrink: 0;
}
.reply-bar-content {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    padding-left: 4px;
}
.reply-bar-name {
    font-size: 0.75rem;
    font-weight: 700;
    color: #667eea;
}
.reply-bar-preview {
    font-size: 0.75rem;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 280px;
}
.reply-bar-close {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: none;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    flex-shrink: 0;
    transition: background 0.2s, transform 0.15s;
}
.reply-bar-close:hover {
    background: rgba(239, 68, 68, 0.2);
    transform: rotate(90deg);
}

/* ═══ SCROLL DOWN BUTTON ══════════════════════════════════════════════════ */
.scroll-down-btn {
    position: absolute;
    bottom: 100px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 20;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 0.8rem;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(102, 126, 234, 0.45);
    transition: transform 0.2s, box-shadow 0.2s;
    animation: scrollBtnIn 0.25s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}
.scroll-down-btn:hover {
    transform: translateX(-50%) translateY(-2px);
    box-shadow: 0 6px 18px rgba(102, 126, 234, 0.55);
}

/* ═══ CHAT FOOTER ═════════════════════════════════════════════════════════ */
.chat-footer {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: #fff;
    border-top: 1px solid #f0f2f5;
    min-height: 68px;
}
.footer-attach-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: none;
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s, transform 0.15s;
}
.footer-attach-btn:hover {
    background: rgba(102, 126, 234, 0.2);
    transform: rotate(-15deg) scale(1.08);
}
.footer-input {
    flex: 1;
    padding: 10px 16px;
    border: 1.5px solid #edf0f7;
    border-radius: 22px;
    background: #f5f8fa;
    font-size: 0.88rem;
    color: #1a202c;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}
.footer-input:focus {
    border-color: #667eea;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
.footer-send-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: #e8ecf8;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.25s, color 0.25s, transform 0.15s, box-shadow 0.2s;
}
.footer-send-btn.active,
.footer-send-btn:hover {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    transform: scale(1.08);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* ═══ CALL BUTTONS ════════════════════════════════════════════════════════ */
.card-header .d-flex button {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 8px 10px !important;
    border-radius: 10px !important;
    transition: background 0.2s, transform 0.15s !important;
    cursor: pointer;
}
.card-header .d-flex button:hover {
    background: rgba(102, 126, 234, 0.1) !important;
    transform: scale(1.12);
}
.card-header .d-flex button:active {
    transform: scale(0.94);
}
.card-header .d-flex button svg {
    width: 20px !important;
    height: 20px !important;
    color: #7e8299 !important;
    transition: color 0.2s !important;
}
.card-header .d-flex button:nth-last-child(3):hover svg {
    color: #10b981 !important;
}
.card-header .d-flex button:nth-last-child(2):hover svg {
    color: #667eea !important;
}

/* ═══ ADD GROUP BUTTON ════════════════════════════════════════════════════ */
.btn-light-primary {
    background: rgba(102, 126, 234, 0.1) !important;
    color: #667eea !important;
    border: none;
    transition: background 0.2s, transform 0.15s;
}
.btn-light-primary:hover {
    background: rgba(102, 126, 234, 0.18) !important;
    transform: translateY(-1px);
}

/* ═══ MODAL BACKDROP ══════════════════════════════════════════════════════ */
.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(15, 15, 30, 0.55);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    animation: fadeIn 0.2s ease;
}
.modal-card {
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    width: 100%;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.18);
    animation: modalPop 0.28s cubic-bezier(0.34, 1.2, 0.64, 1) both;
}

/* ── Confirm Card ── */
.confirm-card {
    padding: 36px 28px 28px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.confirm-icon-wrap {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 16px;
    animation: popIn 0.35s ease;
}
.confirm-icon-wrap.danger {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}
.confirm-icon-wrap.warning {
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
}
.confirm-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: #1a202c;
    margin: 0 0 8px;
}
.confirm-desc {
    font-size: 0.84rem;
    color: #64748b;
    line-height: 1.6;
    margin: 0 0 20px;
}
.confirm-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}
.confirm-btn {
    width: 100%;
    padding: 10px 16px;
    border-radius: 10px;
    border: none;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.confirm-btn:hover {
    opacity: 0.88;
    transform: translateY(-1px);
}
.confirm-btn.secondary {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
}
.confirm-btn.secondary:hover {
    background: rgba(102, 126, 234, 0.18);
}
.confirm-btn.danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    box-shadow: 0 3px 10px rgba(239, 68, 68, 0.3);
}
.confirm-btn.ghost {
    background: transparent;
    color: #94a3b8;
    font-weight: 500;
}
.confirm-btn.ghost:hover {
    background: #f8fafc;
    color: #64748b;
}

/* ── Info Group Modal ── */
.info-card {
    overflow: hidden;
}
.info-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 28px 24px 20px;
    text-align: center;
    position: relative;
}
.info-close-btn {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.75rem;
    transition: background 0.2s, transform 0.15s;
}
.info-close-btn:hover {
    background: rgba(255, 255, 255, 0.32);
    transform: rotate(90deg);
}
.info-avatar-wrap {
    display: inline-block;
    margin-bottom: 10px;
}
.info-avatar {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.5);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    animation: popIn 0.35s ease;
}
.info-name {
    font-size: 1rem;
    font-weight: 800;
    color: white;
    margin: 0 0 2px;
}
.info-username {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.75);
    margin: 0;
}
.info-body {
    padding: 16px 20px;
    max-height: 280px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(102, 126, 234, 0.2) transparent;
}
.info-section-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: #667eea;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}
.info-members-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.info-member-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: 10px;
    transition: background 0.2s;
}
.info-member-row:hover {
    background: rgba(102, 126, 234, 0.06);
}
.info-member-avatar img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #f0f0f0;
    flex-shrink: 0;
}
.info-member-detail {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}
.info-member-name {
    font-size: 0.84rem;
    font-weight: 700;
    color: #1a202c;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.info-member-you {
    font-size: 0.72rem;
    font-weight: 400;
    color: #94a3b8;
    margin-left: 4px;
}
.info-member-sub {
    font-size: 0.74rem;
    color: #94a3b8;
}
.info-member-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 20px;
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    flex-shrink: 0;
}
.info-empty {
    font-size: 0.8rem;
    color: #94a3b8;
    text-align: center;
    padding: 16px 0;
}
.info-refresh-btn {
    background: none;
    border: none;
    color: #667eea;
    font-size: 0.8rem;
    cursor: pointer;
    text-decoration: underline;
    margin-top: 6px;
}

/* ── Lightbox ── */
.lightbox-backdrop {
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(0, 0, 0, 0.92);
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.2s ease;
}
.lightbox-close-btn {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.12);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.9rem;
    z-index: 1;
    transition: background 0.2s, transform 0.15s;
}
.lightbox-close-btn:hover {
    background: rgba(255, 255, 255, 0.22);
    transform: rotate(90deg);
}
.lightbox-img-wrap {
    animation: modalPop 0.28s ease;
}
.lightbox-img {
    max-height: 85vh;
    max-width: 90vw;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
}

/* ═══ VUE TRANSITIONS ══════════════════════════════════════════════════════ */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.modal-pop-enter-active {
    animation: modalPop 0.28s cubic-bezier(0.34, 1.2, 0.64, 1) both;
}
.modal-pop-leave-active {
    animation: modalPop 0.2s ease reverse both;
}
.lightbox-fade-enter-active {
    animation: fadeIn 0.2s ease;
}
.lightbox-fade-leave-active {
    animation: fadeIn 0.18s ease reverse;
}
.reply-slide-enter-active {
    animation: replySlide 0.2s ease;
}
.reply-slide-leave-active {
    animation: replySlide 0.15s ease reverse;
}
.scroll-btn-enter-active {
    animation: scrollBtnIn 0.25s ease;
}
.scroll-btn-leave-active {
    animation: scrollBtnIn 0.18s ease reverse;
}

/* ═══ TOAST ════════════════════════════════════════════════════════════════ */
:root {
    --toastify-text-color-light: #000 !important;
    --toastify-color-light: #fff;
}
.Toastify__toast-theme--light {
    color: #333 !important;
    background: #fff !important;
}
.Toastify__close-button--light {
    color: #333 !important;
    opacity: 0.7;
}

/* ═══ DARK MODE ════════════════════════════════════════════════════════════ */
[data-bs-theme="dark"] .chat-body-custom {
    background: #151521 !important;
}
[data-bs-theme="dark"] .card-header,
[data-bs-theme="dark"] .card-footer {
    background: #1e1e2d !important;
    border-color: #2b2b40 !important;
}
[data-bs-theme="dark"] .receiver-bubble {
    background: #2b2b40 !important;
    color: #fff !important;
}
[data-bs-theme="dark"] .form-control-solid {
    background: #1b1b29 !important;
    border-color: #2b2b40 !important;
    color: #fff !important;
}
[data-bs-theme="dark"] .bg-white {
    background: #1e1e2d !important;
    color: #fff !important;
}
[data-bs-theme="dark"] .text-gray-900 {
    color: #fff !important;
}
[data-bs-theme="dark"] .text-muted {
    color: #7e8299 !important;
}
[data-bs-theme="dark"] .contact-item:hover {
    background: rgba(102, 126, 234, 0.12) !important;
}
[data-bs-theme="dark"] .contact-item.bg-light-primary {
    background: rgba(102, 126, 234, 0.18) !important;
}
[data-bs-theme="dark"] .chat-footer {
    background: #1e1e2d;
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .footer-input {
    background: #1b1b29;
    border-color: #2b2b40;
    color: #e1e1e1;
}
[data-bs-theme="dark"] .footer-input:focus {
    background: #22223a;
    border-color: #667eea;
}
[data-bs-theme="dark"] .footer-attach-btn {
    background: rgba(102, 126, 234, 0.15);
    color: #a5b4fc;
}
[data-bs-theme="dark"] .footer-send-btn {
    background: #2b2b40;
    color: #6b7280;
}
[data-bs-theme="dark"] .reply-bar {
    background: #1e1e2d;
    border-color: #2b2b40;
}
[data-bs-theme="dark"] .modal-card {
    background: #1e1e2d;
}
[data-bs-theme="dark"] .confirm-title {
    color: #e1e1e1;
}
[data-bs-theme="dark"] .confirm-desc {
    color: #7e8299;
}
[data-bs-theme="dark"] .confirm-btn.ghost:hover {
    background: #2b2b40;
}
[data-bs-theme="dark"] .info-body {
    background: #1e1e2d;
}
[data-bs-theme="dark"] .info-member-name {
    color: #e1e1e1;
}
[data-bs-theme="dark"] .info-member-row:hover {
    background: rgba(102, 126, 234, 0.1);
}
[data-bs-theme="dark"] .card-header .d-flex button:hover {
    background: rgba(102, 126, 234, 0.15) !important;
}
[data-bs-theme="dark"] .card-header .d-flex button svg {
    color: #a1a5b7 !important;
}
[data-bs-theme="dark"] .card-header .d-flex button:nth-last-child(3):hover svg {
    color: #34d399 !important;
}
[data-bs-theme="dark"] .card-header .d-flex button:nth-last-child(2):hover svg {
    color: #a5b4fc !important;
}
</style>
