import { ref } from "vue";
import { useCallStore } from "@/stores/callStore";
import { useAgora } from "./useAgora";
import * as callService from "@/services/callServices";
import type { Call, CallType } from "@/types/call";
import { usePage } from "@inertiajs/vue3";
import { toast } from "vue3-toastify/index";

export const useVideoCall = () => {
    const store = useCallStore();
    const { joinChannel, leaveChannel, toggleAudio, toggleVideo, localAudioTrack, localVideoTrack } = useAgora();
    const processing = ref(false);

    // Dapatkan otentikasi user saat ini (dari inertia shared props atau manapun kamu simpan user auth)
    const page = usePage();
    const currentUser = page.props.auth ? (page.props.auth as any).user : null;
    // @ts-ignore
    const appId = import.meta.env.VITE_AGORA_APP_ID;

    // Start / Invite call
    const startCall = async (receiverId: number, type: CallType = 'video') => {
        if (processing.value) return;
        processing.value = true;

        try {
            const response = await callService.inviteCall(receiverId, type);
            // Simpan call ke store dengan status ringing
            store.setCurrentCall(response.data);

        } catch (error: any) {
            console.error(error);
            toast.error(error.response?.data?.message || "Gagal memulai panggilan");
        } finally {
            processing.value = false;
        }
    };

    // Accept call
    const acceptCall = async (callId: string) => {
        if (processing.value) return;
        processing.value = true;

        try {
            const response = await callService.answerCall(callId);
            const callData = response.data;
            store.setCurrentCall(callData);
            
            // Join channel agora sekarang
            if (currentUser) {
                await joinChannel(appId, callData.channel, callData.token, currentUser.id);
            }

        } catch (error: any) {
            console.error(error);
            toast.error("Gagal menjawab panggilan");
            store.clearIncomingCall();
        } finally {
            processing.value = false;
        }
    };

    // Menolak panggilan masuk
    const rejectCall = async (callId: string) => {
        try {
            await callService.rejectCall(callId);
            store.clearIncomingCall();

        } catch (error) {
            console.error(error);
        }
    };

    // Mengakhiri panggilan
    const endCall = async (callId: string) => {
        try {
            // Leave agora dulu biar feedback UI nya cepet
            await leaveChannel();

            // Hit API backend
            await callService.endCall(callId);

            // Cleanup store
            store.clearCurrentCall();

        } catch (error) {
            console.error(error);
        }
    };

    // Handle websocket events (nanti dipanggil di main app / layout)
    const handleIncomingCall = (event: any) => {
        // Cek kalau kita lagi ga di panggilan lain
        if (!store.isInCall) {
            store.setIncomingCall(event.call);
            // Buynikan nada dering (jika ada / opsional)
        } else {
            // Otomatis ditolak jika tidak dijawab / mati karena timeout
        }
    };

    const handleCallAccepted = async (event: any) => {
        // Update status di store
        if (store.currentCall && store.currentCall.id === event.call.id) {
            store.updateCallStatus('ongoing');

            if (currentUser && event.call.token) {
                await joinChannel(appId, event.call.channel, event.call.token, currentUser.id);
            }
        }
    };

    const handleCallRejected = () => {
        toast.info("Panggilan ditolak");
        store.clearCurrentCall();
        // Suara memanggil berhenti
    };

    const handleCallEnded = async () => {
        await leaveChannel();
        store.clearCurrentCall();
        toast.info("Panggilan berakhir");
    };

    return {
        startCall,
        acceptCall,
        rejectCall,
        endCall,
        handleIncomingCall,
        handleCallAccepted,
        handleCallRejected,
        handleCallEnded,
        toggleAudio,
        toggleVideo,
        localAudioTrack,
        localVideoTrack,
        processing,
    };
};