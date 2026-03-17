<script setup lang="ts">
import { nextTick, onBeforeMount, onMounted, onUnmounted, watch, computed, ref } from "vue";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import KTHeader from "@/layouts/default-layout/components/header/Header.vue";
import KTSidebar from "@/layouts/default-layout/components/sidebar/Sidebar.vue";
import KTContent from "@/layouts/default-layout/components/content/Content.vue";
import KTToolbar from "@/layouts/default-layout/components/toolbar/Toolbar.vue";
import KTScrollTop from "@/layouts/default-layout/components/extras/ScrollTop.vue";
import { useRoute } from "vue-router";
import { reinitializeComponents } from "@/core/plugins/keenthemes";
import LayoutService from "@/core/services/LayoutService";
import { useAuthStore } from '@/stores/authStore';
import { useCallStore } from '@/stores/callStore';
import { useVoiceCall } from '@/composables/useVoiceCall';
import { usePersonalCall } from "@/composables/usePersonalCall";
import { useAgora } from '@/composables/useAgora';
import { database } from '@/libs/firebase';
import { ref as dbRef, onValue, remove, off } from 'firebase/database';
// Call import
import type { PersonalCall, User } from "@/types/call";
import VoiceIncomingModal from '@/components/call/voice/VoiceIncomingModal.vue';
import VoiceCallingModal from '@/components/call/voice/VoiceCallingModal.vue';
import VoiceCallModal from '@/components/call/voice/VoiceCallModal.vue';
import VoiceFloating from '@/components/call/voice/VoiceFloating.vue';
import VideoCallingModal from '@/components/call/video/VideoCallingModal.vue';
import VideoIncomingModal from '@/components/call/video/VideoIncomingModal.vue';
import VideoCallModal from '@/components/call/video/VideoCallModal.vue';
import VideoFloating from '@/components/call/video/VideoFloating.vue';
// --- Upgrade Call Import ---
import AddParticipantModal from '@/components/call/shared/AddParticipantModal.vue';
// --- Group Call Import ---
import { useVoiceGroupCall } from "@/composables/useVoiceGroupCall";
import VoiceGroupCallingModal from "@/components/call/voice/VoiceGroupCallingModal.vue";
import VoiceGroupIncomingModal from "@/components/call/voice/VoiceGroupIncomingModal.vue";
import VoiceGroupCallModal from "@/components/call/voice/VoiceGroupCallModal.vue";
import VoiceGroupFloating from "@/components/call/voice/VoiceGroupFloating.vue";
import { useVideoGroupCall } from '@/composables/useVideoGroupCall';
import VideoGroupCallingModal from '@/components/call/video/VideoGroupCallingModal.vue';
import VideoGroupIncomingModal from '@/components/call/video/VideoGroupIncomingModal.vue';
import VideoGroupCallModal from '@/components/call/video/VideoGroupCallModal.vue';

// --- State Utama ---
const route = useRoute();
const currentUser = computed(() => authStore.user);
const authStore = useAuthStore();
const groups = ref<any[]>([]);
const activeContact = ref<any>(null);
const activeGroup = ref<any>(null);
const showAddParticipantModal = ref(false);

// --- CALL LOGIC ---
const callStore = useCallStore();
const { isAudioEnabled } = useAgora();
const {
    initiateCall,
    answerCall,
    rejectCall,
    endCall,
    isLoading: callProcessing,
} = usePersonalCall();
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
        callStore.currentCall?.type === "voice" &&
        (callStore.callStatus as string) === "ongoing" &&
        !callStore.isMinimized
);
const showFloatingModal = computed(
    () => isCallActive.value && callStore.isMinimized && callStore.currentCall?.type === 'voice'
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
    endCallAsCaller,
    endCallAsCallee,
    cancelVoiceCall,
    toggleAudio,
    // toggleVideo,
    handleIncomingCall,
    handleCallAccepted,
    handleCallRejected,
    // synchronizedEndCall,
    handleCallEnded,
    handleCallCancelled,
    processing: voiceProcessing,
} = useVoiceCall();

const { 
    startGroupVoiceCall, 
    answerGroupVoiceCall, 
    rejectGroupVoiceCall, 
    leaveGroupVoiceCall,
    endGroupVoiceCallForAll,
    handleGroupIncomingCall,
    handleGroupVoiceCallAnswered,
    handleGroupCallCancelled,
    handleGroupCallEnded,
    handleGroupParticipantLeft,
    handleGroupParticipantRecalled,
} = useVoiceGroupCall();    

// State untuk video call group
const { 
    startGroupVideoCall,
    handleGroupIncomingCall: handleVideoGroupIncomingCall,
    handleGroupCallAnswered: handleVideoGroupCallAnswered,
    handleGroupParticipantLeft: handleVideoGroupParticipantLeft,
    handleGroupParticipantRecalled: handleVideoGroupParticipantRecalled,
    handleGroupCallEnded: handleVideoGroupCallEnded,
    handleGroupCallCancelled: handleVideoGroupCallCancelled,
} = useVideoGroupCall();

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
    await acceptVoiceCall();
};

const handleRejectCall = async () => {
    if (!callStore.incomingCall) return;
    await rejectVoiceCall();
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

// --- HANDLER END CALL (SIMPLE & ROBUST) ---
const handleEndVoiceCall = async () => {
    // Ambil ID dari incomingCall (saat ringing) atau currentCall (saat ongoing)
    // Prioritas: Current Call (Ongoing) -> Incoming Call (Ringing)
    const callId = callStore.currentCall?.id || callStore.incomingCall?.id;
    
    console.log('📞 User pressed End Call. Target ID:', callId);

    // Langsung panggil fungsi force stop tadi
    // Tidak perlu cek "if callStore.currentCall", langsung eksekusi saja.
    await endVoiceCall(callId);
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

const showVideoCallModal = computed(() =>
    isCallActive.value &&
    callStore.currentCall?.type === "video" &&
    callStore.callStatus === "ongoing" &&
    !callStore.isMinimized
);

const showVideoFloatingModal = computed(() => 
    isCallActive.value &&
    callStore.callStatus === 'ongoing' &&
    callStore.currentCall?.type === 'video' &&
    callStore.isMinimized
);

// Group Call Functions
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
    const groupMembers = activeGroup.value.users || activeGroup.value.members || [];

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


// 🔥 FUNGSI BARU: Logika End Call khusus Grup
const handleEndGroupVoiceCall = async () => {
    const callId = callStore.currentCall?.id || callStore.backendGroupCall?.id;
    if (!callId) return;

    // Deteksi apakah user yang sedang login adalah Host/Caller
    // ✅ PERBAIKAN: Ubah caller_id menjadi host_id
    const isHost = callStore.currentCall?.caller?.id === authStore.user?.id || 
                   callStore.backendGroupCall?.host_id === authStore.user?.id; 

    if (isHost) {
        // Jika Host yang mematikan, bubarkan untuk semua
        console.log("👑 Host membubarkan panggilan grup");
        await endGroupVoiceCallForAll(callId);
    } else {
        // Jika peserta biasa, sekadar keluar dari panggilan
        console.log("🚶‍♂️ Peserta keluar dari panggilan grup");
        await leaveGroupVoiceCall(callId);
    }
};

// Fungsi video call group
const handleStartVideoGroupCall = async () => {
    if (!activeGroup.value) {
        toast.error("Pilih grup terlebih dahulu");
        return;
    }

    const groupMembers = activeGroup.value.users || activeGroup.value.members || [];
    
    // Ambil ID peserta selain Host (diri sendiri)
    const participantIds = groupMembers
        .filter((member: any) => member.id !== currentUser.value?.id)
        .map((member: any) => member.id);

    if (participantIds.length === 0) {
        toast.error("Tidak ada peserta lain di grup ini");
        return;
    }

    // Set nama grup & foto khusus untuk state
    callStore.activeGroupName = activeGroup.value.name;
    callStore.activeGroupAvatar = activeGroup.value.avatar || activeGroup.value.photo || '';

    // Panggil aksi dari composable! 🚀
    await startGroupVideoCall(activeGroup.value.id, participantIds);
};

// Tambahkan ini untuk memformat data partisipan agar disetujui oleh TypeScript
const formattedGroupParticipants = computed(() => {
    return callStore.groupParticipants.map((p: any) => {
        const user = p.user || {};
        return {
            id: p.user_id || user.id || p.id,
            name: user.name || 'Unknown',
            avatar: user.photo || user.avatar || user.profile_photo_url || '',
            status: p.status || 'ringing'
        };
    });
});

// --- [TS FIX] Cast incomingCall ke any untuk mengakses custom properti ---
const incomingCallAsAny = computed(() => callStore.incomingCall as any);

onBeforeMount(() => {
    LayoutService.init();
});

let incomingUnsub: (() => void) | undefined;
let statusUnsub: (() => void) | undefined;
let upgradeListenerRef: any = null;

watch(() => callStore.currentCall?.id, (newCallId, oldCallId) => {
    // Bersihkan listener lama jika panggilan berganti/selesai
    if (oldCallId && upgradeListenerRef) {
        off(upgradeListenerRef);
        upgradeListenerRef = null;
    }

    // Jika ada panggilan personal sedang aktif
    if (newCallId) {
        upgradeListenerRef = dbRef(database, `calls/${newCallId}/upgrade`);
        
        onValue(upgradeListenerRef, async (snapshot) => {
            const data = snapshot.val();
            
            if (data && data.is_upgraded && data.group_call) {
                console.log("📡 [Firebase] Panggilan di-upgrade ke Group Call:", data);
                toast.info("Panggilan dialihkan ke Panggilan Grup...", { autoClose: 3000 });

                // Hapus trigger agar tidak berulang
                await remove(upgradeListenerRef);

                // Eksekusi Transisi UI
                callStore.clearCurrentCall();
                callStore.setBackendGroupCall(
                    data.group_call, 
                    data.group_call?.token || data.token || '', 
                    data.group_call?.channel_name || data.channel_name || ''
                );
                callStore.isGroupCall = true;
            }
        });
    }
}, { immediate: true });

onMounted(() => {
    nextTick(() => {
        reinitializeComponents();
    });

    const callId = callStore.backendGroupCall?.id || callStore.currentCall?.id;
    if (callId) {
        // 🔥 Dengarkan perubahan data seluruh peserta di Firebase
        const participantsRef = dbRef(database, `group_calls/${callId}/participants`);
        
        onValue(participantsRef, (snapshot) => {
            if (snapshot.exists()) {
                const data = snapshot.val();
                
                // Looping data Firebase dan update status tiap peserta di store lokal
                Object.keys(data).forEach((key) => {
                    const userId = Number(key);
                    const newStatus = data[key].status;
                    
                    // Pastikan kamu punya fungsi ini di callStore untuk mengubah status peserta
                    callStore.updateGroupParticipantStatus(userId, newStatus);
                });
            }
        });
    }

    if (import.meta.env.DEV) {
        (window as any).authStore = authStore;
        (window as any).callStore = callStore;
        (window as any).showVideoCallingModal = showVideoCallingModal;
        (window as any).showVideoIncomingModal = showVideoIncomingModal;
        (window as any).showVideoCallModal = showVideoCallModal;
        console.log("✅ Debug Variabel diekspos ke window");
    }

    const userId = authStore.user?.id;
    if (!userId) return;

    // --------------------------------------------------
    // A. LISTENER PANGGILAN MASUK (INCOMING)
    // --------------------------------------------------
    const incomingCallRef = dbRef(database, `calls/${userId}/incoming`);
    incomingUnsub = onValue(incomingCallRef, (snapshot) => {
        const data = snapshot.val();
        if (data) {
            if (data.call_type === 'video') {
                // Buat objek incoming call
                const incomingCall = {
                    id: data.call_id,
                    type: 'video' as const,
                    caller: data.caller,
                    receiver: {
                        id: authStore.user!.id,
                        name: authStore.user!.name,
                        email: authStore.user!.email,
                        avatar: authStore.user!.photo || authStore.user!.profile_photo_url
                    },
                    status: 'ringing' as const,
                    token: data.agora_token,
                    channel: data.channel_name
                };
                callStore.setIncomingCall(incomingCall);

                // Buat backend call (PersonalCall)
                const backendCall: PersonalCall = {
                    id: data.call_id,
                    caller_id: data.caller.id,
                    callee_id: authStore.user!.id,
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
                callStore.setBackendCall(backendCall, data.agora_token, data.channel_name);

            } else if (data.call_type === 'voice') {
                handleIncomingCall(data);
            } else if (data.call_type === 'group_voice') {
                handleGroupIncomingCall(data);
            } else if (data.call_type === 'group_video') {
                // Pastikan fungsi handleVideoGroupIncomingCall sudah di-import/ada
                handleVideoGroupIncomingCall(data); 
            }
            
            // Hapus setelah data dibaca
            remove(incomingCallRef);
        }
    });

    // --------------------------------------------------
    // B. LISTENER STATUS PANGGILAN
    // --------------------------------------------------
    const statusRef = dbRef(database, `calls/${userId}/status`);
    statusUnsub = onValue(statusRef, (snapshot) => {
        const data = snapshot.val();
        if (data) {
            switch (data.status) {
                case "accepted":
                    console.log("✅ Firebase: Panggilan diterima");

                    if (data.call_type === "video") {
                        callStore.updateCallStatus("ongoing");
                        callStore.setInCall(true);

                        (async () => {
                            if (callStore.agoraToken && callStore.channelName && authStore.user?.id) {
                                console.log("👋 Caller bergabung ke channel");
                                const { joinChannel } = useAgora();
                                await joinChannel(
                                    callStore.channelName,
                                    callStore.agoraToken,
                                    Number(authStore.user.id)
                                );
                                console.log("✅ Caller berhasil bergabung ke channel");
                            }
                            if (data.call) callStore.updateBackendCall(data.call);
                        })();
                    } else if (data.call_type === "group_voice" || data.call_type === "group_video") {
                        console.log('✅ Panggilan grup diterima');
                        handleGroupVoiceCallAnswered(data);
                        callStore.clearIncomingCall();
                    } else {
                        handleCallAccepted(data);
                    }
                    break;

                case "rejected":
                    console.log("❌ Firebase: Panggilan ditolak");

                    if (data.call_type === "video") {
                        callStore.updateCallStatus("rejected");
                        setTimeout(() => {
                            callStore.clearCurrentCall();
                            callStore.clearIncomingCall();
                        }, 2000);
                    } else if (data.call_type === "group_voice" || data.call_type === "group_video") {
                        callStore.clearIncomingCall();
                    } else {
                        handleCallRejected();
                    }
                    break;

                case "missed":
                case "cancelled":
                    console.log(`❌ Firebase: Panggilan ${data.status}`);

                    callStore.clearIncomingCall();

                    if (data.call_type === "video") {
                        callStore.updateCallStatus(data.status);
                        setTimeout(() => {
                            callStore.clearCurrentCall();
                        }, 2000);
                    } else if (data.call_type === "group_voice" || data.call_type === "group_video") {
                        console.log('Panggilan group dibatalkan oleh caller');
                        handleGroupCallCancelled(data);
                    } else {
                        console.log("Eksekusi handleCallCancelled untuk Voice");
                        handleCallCancelled();
                    }
                    break;

                case "ended":
                    console.log("📴 Firebase: Panggilan diakhiri");

                    if (data.call_type === "video") {
                        (async () => {
                            try {
                                const { leaveChannel } = useAgora();
                                console.log("👋 Meninggalkan channel Agora (Remote diberhentikan)");
                                await leaveChannel();
                                
                                console.log("🧹 Membersihkan call store...");
                                callStore.updateCallStatus("ended");

                                setTimeout(() => {
                                    callStore.clearCurrentCall();
                                }, 2000);
                            } catch (error) {
                                console.error("❌ Error pada saat membersihkan panggilan yang berakhir:", error);
                                callStore.clearCurrentCall();
                                callStore.clearIncomingCall();
                            }
                        })();
                    } else if (data.call_type === "group_voice" || data.call_type === "group_video") {
                        console.log('Panggilan grup diakhiri');
                        handleGroupCallEnded(data);
                    } else {
                        // VOICE CALL
                        console.log("🎤 Voice call ended via Firebase");
                        callStore.updateCallStatus('ended');
                        toast.info("Panggilan berakhir");
                        
                        setTimeout(async () => {
                            try {
                                const { leaveChannel } = useAgora();
                                await leaveChannel();
                                callStore.clearCurrentCall();
                                callStore.clearIncomingCall();
                            } catch (error) {
                                console.error("Cleanup error:", error);
                            }
                        }, 100);
                    }
                    break;
            }
            
            // Hapus status dari firebase (Cukup ditulis 1 kali di akhir setelah blok switch selesai diproses)
            remove(statusRef);
        }
    });
});

onUnmounted(() => {
  if (incomingUnsub) incomingUnsub();
  if (statusUnsub) statusUnsub();
});

watch(
    () => route.path,
    () => {
        nextTick(() => {
            reinitializeComponents();
        });
    }
);
</script>

<template>
    <!--begin::App-->
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <!--begin::Page-->
        <div class="app-page flex-column flex-column-fluid" id="kt_app_page">
            <KTHeader />
            <!--begin::Wrapper-->
            <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">
                <KTSidebar />
                <!--begin::Main-->
                <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                    <!--begin::Content wrapper-->
                    <div class="d-flex flex-column flex-column-fluid">
                        <KTToolbar />
                        <div id="kt_app_content" class="app-content flex-column-fluid">
                            <KTContent></KTContent>
                        </div>
                    </div>
                    <!--end::Content wrapper-->
                </div>
                <!--end:::Main-->
            </div>
            <!--end::Wrapper-->
        </div>
        <!--end::Page-->
    </div>
    <!--end::App-->
    <KTScrollTop />
     <!-- Global Call Modals -->
    <Teleport to="body">
            <VoiceIncomingModal
                v-if="!callStore.isGroupCall && callStore.incomingCall && callStore.incomingCall.type === 'voice'"
                :callerName="callStore.incomingCall.caller?.name || 'Seseorang'"
                :callerPhoto="callStore.incomingCall.caller?.photo || callStore.incomingCall.caller?.avatar || ''"
                :callStatus="callStore.incomingCall.isGroup ? 'Mengundang Anda ke Panggilan Grup...' : 'Panggilan Suara Masuk...'"
                @accept="() => {
                    if (callStore.incomingCall?.isGroup) {
                        answerGroupVoiceCall(callStore.incomingCall.id);
                    } else {
                        handleAcceptCall();
                    }
                }"
                @reject="() => {
                    if (callStore.incomingCall?.isGroup) {
                        rejectGroupVoiceCall(callStore.incomingCall.id);
                    } else {
                        handleRejectCall();
                    }
                }"
            />

            <VoiceCallingModal
                v-if="!callStore.isGroupCall && callStore.currentCall && callStore.callStatus === 'calling' && !callStore.isMinimized"
                :callee-name="callingModalProps.calleeName"
                :callee-photo="callingModalProps.calleePhoto"
                :call-status="callingModalProps.callStatus"
                @cancel="cancelVoiceCall"
            />

            <VoiceCallModal
                v-if="callStore.currentCall && callStore.currentCall.type === 'voice' && callStore.callStatus === 'ongoing' && !callStore.isGroupCall && !callStore.isMinimized"
                :remote-name="ongoingCallProps.remoteName"
                :remote-photo="ongoingCallProps.remotePhoto"
                :is-muted="!isAudioEnabled"
                :is-speaker-on="false"
                @end-call="handleEndVoiceCall"
                @minimize="callStore.toggleMinimize"
                @addParticipant="showAddParticipantModal = true"
            />

            <VoiceFloating
                v-if="callStore.currentCall && callStore.currentCall.type === 'voice' && callStore.callStatus === 'ongoing' && !callStore.isGroupCall && callStore.isMinimized"
                :remote-name="ongoingCallProps.remoteName"
                :remote-photo="ongoingCallProps.remotePhoto"
                :is-muted="false"
                @maximize="callStore.toggleMinimize"
                @end-call="handleEndVoiceCall"
            />

            <!-- Video call modals tetap sama -->
            <VideoCallingModal v-if="showVideoCallingModal" />
            <VideoIncomingModal v-if="showVideoIncomingModal" />
            <VideoGroupIncomingModal/>
            <VideoGroupCallingModal v-if="callStore.isGroupCall && callStore.currentCall?.type === 'video' && !callStore.isMinimized" />
            <VideoGroupCallModal/>
            <VideoCallModal v-if="callStore.currentCall && callStore.currentCall.type === 'video' && callStore.callStatus === 'ongoing' && !callStore.isGroupCall && !callStore.isMinimized" 
              @minimize="callStore.toggleMinimize" 
            />
            <VideoFloating
                v-if="callStore.currentCall && callStore.currentCall.type === 'video' && callStore.callStatus === 'ongoing' && !callStore.isGroupCall && callStore.isMinimized"
                @maximize="callStore.toggleMinimize"
                @end-call="handleEndVoiceCall"
            />
            <VoiceGroupFloating />

            <VoiceGroupCallingModal
                v-if="callStore.isGroupCall && callStore.callStatus === 'calling' && !callStore.isMinimized && (callStore.currentCall?.type === 'voice' || callStore.backendGroupCall?.call_type === 'voice')"
                :groupName="callStore.backendGroupCall?.group?.name || callStore.activeGroupName || 'Group Call'"
                :groupPhoto="callStore.backendGroupCall?.group?.photo || callStore.backendGroupCall?.group?.avatar || callStore.activeGroupAvatar || ''"
                :participants="formattedGroupParticipants" 
                :callStatus="callStore.callStatus"
                @cancel="leaveGroupVoiceCall(callStore.currentCall?.id || callStore.backendGroupCall?.id || 0)" 
            />

        <VoiceGroupIncomingModal
            v-if="callStore.incomingCall && callStore.incomingCall.isGroup && callStore.incomingCall.type === 'voice'"
            :groupName="incomingCallAsAny.groupName || 'Group Call'"
            :groupPhoto="incomingCallAsAny.groupAvatar || ''"
            :inviterName="callStore.incomingCall.caller?.name"
            :participants="incomingCallAsAny.participants || []"
            @accept="() => answerGroupVoiceCall(callStore.incomingCall!.id)"
            @reject="() => rejectGroupVoiceCall(callStore.incomingCall!.id)"
        />

        <VoiceGroupCallModal
            v-if="callStore.isGroupCall && callStore.callStatus === 'ongoing' && !callStore.isMinimized && (callStore.currentCall?.type === 'voice' || callStore.backendGroupCall?.call_type === 'voice')"
            @end-call="handleEndGroupVoiceCall"
            @leave="() => leaveGroupVoiceCall(callStore.currentCall?.id || callStore.backendGroupCall?.id || 0)"
            @end-all="() => endGroupVoiceCallForAll(callStore.currentCall?.id || callStore.backendGroupCall?.id || 0)"
            @minimize="callStore.toggleMinimize"
        />

        <AddParticipantModal 
            v-if="showAddParticipantModal" 
            @close="showAddParticipantModal = false" 
        />

    </Teleport>
</template>
