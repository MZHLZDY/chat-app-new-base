<script setup lang="ts">
import { ref, onMounted, nextTick, computed, onUnmounted, watch } from "vue";
import { useAuthStore } from "@/stores/authStore";
import { usePage } from "@inertiajs/vue3";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import {
    formatDistanceToNowStrict,
    format,
    isToday,
    isYesterday,
    isSameDay,
} from "date-fns";
import { id } from "date-fns/locale";
import {
    Phone,
    Video,
    Download,
    Loader2,
    CheckCheck,
    ArrowLeft,
} from "lucide-vue-next";
import { useGlobalChatStore } from "@/stores/globalChat";
import { usePersonalCall } from "@/composables/usePersonalCall";
import type { PersonalCall, User } from "@/types/call";
import VoiceCallModal from "@/components/call/voice/VoiceCallModal.vue";
import VoiceFloating from "@/components/call/voice/VoiceFloating.vue";
import VoiceIncomingModal from "@/components/call/voice/VoiceIncomingModal.vue";
import VoiceCallingModal from "@/components/call/voice/VoiceCallingModal.vue";
import VideoCallingModal from "@/components/call/video/VideoCallingModal.vue";
import VideoIncomingModal from "@/components/call/video/VideoIncomingModal.vue";
import VideoCallModal from "@/components/call/video/VideoCallModal.vue";

// Component Form Kontak
import ContactForm from "./Form.vue";
import EditForm from "./Edit.vue";

// --- FIREBASE IMPORT ---
import { db, auth } from "@/libs/firebase";
import { database } from "@/libs/firebase";
import {
    ref as firebaseRef,
    onChildAdded,
    onChildChanged, // <-- Tambahkan ini
    onChildRemoved,
    onValue,
    off,
    remove,
    set,
    onDisconnect,
    type Unsubscribe,
    query,
    limitToLast,
} from "firebase/database";
import { onAuthStateChanged } from "firebase/auth";
import { ref as dbRef } from "firebase/database";

// --- CALL IMPORT ---
import { useVoiceCall } from "@/composables/useVoiceCall";
// import { useVideoCall } from "@/composables/useVideoCall";
import { useCallStore } from "@/stores/callStore";
import type { Call, CallStatus, CallType } from "@/types/call";

// State usePersonalCall
const {
    initiateCall,
    answerCall,
    rejectCall,
    endCall,
    isLoading: callProcessing,
} = usePersonalCall();

// --- STATE UTAMA ---
const authStore = useAuthStore();
const page = usePage();
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
const globalChatStore = useGlobalChatStore();
const isAddContactOpen = ref(false);
const isEditContactOpen = ref(false);
const contactIdToEdit = ref<string | number | undefined>(undefined);
const editModalTitle = ref("Edit Kontak");
const isDeleteModalOpen = ref(false);
const messageToDelete = ref<any>(null);
const showMobileChat = ref(false);
const messageDrafts = ref<Record<string | number, string>>({});
const isLightboxOpen = ref(false);
const activeLightboxUrl = ref("");
const heartbeatInterval = ref<any>(null);
const showScrollButton = ref(false);
const replyingTo = ref<any>(null);
const isHeaderMenuOpen = ref(false);
const isInfoModalOpen = ref(false);
const isFriendTyping = ref(false);
const isMuted = ref(false);
const isClearChatModalOpen = ref(false);
const openMessageMenuId = ref<number | string | null>(null);
let typingTimeout: ReturnType<typeof setTimeout> | null = null;
let typingListenerOff: Unsubscribe | null = null;

// --- FIREBASE STATE (Untuk Cleanup yang Benar) ---
let unsubscribeChats: Unsubscribe | null = null;
let unsubscribeNotif: Unsubscribe | null = null;
let unsubscribeOnlineAdded: Unsubscribe | null = null;
let unsubscribeOnlineRemoved: Unsubscribe | null = null;

// Ref untuk onValue (karena onValue cara cleanup-nya beda, pakai off)
let connectedRef: any = null;
let onlineRef: any = null;

// --- CALL LOGIC ---
const callStore = useCallStore();
// 1. Cek apakah sedang ada panggilan aktif
const isCallActive = computed(() => !!callStore.currentCall);

// Perbaikan 1: Casting status ke string agar tidak error type mismatch
const showIncomingModal = computed(
    () => !!callStore.incomingCall && !callStore.isInCall
);
const showCallingModal = computed(
    () => isCallActive.value && (callStore.callStatus as string) === "calling"
);
const showOngoingModal = computed(
    () =>
        isCallActive.value &&
        (callStore.callStatus as string) === "ongoing" &&
        !callStore.isMinimized
);
const showFloatingModal = computed(
    () => isCallActive.value && callStore.isMinimized
);

// Perbaikan 2: Sesuaikan akses properti (caller.id & receiver)
const remoteUser = computed(() => {
    if (!callStore.currentCall) return { name: "Unknown", avatar: "" };

    const call = callStore.currentCall;
    const myId = authStore.user?.id;

    // Error log bilang: call.caller.id (bukan call.caller_id)
    if (call.caller && call.caller.id === myId) {
        // Error log bilang: propertinya 'receiver', bukan 'callee'
        // @ts-ignore (jika receiver kadang null di type definition)
        return call.receiver || { name: "User", avatar: "" };
    } else {
        return call.caller || { name: "User", avatar: "" };
    }
});

const outgoingCalleeInfo = computed(() => {
    const c = callStore.currentCall as any;

    // Default value jika call null
    if (!c) return { name: "Unknown", photo: "" };

    // 1. Cek struktur Response API InviteCall (Outgoing) -> call.callee
    if (c.call && c.call.callee) {
        return {
            name: c.call.callee.name || "Unknown",
            photo: c.call.callee.profile_photo_url || c.call.callee.photo || "",
        };
    }

    // 2. Cek struktur standar (Incoming/Ongoing) -> call.receiver
    if (c.receiver) {
        return {
            name: c.receiver.name || "Unknown",
            photo: c.receiver.profile_photo_url || c.receiver.photo || "",
        };
    }

    // 3. Cek struktur alternatif flat -> call.callee
    if (c.callee) {
        return {
            name: c.callee.name || "Unknown",
            photo: c.callee.profile_photo_url || c.callee.photo || "",
        };
    }

    return { name: "Unknown", photo: "" };
});

/**
 * Helper function untuk mendapatkan foto caller dengan fallback priority
 */
const getCallerPhoto = (caller: any) => {
    if (!caller) return "";

    // Priority: profile_photo_url > photo > avatar
    return caller.profile_photo_url || caller.photo || caller.avatar || "";
};

/**
 * Helper function untuk mendapatkan foto remote user
 */
const getRemoteUserPhoto = (user: any) => {
    if (!user) return "";

    return user.profile_photo_url || user.photo || user.avatar || "";
};

// Pastikan destructuring ini sekarang sudah cocok dengan export useVoiceCall.ts di atas
const {
    startVoiceCall,
    acceptVoiceCall,
    rejectVoiceCall,
    endVoiceCall,
    cancelVoiceCall,
    toggleAudio,
    // toggleVideo,
    handleIncomingCall,
    handleCallAccepted,
    handleCallRejected,
    handleCallEnded,
    handleCallCancelled,
    processing: voiceProcessing,
} = useVoiceCall();

const isMinimized = ref(false); // State untuk mode minimize
const isSpeakerOn = ref(false); // State untuk speaker (UI only)

// --- HANDLERS ---

const handleVoiceCall = () => {
    if (!activeContact.value) {
        toast.error("Pilih kontak terlebih dahulu");
        return;
    }

    console.log("📞 Memulai voice call ke:", activeContact.value);
    startVoiceCall(activeContact.value, "voice"); // ✅ Kirim object lengkap!
};

const handleAcceptCall = async () => {
    if (!callStore.incomingCall) return;
    await acceptVoiceCall(String(callStore.incomingCall.id));
};

const handleRejectCall = async () => {
    if (!callStore.incomingCall) return;
    await rejectVoiceCall(String(callStore.incomingCall.id));
};

const handleCancelCall = async () => {
    if (!callStore.currentCall) return;
    await endVoiceCall(callStore.currentCall.id);
    isMinimized.value = false;
};

// 1. Logic Speaker (Simulasi Toggle)
const toggleSpeaker = () => {
    isSpeakerOn.value = !isSpeakerOn.value;
    // Note: Di browser, mengganti output audio ke speaker/earpiece
    // memerlukan enumerasi device yang kompleks. Untuk UI ini cukup state saja.
};

// 2. Logic Minimize
const handleMinimize = () => {
    isMinimized.value = true;
};

// 3. Logic Maximize (kembali ke layar penuh)
const handleMaximize = () => {
    isMinimized.value = false;
};

// 4. Update handleEndVoiceCall agar mereset minimize juga
// --- HANDLER END CALL (FIXED) ---
const handleEndVoiceCall = () => {
    // 1. Ambil ID ke variabel sementara
    const callId = callStore.currentCall?.id;

    // 2. Cek Validitas: Jika ID ada (tidak null/undefined/0)
    if (callId) {
        // Kita kirim apa adanya, karena useVoiceCall sekarang sudah bisa terima number/string
        endVoiceCall(callId);
    } else {
        // Fallback: Jika ID entah kenapa hilang, force cleanup di lokal saja
        console.warn("Call ID missing, forcing local cleanup.");
        callStore.clearCurrentCall();
    }

    // 3. Reset tampilan
    isMinimized.value = false;
};

// Handler video call
const handleVideoCall = async () => {
    console.log("📹 Tombol video call diklik");

    // Pastikan authStore.user terinisialisasi
    if (!authStore.user) {
        console.warn(
            "⚠️ authStore.user tidak terdefinisi, mencoba inisialisasi..."
        );

        if (currentUser.value?.id) {
            authStore.setUser(currentUser.value);
            console.log("✅ authStore.user terinisialisasi:", authStore.user);
        } else {
            console.error(
                "❌ Gagal inisialisasi authStore.user, tidak ada currentUser"
            );
            toast.error("Silahkan refresh halaman");
            return;
        }
    }

    // Double check setelah inisialisasi
    if (!authStore.user?.id) {
        console.error(
            "❌ authStore.user masih tidak terdefinisi setelah inisialisasi"
        );
        toast.error("Gagal mendapatkan data user");
        return;
    }

    // Validasi kontak aktif
    if (!activeContact.value) {
        console.error(
            "❌ Tidak ada kontak yang dipilih untuk panggilan video."
        );
        toast.error("Pilih kontak terlebih dahulu");
        return;
    }

    try {
        console.log("🚀 Memulai panggilan video ke:", activeContact.value.name);
        console.log("📦 Caller ID:", authStore.user.id);
        console.log("📦 Receiver ID:", activeContact.value.id);

        // Convert activeContact ke tipe User
        const receiveUser: User = {
            id: activeContact.value.id,
            name: activeContact.value.display_name || activeContact.value.name,
            email: activeContact.value.email,
            avatar: activeContact.value.photo
                ? `/storage/${activeContact.value.photo}`
                : undefined,
        };

        console.log("📦 Receiver User:", receiveUser);

        await initiateCall(receiveUser, "video"); // Panggil API /call/invite

        console.log("✅ Memulai panggilan video");

        // Log state setelah initiateCall
        console.log("State setelah initiate");
        console.log("callStore.currentCall:", callStore.currentCall);
        console.log("callStore.incomingCall:", callStore.incomingCall);
        console.log("callStore.isInCall:", callStore.isInCall);
        console.log("callStore.callStatus:", callStore.callStatus);
        console.log("showVideoCallingModal:", showVideoCallingModal.value);
        console.log("showVideoIncomingModal:", showVideoIncomingModal.value);
        console.log("showVideoCallModal:", showVideoCallModal.value);

        toast.success("Memanggil...");
    } catch (error: any) {
        console.error("❌ Gagal memulai panggilan video:", error);

        // Clear call state kalau error
        callStore.clearCurrentCall();

        // Tampilkan pesan error
        const errorMsg =
            error.response?.data?.message ||
            error.message ||
            "Gagal memulai panggilan video";
        toast.error(errorMsg);
    }
};

// Props untuk modal voice call
const incomingCallProps = computed(() => {
    if (!callStore.incomingCall) {
        return { callerName: "Unknown", callerPhoto: "" };
    }

    const caller = callStore.incomingCall.caller;
    return {
        callerName: caller?.name || "Unknown",
        callerPhoto:
            caller?.profile_photo_url || caller?.photo || caller?.avatar || "",
    };
});

const callingModalProps = computed(() => {
    if (!callStore.currentCall) {
        return {
            calleeName: "Unknown",
            calleePhoto: "",
            callStatus: "calling",
        };
    }

    const call = callStore.currentCall as any;
    const myId = authStore.user?.id;

    if (call.caller?.id === myId) {
        const callee = call.receiver || call.callee;
        return {
            calleeName: callee?.name || callee?.display_name || "Unknown",
            calleePhoto:
                callee?.profile_photo_url ||
                callee?.photo ||
                callee?.avatar ||
                "",
            callStatus: callStore.callStatus || "calling",
        };
    } else {
        return {
            calleeName: call.caller?.name || "Unknown",
            calleePhoto:
                call.caller?.profile_photo_url || call.caller?.photo || "",
            callStatus: callStore.callStatus || "calling",
        };
    }
});

const ongoingCallProps = computed(() => {
    if (!callStore.currentCall) {
        return { remoteName: "Unknown", remotePhoto: "" };
    }

    const call = callStore.currentCall as any;
    const myId = authStore.user?.id;

    const remoteUser =
        call.caller?.id === myId ? call.receiver || call.callee : call.caller;

    return {
        remoteName: remoteUser?.name || remoteUser?.display_name || "Unknown",
        remotePhoto:
            remoteUser?.profile_photo_url ||
            remoteUser?.photo ||
            remoteUser?.avatar ||
            "",
    };
});

// Computed untuk modal video call
const showVideoCallingModal = computed(
    () =>
        callStore.currentCall?.type === "video" &&
        callStore.callStatus === "ringing" &&
        !callStore.isInCall
);

const showVideoIncomingModal = computed(
    () => callStore.incomingCall?.type === "video" && !callStore.isInCall
);

const showVideoCallModal = computed(
    () =>
        callStore.currentCall?.type === "video" &&
        callStore.callStatus === "ongoing" &&
        callStore.isInCall
);

// --- PRIVATE CHAT LOGIC ---
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

const formatTime = (dateString: string | null | undefined): string => {
    if (!dateString)
        return new Date().toLocaleTimeString("id-ID", {
            hour: "2-digit",
            minute: "2-digit",
        });
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) throw new Error("Invalid date");
        return date.toLocaleTimeString("id-ID", {
            hour: "2-digit",
            minute: "2-digit",
        });
    } catch (error) {
        return new Date().toLocaleTimeString("id-ID", {
            hour: "2-digit",
            minute: "2-digit",
        });
    }
};

const formatLastSeen = (
    dateInput: string | number | null | undefined
): string => {
    if (!dateInput) return "offline";
    try {
        const date = new Date(dateInput);
        if (isToday(date)) {
            return `terakhir dilihat pukul ${format(date, "HH:mm", {
                locale: id,
            })}`;
        }
        if (isYesterday(date)) {
            return `terakhir dilihat kemarin pukul ${format(date, "HH:mm", {
                locale: id,
            })}`;
        }
        return `terakhir dilihat ${format(date, "d MMM yyyy, HH:mm", {
            locale: id,
        })}`;
    } catch (error) {
        return "offline";
    }
};

const formatDateSeparator = (dateString: string): string => {
    if (!dateString) return "";
    const date = new Date(dateString);

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
    if (activeContact.value) {
        messageDrafts.value[activeContact.value.id] = newMessage.value;
    }

    showMobileChat.value = true;
    activeContact.value = contact;
    globalChatStore.setActiveChat(contact.id);
    messages.value = [];
    newMessage.value = messageDrafts.value[String(contact.id)] || "";

    const contactIndex = contacts.value.findIndex((c) => c.id === contact.id);
    if (contactIndex !== -1) {
        contacts.value[contactIndex].unread_count = 0;
    }

    await getMessages(contact.id);

    nextTick(() => {
        const input = document.querySelector("input[type='text'].form-control");
        if (input) (input as HTMLElement).focus();
    });
};

const closeMobileChat = () => {
    showMobileChat.value = false;
    activeContact.value = null;
};

const getMessages = async (friendId: any) => {
    isLoadingMessages.value = true;
    try {
        const response = await axios.get(`/chat/messages/${friendId}`);
        messages.value = response.data.data
            ? response.data.data
            : response.data;
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
    if (!activeContact.value) return;

    const tempId = Date.now();
    let tempType = "text";
    if (file) {
        if (file.type.startsWith("image/")) tempType = "image";
        else if (file.type.startsWith("video/")) tempType = "video";
        else tempType = "file";
    }

    const tempMessage = {
        id: tempId,
        sender_id: currentUser.value?.id,
        receiver_id: activeContact.value.id,
        message: textContent,
        file_path: file ? URL.createObjectURL(file) : null,
        type: tempType,
        file_size: file ? file.size : 0,
        file_name: file ? file.name : null,
        created_at: new Date().toISOString(),
        read_at: null,
        reply_to: replyingTo.value ? replyingTo.value : null,
    };

    messages.value.push(tempMessage);
    scrollToBottom();
    const formData = new FormData();
    formData.append("receiver_id", activeContact.value.id);

    if (textContent) formData.append("message", textContent);
    if (file) formData.append("file", file);

    if (replyingTo.value) {
        formData.append("reply_to_id", replyingTo.value.id);
    }

    const tempReply = replyingTo.value;
    newMessage.value = "";
    replyingTo.value = null;
    if (fileInput.value) fileInput.value.value = "";
    if (activeContact.value) {
        delete messageDrafts.value[activeContact.value.id];
    }
    if (fileInput.value) fileInput.value.value = "";

    try {
        const response = await axios.post("/chat/send", formData, {
            headers: { "Content-Type": "multipart/form-data" },
        });
        const realMessage = response.data.data
            ? response.data.data
            : response.data;
        const index = messages.value.findIndex((m) => m.id === tempId);
        if (index !== -1) {
            messages.value[index] = realMessage;
        }

        refreshContactOrder(activeContact.value.id);
    } catch (error) {
        console.error("Gagal kirim pesan", error);
        toast.error("Gagal mengirim pesan");
        messages.value = messages.value.filter((m) => m.id !== tempId);
        replyingTo.value = tempReply;
    }
};

const setReply = (msg: any) => {
    replyingTo.value = msg;
    nextTick(() => {
        const input = document.querySelector("textarea");
        if (input) input.focus();
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

const STORAGE_URL = import.meta.env.VITE_API_URL || "http://localhost:8000";

const getFileUrl = (path: string) => {
    if (!path) return "";

    if (path.startsWith("blob:")) {
        return path;
    }
    if (path.startsWith("http")) {
        return path;
    }
    return `/storage/${path}`;
};

const downloadAttachment = (msg: any) => {
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

const refreshContactOrder = (contactId: any) => {
    const idx = contacts.value.findIndex((c) => c.id === contactId);
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

const confirmDelete = async (type: "me" | "everyone") => {
    if (!messageToDelete.value) return;
    const isForEveryone = type === "everyone";

    try {
        await axios.delete(`/chat/delete/${messageToDelete.value.id}`, {
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

const openEditModal = () => {
    isHeaderMenuOpen.value = false;
    if (!activeContact.value) {
        toast.error("Tidak ada kontak yang dipilih");
        return;
    }
    contactIdToEdit.value = activeContact.value.id;
    editModalTitle.value = "Edit Kontak";
    isEditContactOpen.value = true;
};

const handleContactUpdated = async () => {
    isEditContactOpen.value = false;
    await fetchContacts();
    if (
        activeContact.value &&
        contactIdToEdit.value === activeContact.value.id
    ) {
        const updatedContact = contacts.value.find(
            (c) => c.id === activeContact.value.id
        );
        if (updatedContact) {
            activeContact.value = updatedContact;
        }
    }
    toast.success("Kontak berhasil disimpan!");
};

const updateContactStatus = (userId: any, isOnline: boolean) => {
    const strUserId = String(userId);
    const contact = contacts.value.find((c) => String(c.id) === strUserId);
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

const toggleHeaderMenu = () => {
    isHeaderMenuOpen.value = !isHeaderMenuOpen.value;
};

const openInfoModal = () => {
    isHeaderMenuOpen.value = false;
    isInfoModalOpen.value = true;
};

const openClearChatModal = () => {
    isHeaderMenuOpen.value = false;
    if (!activeContact.value) return;
    isClearChatModalOpen.value = true;
};

const handleClearChat = async () => {
    if (!activeContact.value) return;

    try {
        await axios.post(`/chat/clear/${activeContact.value.id}`);
        messages.value = [];
        toast.success("Chat berhasil dibersihkan");
        await fetchContacts();
    } catch (error) {
        console.error(error);
        toast.error("Gagal membersihkan chat");
    } finally {
        isClearChatModalOpen.value = false;
    }
};

const handleTyping = () => {
    if (!activeContact.value || !currentUser.value) return;
    const myTypingRef = firebaseRef(
        db,
        `typing_status/${currentUser.value.id}`
    );
    set(myTypingRef, {
        is_typing: true,
        receiver_id: activeContact.value.id,
    });
    onDisconnect(myTypingRef).remove();
    if (typingTimeout) clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        set(myTypingRef, null);
    }, 2000);
};
const listenToTypingStatus = (friendId: number) => {
    if (typingListenerOff) {
        typingListenerOff();
        typingListenerOff = null;
    }

    const friendTypingRef = firebaseRef(db, `typing_status/${friendId}`);
    typingListenerOff = onValue(friendTypingRef, (snapshot) => {
        const data = snapshot.val();
        if (
            data &&
            data.is_typing &&
            data.receiver_id === currentUser.value?.id
        ) {
            isFriendTyping.value = true;
            setTimeout(() => {
                scrollToBottom();
            }, 100);
        } else {
            isFriendTyping.value = false;
        }
    });
};

// const notificationSound = new Audio('/media/preview.mp3');
// notificationSound.volume = 0.5;
// const playNotificationSound = async () => {
//     if (isMuted.value) return;

//     try {
//         notificationSound.currentTime = 0;
//         await notificationSound.play();
//     } catch (error) {
//         console.warn("Gagal memutar notifikasi suara:", error);
//     }
// };

const requestNotificationPermission = async () => {
    if ("Notification" in window && Notification.permission !== "granted") {
        await Notification.requestPermission();
    }
};

const handleEscKey = (e: KeyboardEvent) => {
    if (e.key === "Escape") {
        if (isLightboxOpen.value) {
            closeLightbox();
            return;
        }
        if (
            isAddContactOpen.value ||
            isEditContactOpen.value ||
            isDeleteModalOpen.value ||
            isInfoModalOpen.value ||
            isHeaderMenuOpen.value ||
            isClearChatModalOpen.value
        ) {
            if (isHeaderMenuOpen.value) isHeaderMenuOpen.value = false;
            if (isClearChatModalOpen.value) isClearChatModalOpen.value = false;
            return;
        }
        if (activeContact.value) {
            activeContact.value = null;
        }
    }
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

        if (incomingMsg.type === "delete_notify") {
            console.log(
                "Menerima perintah hapus untuk ID:",
                incomingMsg.target_message_id
            );

            const index = messages.value.findIndex(
                (m) => m.id === incomingMsg.target_message_id
            );

            if (index !== -1) {
                messages.value.splice(index, 1);
                // messages.value[index].message = "🚫 Pesan ini telah dihapus";
                // messages.value[index].type = "deleted";
            }
            return;
        }
        const msgTime = new Date(incomingMsg.created_at).getTime();
        const now = Date.now();
        const ageInSeconds = (now - msgTime) / 1000;

        if (ageInSeconds > 60) {
            return;
        }

        // if (incomingMsg.sender_id !== currentUser.value?.id) {
        //     playNotificationSound();
        // }

        if (
            activeContact.value &&
            incomingMsg.sender_id === activeContact.value.id
        ) {
            const exists = messages.value.some(
                (m: any) => m.id === incomingMsg.id
            );

            if (!exists) {
                messages.value.push(incomingMsg);
                scrollToBottom();

                try {
                    await axios.put(`/chat/message/${incomingMsg.id}/read`);
                } catch (error) {}
            }
        }

        const contactIndex = contacts.value.findIndex(
            (c) => c.id === incomingMsg.sender_id
        );

        if (contactIndex !== -1) {
            const contact = contacts.value[contactIndex];
            contact.last_message =
                incomingMsg.message ||
                (incomingMsg.type === "image"
                    ? "Gambar"
                    : incomingMsg.type === "video"
                    ? "Video"
                    : "File");
            contact.last_message_time = incomingMsg.created_at;

            if (
                !activeContact.value ||
                activeContact.value.id !== incomingMsg.sender_id
            ) {
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

        if (notif.type === "cancel_call" || notif.type === "canceled") {
            // Sesuaikan tipe dari backend
            console.log("Notifikasi cancel diterima!");
            // Panggil fungsi handleCallCancelled dari useVoiceCall
            handleCallCancelled();
            // (Opsional) Hapus notif agar tidak diproses ulang
            // remove(firebaseRef(db, `notifications/${myId}/${notifKey}`));
        }

        if (notif.type === "read_receipt") {
            if (
                activeContact.value &&
                notif.reader_id === activeContact.value.id
            ) {
                messages.value.forEach((msg: any) => {
                    if (msg.sender_id === myId && !msg.read_at) {
                        msg.read_at = notif.read_at;
                    }
                });
            }
        } else if (notif.type === "message_deleted") {
            messages.value = messages.value.filter(
                (m: any) => m.id !== notif.message_id
            );
        }
    });

    const myOnlineRef = firebaseRef(db, `online_users/${myId}`);
    connectedRef = firebaseRef(db, ".info/connected");

    onValue(connectedRef, (snap) => {
        if (snap.val() === true) {
            set(myOnlineRef, true)
                .then(() => {})
                .catch((error) => {});

            onDisconnect(myOnlineRef).remove();
        }
    });

    onlineRef = firebaseRef(db, "online_users");

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

watch(
    messages,
    () => {
        scrollToBottom();
    },
    { deep: true }
);

watch(activeContact, (newVal, oldVal) => {
    if (newVal?.id !== oldVal?.id) {
        setTimeout(() => {
            scrollToBottom();
        }, 100);
    }
    if (typingListenerOff) {
        typingListenerOff();
        typingListenerOff = null;
    }
    if (newVal) {
        isFriendTyping.value = false;
        listenToTypingStatus(newVal.id);
    }
});

onMounted(async () => {
    console.log("🚀 Komponen terpasang");

    setupFirebaseListeners();

    // Expose ke window untuk debug
    if (import.meta.env.DEV) {
        (window as any).authStore = authStore;
        (window as any).callStore = callStore;
        (window as any).showVideoCallingModal = showVideoCallingModal;
        (window as any).showVideoIncomingModal = showVideoIncomingModal;
        (window as any).showVideoCallModal = showVideoCallModal;
        console.log("✅ Debug Variabel diekspos ke window");
    }

    // Init authStore dari current user
    if (!authStore.user && currentUser.value) {
        authStore.setUser(currentUser.value);
        console.log("✅ authStore.user terinisialisasi dari computed");
    }

    requestNotificationPermission();
    await fetchContacts();

    if (currentUser.value) {
        onAuthStateChanged(auth, (firebaseUser) => {
            if (firebaseUser) {
                setupFirebaseListeners();
                axios.post("/chat/heartbeat").catch(() => {});
                heartbeatInterval.value = setInterval(() => {
                    axios.post("/chat/heartbeat").catch(() => {});
                }, 60000);
            } else {
            }
        });
    }

    window.addEventListener("keydown", handleEscKey);

    // Listener for voice call
    const userId = authStore.user?.id;

    if (userId) {
        console.log(`📡 Menghubungkan ke channel: users/${userId}`);

        // Listener untuk panggilan masuk
        const incomingCallRef = dbRef(database, `calls/${userId}/incoming`);

        onValue(incomingCallRef, (snapshot) => {
            const data = snapshot.val();

            if (data) {
                console.log("🔔Firebase: Panggilan masuk:", data);

                // Video call
                if (data.call_type === "video") {
                    if (!authStore.user?.id) {
                        console.error(
                            "❌ authStore.user tidak terdefinisi saat panggilan masuk video"
                        );
                        return;
                    }

                    const incomingCall: Call = {
                        id: data.call_id,
                        type: "video" as CallType,
                        caller: data.caller,
                        receiver: {
                            id: authStore.user.id,
                            name: authStore.user.name,
                            email: authStore.user.email,
                            avatar:
                                authStore.user.photo ||
                                authStore.user.profile_photo_url ||
                                undefined,
                        },
                        status: "ringing" as CallStatus,
                        token: data.agora_token,
                        channel: data.channel_name,
                    };

                    callStore.setIncomingCall(incomingCall);

                    // Set backend call
                    const backendCall: PersonalCall = {
                        id: data.call_id,
                        caller_id: data.caller.id,
                        callee_id: authStore.user.id,
                        call_type: "video",
                        channel_name: data.channel_name,
                        status: "ringing",
                        answered_at: null,
                        ended_at: null,
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                        duration: null,
                        ended_by: null,
                    };

                    callStore.setBackendCall(
                        backendCall,
                        data.agora_token,
                        data.channel_name
                    );

                    console.log("✅ Panggilan video terhandle (Firebase)");
                } else if (data.call_type === "voice") {
                    // Voice call
                    handleIncomingCall(data);
                    console.log("✅ Panggilan suara terhandle (Firebase)");
                }

                // Hapus data dari firebase ketika sudah dibaca
                remove(incomingCallRef);
            }
        });

        // Listener untuk status panggilan
        const statusRef = dbRef(database, `calls/${userId}/status`);

        onValue(statusRef, (snapshot) => {
            const data = snapshot.val();

            if (data) {
                console.log("📡 Firebase: Status panggilan terupdate:", data);

                switch (data.status) {
                    case "accepted":
                        console.log("✅ Firebase: Panggilan diterima");

                        if (data.call_type === "video") {
                            callStore.updateCallStatus("ongoing");
                            callStore.setInCall(true);

                            // Update backend call jika ada
                            if (data.call) {
                                callStore.updateBackendCall(data.call);
                            }
                        } else {
                            handleCallAccepted(data);
                        }

                        // Hapus status dari firebase
                        remove(statusRef);
                        break;

                    case "rejected":
                        console.log("❌ Firebase: Panggilan ditolak");

                        if (data.call_type === "video") {
                            callStore.updateCallStatus("rejected");
                            setTimeout(() => {
                                callStore.clearCurrentCall();
                                callStore.clearIncomingCall();
                            }, 2000);
                        } else {
                            handleCallRejected();
                        }

                        // Hapus status dari firebase
                        remove(statusRef);
                        break;

                    case "cancelled":
                        console.log("❌ Firebase: Panggilan dibatalkan");

                        if (data.call_type === "video") {
                            callStore.updateCallStatus("cancelled");
                            setTimeout(() => {
                                callStore.clearCurrentCall();
                                callStore.clearIncomingCall();
                            }, 2000);
                        } else {
                            // TAMBAHKAN INI UNTUK VOICE CALL
                            console.log(
                                "Eksekusi handleCallCancelled untuk Voice"
                            );
                            handleCallCancelled();
                        }

                        // Hapus status dari firebase
                        remove(statusRef);
                        break;

                    case "ended":
                        console.log("📴 Firebase: Panggilan diakhiri");

                        if (data.call_type === "video") {
                            callStore.updateCallStatus("ended");
                            setTimeout(() => {
                                callStore.clearCurrentCall();
                            }, 2000);
                        } else {
                            handleCallEnded();
                        }

                        remove(statusRef);
                        break;
                }
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
        const myOnlineRef = firebaseRef(
            db,
            `online_users/${currentUser.value.id}`
        );
        remove(myOnlineRef);
    }

    if (heartbeatInterval.value) {
        clearInterval(heartbeatInterval.value);
    }

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
                <div class="card-header pt-7" id="kt_chat_contacts_header">
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
                                placeholder="Cari kontak..."
                            />
                        </form>
                        <button
                            class="btn btn-sm btn-light-primary fw-bold"
                            @click="openAddContactModal"
                        >
                            <KTIcon icon-name="plus" icon-class="fs-2" />
                        </button>
                    </div>
                </div>

                <div class="card-body pt-5" id="kt_chat_contacts_body">
                    <div
                        class="scroll-y me-n5 pe-5 h-200px h-lg-auto"
                        style="max-height: 60vh"
                    >
                        <div v-if="isLoadingContact" class="text-center mt-5">
                            <span
                                class="spinner-border spinner-border-sm text-primary"
                            ></span>
                        </div>
                        <div
                            v-for="contact in contacts"
                            :key="contact.id"
                            @click="selectContact(contact)"
                            class="d-flex align-items-center p-3 mb-2 rounded cursor-pointer contact-item position-relative overflow-hidden"
                            :class="{
                                'bg-light-primary':
                                    activeContact?.id === contact.id,
                            }"
                        >
                            <div class="d-flex align-items-center">
                                <div
                                    class="symbol symbol-40px symbol-circle me-3"
                                >
                                    <img
                                        :src="
                                            contact.photo
                                                ? `/storage/${contact.photo}`
                                                : '/media/avatars/blank.png'
                                        "
                                        alt="image"
                                    />
                                    <div
                                        v-if="contact.is_online"
                                        class="symbol-badge bg-success start-100 top-100 border-4 h-8px w-8px ms-n2 mt-n2"
                                    ></div>
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
                                                {{ contact.display_name }}
                                            </span>

                                            <span
                                                v-if="!contact.is_saved"
                                                class="badge badge-light-warning ms-2 fs-9 flex-shrink-0"
                                            >
                                                Unknown
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
                                                    messageDrafts[
                                                        contact.id
                                                    ]?.trim()
                                                "
                                                class="text-danger fst-italic"
                                            >
                                                <span class="me-1">Draft:</span>
                                                <span class="text-gray-800">{{
                                                    messageDrafts[contact.id]
                                                }}</span>
                                            </span>

                                            <span v-else>
                                                <span
                                                    v-if="
                                                        contact.last_message &&
                                                        contact.last_message_sender_id ===
                                                            currentUser?.id
                                                    "
                                                    class="me-1"
                                                >
                                                    <i
                                                        v-if="
                                                            contact.last_message_read_at
                                                        "
                                                        class="fas fa-check-double text-primary fs-9"
                                                    ></i>
                                                    <i
                                                        v-else
                                                        class="fas fa-check-double text-gray-400 fs-9"
                                                    ></i>
                                                </span>

                                                {{ contact.email }}
                                            </span>
                                        </span>
                                        <span
                                            v-if="contact.unread_count > 0"
                                            class="badge badge-circle badge-primary w-20px h-20px fs-9"
                                        >
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

        <Teleport to="body">
            <VoiceIncomingModal
                v-if="showIncomingModal"
                :caller-name="incomingCallProps.callerName"
                :caller-photo="incomingCallProps.callerPhoto"
                @accept="handleAcceptCall"
                @reject="handleRejectCall"
            />

            <VoiceCallingModal
                v-if="showCallingModal"
                :callee-name="callingModalProps.calleeName"
                :callee-photo="callingModalProps.calleePhoto"
                :call-status="callingModalProps.callStatus"
                @cancel="cancelVoiceCall"
            />

            <VoiceCallModal
                v-if="showOngoingModal"
                :remote-name="ongoingCallProps.remoteName"
                :remote-photo="ongoingCallProps.remotePhoto"
                :is-muted="false"
                :is-speaker-on="false"
                @end-call="handleEndVoiceCall"
                @minimize="callStore.toggleMinimize"
            />

            <VoiceFloating
                v-if="showFloatingModal"
                :remote-name="ongoingCallProps.remoteName"
                :remote-photo="ongoingCallProps.remotePhoto"
                :is-muted="false"
                @maximize="callStore.toggleMinimize"
                @end-call="handleEndVoiceCall"
            />

            <!-- Video call modals tetap sama -->
            <VideoCallingModal v-if="showVideoCallingModal" />
            <VideoIncomingModal v-if="showVideoIncomingModal" />
            <VideoCallModal v-if="showVideoCallModal" />
        </Teleport>

        <div
            class="flex-lg-row-fluid ms-lg-6 ms-xl-10"
            style="min-width: 0"
            :class="showMobileChat ? 'd-block' : 'd-none d-lg-block'"
        >
            <div class="card h-100 overflow-hidden" id="kt_chat_messenger">
                <div
                    v-if="!activeContact"
                    class="card-body d-flex flex-column justify-content-center align-items-center h-100"
                >
                    <div class="symbol symbol-100px mb-5">
                        <img
                            src="/media/illustrations/sketchy-1/2.png"
                            alt="Welcome"
                        />
                    </div>
                    <h3 class="fw-bold text-gray-800">Selamat Datang</h3>
                    <p class="text-muted">
                        Silakan pilih kontak untuk mulai mengobrol.
                    </p>
                </div>

                <div v-else class="d-flex flex-column h-100">
                    <div
                        class="card-header d-flex align-items-center p-3 border-bottom"
                        style="min-height: 70px"
                        v-if="activeContact"
                    >
                        <div class="d-flex align-items-center flex-grow-1">
                            <button
                                v-if="showMobileChat"
                                class="btn btn-sm btn-icon btn-clear d-lg-none me-3"
                                @click="closeMobileChat"
                            >
                                <ArrowLeft
                                    class="w-20px h-20px text-gray-700"
                                />
                            </button>

                            <div class="symbol symbol-40px symbol-circle me-3">
                                <img
                                    :src="
                                        activeContact.photo
                                            ? `/storage/${activeContact.photo}`
                                            : '/media/avatars/blank.png'
                                    "
                                    alt="image"
                                />
                            </div>

                            <div class="d-flex flex-column">
                                <span class="fw-bold text-gray-800 fs-6">
                                    {{ activeContact.display_name }}
                                </span>
                                <span class="text-muted fs-8">
                                    {{
                                        activeContact.is_online
                                            ? "Online"
                                            : formatLastSeen(
                                                  activeContact.last_seen
                                              )
                                    }}
                                </span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button
                                v-if="!activeContact.is_saved"
                                @click="openSaveContactModal(activeContact)"
                                class="btn btn-sm btn-light-primary d-none d-sm-inline-flex align-items-center"
                            >
                                <i class="fas fa-user-plus fs-7 me-1"></i>
                                Simpan
                            </button>

                            <button
                                @click="startVoiceCall(activeContact)"
                                :disabled="voiceProcessing"
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
                                            Info Kontak
                                        </a>
                                    </div>

                                    <div class="menu-item px-3">
                                        <a
                                            href="#"
                                            class="menu-link px-3"
                                            @click.prevent="isMuted = !isMuted"
                                        >
                                            <i
                                                class="fas me-2"
                                                :class="
                                                    isMuted
                                                        ? 'fa-volume-mute'
                                                        : 'fa-volume-up'
                                                "
                                            ></i>
                                            {{
                                                isMuted
                                                    ? "Bunyikan Suara"
                                                    : "Bisukan Suara"
                                            }}
                                        </a>
                                    </div>

                                    <div class="menu-item px-3">
                                        <a
                                            href="#"
                                            class="menu-link px-3"
                                            @click.prevent="openClearChatModal"
                                        >
                                            <i class="fas fa-eraser me-2"></i>
                                            Bersihkan Chat
                                        </a>
                                    </div>

                                    <div class="separator my-2"></div>

                                    <div class="menu-item px-3">
                                        <a
                                            href="#"
                                            class="menu-link px-3"
                                            @click.prevent="openEditModal"
                                        >
                                            <i
                                                class="fas fa-user-edit me-2"
                                            ></i>
                                            Edit Kontak
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
                                    {{ formatDateSeparator(msg.created_at) }}
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
                                                        : activeContact?.name ||
                                                          "Teman"
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
                                                        : msg.reply_to.type ===
                                                          "image"
                                                        ? "Foto"
                                                        : msg.reply_to.type ===
                                                          "video"
                                                        ? "Video"
                                                        : "File")
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
                                                :src="
                                                    msg.file_path.startsWith(
                                                        'blob'
                                                    )
                                                        ? msg.file_path
                                                        : getFileUrl(
                                                              msg.file_path
                                                          )
                                                "
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
                                                class="btn btn-icon btn-sm btn-dark position-absolute bottom-0 end-0 m-2 shadow-sm download-btn"
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
                                                title="Download Gambar"
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
                                                        (
                                                            msg.file_size / 1024
                                                        ).toFixed(0)
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

                                                <span
                                                    v-else-if="msg.read_at"
                                                    title="Dibaca"
                                                >
                                                    <i
                                                        class="fas fa-check-double tick-read fs-9"
                                                    ></i>
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
                                                class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg-light-primary fw-bold w-100px py-1 show position-absolute mt-1 shadow-lg bg-white"
                                                :class="
                                                    msg.sender_id ===
                                                    currentUser?.id
                                                        ? 'start-0'
                                                        : 'end-0'
                                                "
                                                style="z-index: 105"
                                            >
                                                <div class="menu-item px-2">
                                                    <a
                                                        href="#"
                                                        class="menu-link px-2 fs-7"
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

                                                <div class="menu-item px-2">
                                                    <a
                                                        href="#"
                                                        class="menu-link px-2 fs-7 text-danger"
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
                            v-if="isFriendTyping"
                            class="d-flex justify-content-start mb-10"
                        >
                            <div class="d-flex flex-column align-items-start">
                                <div class="d-flex align-items-center mb-2">
                                    <div
                                        class="symbol symbol-35px symbol-circle"
                                    >
                                        <img
                                            :src="
                                                activeContact?.photo
                                                    ? `/storage/${activeContact.photo}`
                                                    : '/media/avatars/blank.png'
                                            "
                                            alt="image"
                                        />
                                    </div>
                                    <div
                                        class="ms-3 p-4 bg-light rounded shadow-sm text-dark fw-bold text-start d-inline-block"
                                        style="
                                            border-bottom-left-radius: 0 !important;
                                            min-width: 60px;
                                        "
                                    >
                                        <div class="typing-indicator">
                                            <span></span>
                                            <span></span>
                                            <span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="replyingTo"
                        class="px-4 py-2 bg-light border-top d-flex justify-content-between align-items-center"
                    >
                        <div
                            class="d-flex flex-column border-start border-4 border-primary ps-2"
                        >
                            <span class="text-primary fw-bold small">
                                {{
                                    replyingTo.sender_id === currentUser?.id
                                        ? "Anda"
                                        : activeContact.name
                                }}
                            </span>

                            <span
                                class="text-muted small text-truncate"
                                style="max-width: 300px"
                            >
                                <i
                                    v-if="replyingTo.type === 'image'"
                                    class="fas fa-camera me-1"
                                ></i>
                                <i
                                    v-else-if="replyingTo.type === 'video'"
                                    class="fas fa-video me-1"
                                ></i>
                                <i
                                    v-else-if="replyingTo.type === 'file'"
                                    class="fas fa-file me-1"
                                ></i>
                                {{
                                    replyingTo.message ||
                                    (replyingTo.type === "image"
                                        ? "Foto"
                                        : "File")
                                }}
                            </span>
                        </div>

                        <button
                            @click="cancelReply"
                            class="btn btn-sm btn-icon btn-light-danger"
                        >
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <transition name="fade">
                        <button
                            v-if="showScrollButton"
                            @click="scrollToBottom"
                            class="btn btn-primary btn-icon shadow-sm rounded-circle position-absolute"
                            style="
                                bottom: 100px;
                                right: 30px;
                                z-index: 10;
                                width: 30px;
                                height: 30px;
                            "
                        >
                            <i class="fas fa-arrow-down fs-4"></i>
                        </button>
                    </transition>
                    <div class="card-footer pt-4 pb-4" style="min-height: 80px">
                        <div class="d-flex align-items-center">
                            <button
                                class="btn btn-sm btn-icon btn-active-light-primary me-2"
                                @click="triggerFileUpload"
                            >
                                <KTIcon
                                    icon-name="paper-clip"
                                    icon-class="fs-3"
                                />
                            </button>
                            <input
                                type="file"
                                ref="fileInput"
                                class="d-none"
                                @change="sendMessage"
                                accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                            />
                            <input
                                v-model="newMessage"
                                @keyup.enter="sendMessage"
                                type="text"
                                @input="
                                    (e) => {
                                        handleTyping();
                                        if (activeContact)
                                            messageDrafts[activeContact.id] =
                                                newMessage;
                                    }
                                "
                                class="form-control form-control-solid me-3"
                                placeholder="Ketik pesan..."
                            />
                            <button
                                class="btn btn-primary btn-icon"
                                @click="sendMessage"
                            >
                                <KTIcon icon-name="send" icon-class="fs-2" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal State -->
    <div v-if="isAddContactOpen" class="modal-overlay">
        <div
            class="modal-content-wrapper bg-white rounded shadow p-0 overflow-hidden"
            style="max-width: 500px; width: 100%"
        >
            <ContactForm
                @close="isAddContactOpen = false"
                @refresh="fetchContacts"
            />
        </div>
    </div>
    <div v-if="isEditContactOpen" class="modal-overlay">
        <div
            class="modal-content-wrapper bg-white rounded shadow p-0 overflow-hidden"
            style="max-width: 500px; width: 100%"
        >
            <EditForm
                v-if="isEditContactOpen"
                :contactId="contactIdToEdit"
                :title="editModalTitle"
                @close="isEditContactOpen = false"
                @updated="handleContactUpdated"
                @refresh="fetchContacts"
            />
        </div>
    </div>
    <div
        v-if="isLightboxOpen"
        class="lightbox-overlay"
        @click.self="closeLightbox"
    >
        <div class="lightbox-content position-relative text-center">
            <button
                @click="closeLightbox"
                class="btn btn-icon btn-sm btn-dark position-absolute top-0 end-0 m-3 shadow z-index-10"
            >
                <i class="fas fa-times fs-2"></i>
            </button>
            <img
                :src="activeLightboxUrl"
                class="img-fluid rounded shadow-lg"
                style="max-height: 85vh; max-width: 90vw"
            />
        </div>
    </div>
    <div v-if="isDeleteModalOpen" class="modal-overlay">
        <div
            class="modal-content bg-white rounded shadow p-5 text-center"
            style="width: 350px"
        >
            <div class="bg-light-danger mb-4">
                <i class="fas fa-trash fs-2 text-danger p-3"></i>
            </div>
            <h3 class="fw-bold text-gray-800 mb-1">Hapus Pesan?</h3>
            <p class="text-muted fs-7 mb-4">
                Pesan yang dihapus tidak dapat dikembalikan.
            </p>
            <div class="d-grid gap-2">
                <button
                    @click="confirmDelete('me')"
                    class="btn btn-light-primary"
                >
                    Hapus untuk saya
                </button>
                <button
                    v-if="messageToDelete?.sender_id === currentUser?.id"
                    @click="confirmDelete('everyone')"
                    class="btn btn-light-danger"
                >
                    Hapus untuk semua orang
                </button>
                <button
                    @click="closeDeleteModal"
                    class="btn btn-link text-muted btn-sm"
                >
                    Batal
                </button>
            </div>
        </div>
    </div>
    <div
        v-if="isInfoModalOpen"
        class="modal-overlay"
        @click.self="isInfoModalOpen = false"
    >
        <div
            class="modal-content bg-white rounded shadow p-0 overflow-hidden"
            style="max-width: 400px; width: 100%"
        >
            <div
                class="modal-header p-4 border-bottom d-flex justify-content-between align-items-center"
            >
                <h3 class="fw-bold m-0">Info Kontak</h3>
                <div
                    class="btn btn-icon btn-sm btn-active-light-primary ms-2"
                    @click="isInfoModalOpen = false"
                >
                    <i class="fas fa-times fs-2"></i>
                </div>
            </div>
            <div class="modal-body p-5 text-center">
                <div class="symbol symbol-100px symbol-circle mb-4">
                    <img
                        :src="
                            activeContact?.photo
                                ? `/storage/${activeContact.photo}`
                                : '/media/avatars/blank.png'
                        "
                        alt="image"
                        style="object-fit: cover"
                    />
                </div>

                <h4 class="fw-bold text-gray-800">
                    {{ activeContact?.alias || activeContact?.name }}
                </h4>
                <div v-if="activeContact?.alias" class="text-muted fs-7">
                    ~ {{ activeContact?.name }}
                </div>
                <div
                    v-if="activeContact?.bio"
                    class="text-gray-600 fs-7 mt-2 px-4 text-break"
                >
                    "{{ activeContact.bio }}"
                </div>

                <div class="text-start bg-light rounded p-4 mt-4">
                    <div class="d-flex mb-3">
                        <i
                            class="fas fa-phone text-gray-500 fs-4 me-3 mt-1"
                        ></i>
                        <div>
                            <div class="fs-7 text-muted">Nomor Telepon</div>
                            <div class="fw-bold text-gray-800">
                                {{ activeContact?.phone }}
                            </div>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <i
                            class="fas fa-envelope text-gray-500 fs-4 me-3 mt-1"
                        ></i>
                        <div>
                            <div class="fs-7 text-muted">Email</div>
                            <div class="fw-bold text-gray-800">
                                {{ activeContact?.email || "-" }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div v-if="isClearChatModalOpen" class="modal-overlay">
        <div
            class="modal-content bg-white rounded shadow p-5 text-center"
            style="width: 350px"
        >
            <div class="bg-light-danger mb-4">
                <i class="fas fa-eraser fs-2 text-danger p-3"></i>
            </div>
            <h3 class="fw-bold text-gray-800 mb-1">Bersihkan Chat?</h3>
            <p class="text-muted fs-7 mb-4">
                Apakah Anda yakin ingin menghapus
                <b>semua riwayat pesan</b> dengan kontak ini? Tindakan ini tidak
                dapat dibatalkan.
            </p>
            <div class="d-grid gap-2">
                <button @click="handleClearChat" class="btn btn-danger">
                    Ya, Bersihkan
                </button>
                <button
                    @click="isClearChatModalOpen = false"
                    class="btn btn-link text-muted btn-sm"
                >
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

.fa-check,
.fa-check-double {
    transition: all 0.2s ease;
}

/* Hover Effects */
.hover-effect {
    transition: background-color 0.2s ease;
}

.hover-effect:hover {
    background-color: #b6c3f0;
}

/* Animasi Typing Indicator */
.typing-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 10px;
}

.typing-indicator span {
    display: block;
    width: 6px;
    height: 6px;
    background-color: #888;
    border-radius: 50%;
    margin: 0 2px;
    animation: typing 1.4s infinite ease-in-out both;
}

.typing-indicator span:nth-child(1) {
    animation-delay: -0.32s;
}

.typing-indicator span:nth-child(2) {
    animation-delay: -0.16s;
}

@keyframes typing {
    0%,
    80%,
    100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}

/* CSS Animasi Loading */
.spin-animation {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
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
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
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

/* toast color */
:root {
    --toastify-text-color-light: #000000 !important;
    --toastify-color-light: #ffffff;
}

.Toastify__toast-theme--light {
    color: #333333 !important;
    background-color: #ffffff !important;
}

.Toastify__close-button--light {
    color: #333333 !important;
    opacity: 0.7;
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
