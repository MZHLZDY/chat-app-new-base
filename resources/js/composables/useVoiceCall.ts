import { ref } from "vue";
import { useCallStore } from "@/stores/callStore";
import { useAuthStore } from "@/stores/auth";
import { useAgora } from "./useAgora";
import * as callService from "@/services/callServices";
import type { CallType } from "@/types/call";
import { toast } from "vue3-toastify";

export const useVoiceCall = () => {
    const store = useCallStore();
    const authStore = useAuthStore();
    
    const { 
        joinChannel, 
        leaveChannel, 
        toggleAudio, 
        toggleVideo, 
        localAudioTrack 
    } = useAgora();
    
    const processing = ref(false);
    
    // @ts-ignore
    const appId = import.meta.env.VITE_AGORA_APP_ID;

    // --- ACTIONS ---

    const startVoiceCall = async (receiverId: number, type: CallType = 'voice') => {
        if (processing.value) return;
        processing.value = true;

        try {
            const response = await callService.inviteCall(receiverId, type);
            store.setCurrentCall(response.data);
        } catch (error: any) {
            console.error(error);
            toast.error(error.response?.data?.message || "Gagal memulai panggilan suara");
        } finally {
            processing.value = false;
        }
    };

    const acceptVoiceCall = async (callId: string) => {
        if (processing.value) return;
        processing.value = true;

        try {
            const response = await callService.answerCall(callId);
            const callData = response.data;
            store.setCurrentCall(callData);
            
            if (authStore.user && authStore.user.id) {
                await joinChannel(appId, callData.channel, callData.token, authStore.user.id);
                await toggleVideo(true);
            } else {
                throw new Error("User tidak terautentikasi");
            }
        } catch (error: any) {
            console.error(error);
            toast.error(error.message || "Gagal menjawab panggilan");
            store.clearIncomingCall();
        } finally {
            processing.value = false;
        }
    };

    const rejectVoiceCall = async (callId: string) => {
        try {
            await callService.rejectCall(callId);
            store.clearIncomingCall();
        } catch (error) {
            console.error(error);
        }
    };

    const endVoiceCall = async (callId: string | number) => {
        try {
            await leaveChannel();
            await callService.endCall(String(callId)); 
            store.clearCurrentCall();
        } catch (error) {
            console.error(error);
        }
    };

    // --- EVENT HANDLERS ---
    
    const handleIncomingCall = (event: any) => {
        if (!store.isInCall) {
            store.setIncomingCall(event.call);
        } 
    };

    const handleCallAccepted = async (event: any) => {
        if (store.currentCall && store.currentCall.id === event.call.id) {
            store.updateCallStatus('ongoing');

            if (authStore.user && authStore.user.id && event.call.token) {
                await joinChannel(appId, event.call.channel, event.call.token, authStore.user.id);
                await toggleVideo(true); 
            }
        }
    };

    const handleCallRejected = () => {
        toast.info("Panggilan suara ditolak");
        store.clearCurrentCall();
    };

    const handleCallEnded = async () => {
        await leaveChannel();
        store.clearCurrentCall();
        toast.info("Panggilan berakhir");
    };

    return {
        startVoiceCall,
        acceptVoiceCall,
        rejectVoiceCall,
        endVoiceCall,
        handleIncomingCall,
        handleCallAccepted,
        handleCallRejected,
        handleCallEnded,
        toggleAudio,
        toggleVideo,
        localAudioTrack,
        processing,
    };
};