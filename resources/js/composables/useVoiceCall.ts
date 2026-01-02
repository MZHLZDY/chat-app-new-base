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
            
            console.log('📞 Response dari inviteCall:', response);
            
            store.setCurrentCall(response);
            store.updateCallStatus('calling' as any);
        } catch (error: any) {
            console.error('❌ Error startVoiceCall:', error);
            toast.error(error.response?.data?.message || "Gagal memulai panggilan suara");
        } finally {
            processing.value = false;
        }
    };

    const acceptVoiceCall = async (callId: string) => {
        if (processing.value) return;
        processing.value = true;

        try {
            const callData = await callService.answerCall(callId);
            
            console.log('📞 Response dari answerCall:', callData);
            
            store.setCurrentCall(callData);
            
            if (authStore.user && authStore.user.id) {
                await joinChannel(appId, callData.channel_name, callData.agora_token, authStore.user.id);
                await toggleAudio(false); // Unmute audio
            } else {
                throw new Error("User tidak terautentikasi");
            }
        } catch (error: any) {
            console.error('❌ Error acceptVoiceCall:', error);
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
            console.error('❌ Error rejectVoiceCall:', error);
        }
    };

    const endVoiceCall = async (callId: string | number) => {
        try {
            await leaveChannel();
            await callService.endCall(String(callId)); 
            store.clearCurrentCall();
        } catch (error) {
            console.error('❌ Error endVoiceCall:', error);
        }
    };

    // --- EVENT HANDLERS (FIXED) ---
    
    // ✅ FIX: Tambahkan console.log untuk debug dan pastikan struktur data benar
    const handleIncomingCall = (event: any) => {
        console.log('🔔 handleIncomingCall dipanggil dengan event:', event);
        console.log('🔔 Event.call:', event.call);
        console.log('🔔 Store isInCall sebelum:', store.isInCall);
        
        if (!store.isInCall) {
            // ✅ Pastikan event.call ada dan valid
            if (event && event.call) {
                console.log('✅ Setting incoming call ke store...');
                store.setIncomingCall(event.call);
                console.log('✅ Incoming call berhasil di-set:', store.incomingCall);
            } else {
                console.error('❌ Event atau event.call tidak valid!');
            }
        } else {
            console.log('⚠️ Sudah ada panggilan aktif, incoming call diabaikan');
        }
    };

    const handleCallAccepted = async (event: any) => {
        console.log('✅ handleCallAccepted dipanggil:', event);
        
        if (store.currentCall && store.currentCall.id === event.call.id) {
            store.updateCallStatus('ongoing');

            if (authStore.user && authStore.user.id && event.call.agora_token) {
                await joinChannel(
                    appId, 
                    event.call.channel_name, 
                    event.call.agora_token, 
                    authStore.user.id
                );
                await toggleAudio(false); // Unmute audio
            }
        }
    };

    const handleCallRejected = () => {
        console.log('🚫 handleCallRejected dipanggil');
        toast.info("Panggilan suara ditolak");
        store.clearCurrentCall();
    };

    const handleCallEnded = async () => {
        console.log('❌ handleCallEnded dipanggil');
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