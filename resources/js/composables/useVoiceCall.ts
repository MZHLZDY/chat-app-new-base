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
        // 🔍 Debug: Pastikan data masuk
        console.log("📥 Handle Incoming:", event);

        // KARENA BACKEND MENGIRIM DATA FLAT (tanpa wrapper 'call'),
        // Kita harus mapping manual agar sesuai format Store (Call Interface)
        
        // Cek struktur IncomingCall.php function broadcastWith()
        const mappedCall = {
            id: event.call_id,          // Backend: 'call_id'
            type: event.call_type,      // Backend: 'call_type'
            status: 'calling',          // Default status saat masuk
            channel_name: event.channel_name, // Backend: 'channel_name'
            agora_token: event.agora_token,   // Backend: 'agora_token'
            
            // Backend mengirim object 'caller' berisi {id, name, avatar}
            caller: {
                id: event.caller.id,
                name: event.caller.name,
                avatar: event.caller.avatar || 'default-avatar.png', // Handle null
            },
            
            // Backend mengirim object 'callee' (penerima)
            receiver: {
                id: event.callee?.id,
                name: event.callee?.name,
                avatar: event.callee?.avatar || null
            },

            // Tambahan properti lain jika Store membutuhkannya
            created_at: new Date().toISOString()
        };

        if (!store.isInCall) {
            // Masukkan data hasil mapping ke store
            store.setIncomingCall(mappedCall as any); 
            
            // Opsional: Mainkan ringtone disini jika belum ada
        } else {
            console.log("⚠️ Sedang dalam panggilan, mengabaikan panggilan baru.");
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