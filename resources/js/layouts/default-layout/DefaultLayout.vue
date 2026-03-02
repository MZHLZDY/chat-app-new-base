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
import { ref as dbRef, onValue, remove } from 'firebase/database';
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
// --- Group Call Import ---
import { useVoiceGroupCall } from "@/composables/useVoiceGroupCall";
import VoiceGroupCallingModal from "@/components/call/voice/VoiceGroupCallingModal.vue";
import VoiceGroupIncomingModal from "@/components/call/voice/VoiceGroupIncomingModal.vue";
import VoiceGroupCallModal from "@/components/call/voice/VoiceGroupCallModal.vue";
import VoiceGroupFloating from "@/components/call/voice/VoiceGroupFloating.vue";

// --- State Utama ---
const route = useRoute();
const currentUser = computed(() => authStore.user);
const authStore = useAuthStore();
const activeContact = ref<any>(null);

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
    leaveGroupVoiceCall 
} = useVoiceGroupCall();    

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

onBeforeMount(() => {
    LayoutService.init();
});

let incomingUnsub: (() => void) | undefined;
let statusUnsub: (() => void) | undefined;

onMounted(() => {
    nextTick(() => {
        reinitializeComponents();
    });

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

  // Listener panggilan masuk
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
    }  else if (data.call_type === 'voice') {
      // 🔥 TAMBAHKAN INI
      handleIncomingCall(data);
    }
      // Hapus setelah dibaca
      remove(incomingCallRef);
    }
  });

  // Listener status panggilan
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
                                // Caller join channel setelah diterima
                                if (
                                    callStore.agoraToken &&
                                    callStore.channelName &&
                                    authStore.user?.id
                                ) {
                                    console.log(
                                        "👋 Caller bergabung ke channel"
                                    );

                                    const { joinChannel } = useAgora();
                                    await joinChannel(
                                        callStore.channelName,
                                        callStore.agoraToken,
                                        Number(authStore.user.id)
                                    );

                                    console.log(
                                        "✅ Caller berhasil bergabung ke channel"
                                    );
                                }

                                // Update backend call jika ada
                                if (data.call) {
                                    callStore.updateBackendCall(data.call);
                                }
                            })();
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
                            (async () => {
                                try {
                                    // Cleanup agora dulu sebelum clear store
                                    const { leaveChannel } = useAgora();

                                    console.log(
                                        "👋 Meninggalkan channel Agora (Remote diberhentikan)"
                                    );
                                    await leaveChannel();

                                    console.log(
                                        "🧹 Membersihkan call store..."
                                    );
                                    callStore.updateCallStatus("ended");

                                    // Clear setelah 2 detik supaya user bisa lihat status ended
                                    setTimeout(() => {
                                        callStore.clearCurrentCall();
                                    }, 2000);
                                } catch (error) {
                                    console.error(
                                        "❌ Error pada saat membersihkan panggilan yang berakhir:",
                                        error
                                    );

                                    // Cleanup secara paksa jika ada error
                                    callStore.clearCurrentCall();
                                    callStore.clearIncomingCall();
                                }
                            })();
                        } else {
                         // VOICE CALL - Immediate response
                         console.log("🎤 Voice call ended via Firebase");
        
                         // Langsung update UI tanpa delay
                         callStore.updateCallStatus('ended');
        
                         // Tampilkan toast segera
                         toast.info("Panggilan berakhir");
        
                         // Async cleanup (biarkan berjalan di background)
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
    
                   remove(statusRef);
                   break;
      }
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
                :is-muted="!isAudioEnabled"
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
            <VideoCallModal v-if="showVideoCallModal" @minimize="callStore.toggleMinimize" />
            <VideoFloating
                v-if="showVideoFloatingModal"
                @maximize="callStore.toggleMinimize"
                @end-call="handleEndVoiceCall"
            />
    </Teleport>
</template>
