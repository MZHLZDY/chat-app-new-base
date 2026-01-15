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
    
    // --- ACTIONS ---

    const startVoiceCall = async (receiver: any, type: CallType = 'voice') => {
        if (processing.value) return;
        processing.value = true;

        // Debug: Log receiver data lengkap
        console.log('🔍 DEBUG - Receiver data:', receiver);
        console.log('🔍 DEBUG - Receiver type:', typeof receiver);
        console.log('🔍 DEBUG - Receiver.id:', receiver?.id);
        console.log('🔍 DEBUG - Receiver keys:', receiver ? Object.keys(receiver) : 'null');

        // Validasi receiver dengan lebih fleksibel
        // Cek berbagai kemungkinan format ID
        const receiverId = receiver?.id || receiver?.user_id || receiver;
        
        if (!receiverId) {
            console.error('❌ Receiver ID tidak ditemukan. Data:', receiver);
            toast.error("Data penerima tidak valid - ID tidak ditemukan");
            processing.value = false;
            return;
        }

        // Konversi ke number dan validasi
        const numericReceiverId = Number(receiverId);
        if (isNaN(numericReceiverId) || numericReceiverId <= 0) {
            console.error('❌ Receiver ID tidak valid:', receiverId);
            toast.error("Data penerima tidak valid - ID harus berupa angka positif");
            processing.value = false;
            return;
        }

        console.log('✅ Receiver ID valid:', numericReceiverId);

        // 1. OPTIMISTIC UPDATE
        const tempCall = {
            id: 0,
            type: type,
            status: 'calling',
            caller: authStore.user,
            receiver: typeof receiver === 'object' ? receiver : { id: numericReceiverId },
            channel_name: '',
            agora_token: ''
        };
        
        store.setCurrentCall(tempCall as any);
        store.updateCallStatus('calling' as any);

        try {
            console.log('📞 Mengirim invite call ke ID:', numericReceiverId, 'type:', type);
            
            const response = await callService.inviteCall(
                numericReceiverId,
                type
            );
            
            console.log('📞 Response dari inviteCall:', response);
            
            // Merge response dengan data receiver dari UI
            const mergedCall = {
                ...response.call,
                receiver: typeof receiver === 'object' ? receiver : { id: numericReceiverId }
            };

            store.setCurrentCall(mergedCall);
            store.updateCallStatus('calling' as any);
            
        } catch (error: any) {
            console.error('❌ Error startVoiceCall:', error);
            console.error('❌ Error response:', error.response);
            
            // Tampilkan error message yang lebih detail
            const errorMessage = error.response?.data?.message 
                || error.response?.data?.error
                || error.message 
                || "Gagal memulai panggilan";
            
            toast.error(errorMessage);
            store.clearCurrentCall();
        } finally {
            processing.value = false;
        }
    }

    const acceptVoiceCall = async (callId: string | number) => {
        if (processing.value) return;
        processing.value = true;

        try {
            const numericCallId = Number(callId);
            console.log('📞 Menjawab call ID:', numericCallId);
            
            const callData = await callService.answerCall(numericCallId);
            
            console.log('📞 Response dari answerCall:', callData);
            
            store.setCurrentCall(callData);
            
            if (authStore.user && authStore.user.id) {
                await joinChannel(
                    callData.channel_name, 
                    callData.agora_token, 
                    authStore.user.id
                );
                await toggleAudio(false); 
            } else {
                throw new Error("User tidak terautentikasi");
            }
        } catch (error: any) {
            console.error('❌ Error acceptVoiceCall:', error);
            
            const errorMessage = error.response?.data?.message 
                || error.message 
                || "Gagal menjawab panggilan";
            
            toast.error(errorMessage);
            store.clearIncomingCall();
        } finally {
            processing.value = false;
        }
    };

    const rejectVoiceCall = async (callId: string | number) => {
        try {
            const numericCallId = Number(callId);
            console.log('🚫 Menolak call ID:', numericCallId);
            
            await callService.rejectCall(numericCallId);
            store.clearIncomingCall();
            toast.info("Panggilan ditolak");
        } catch (error: any) {
            console.error('❌ Error rejectVoiceCall:', error);
            toast.error("Gagal menolak panggilan");
        }
    };

    const endVoiceCall = async (callId: string | number) => {
        try {
            const numericCallId = Number(callId);
            console.log('❌ Mengakhiri call ID:', numericCallId);
            
            await leaveChannel();
            await callService.endCall(numericCallId); 
            store.clearCurrentCall();
            toast.info("Panggilan berakhir");
        } catch (error: any) {
            console.error('❌ Error endVoiceCall:', error);
            toast.error("Gagal mengakhiri panggilan");
        }
    };

    // --- EVENT HANDLERS ---
    
    const handleIncomingCall = (event: any) => {
        console.log("🔥 Handle Incoming Data Raw:", event);

        const baseUrl = import.meta.env.VITE_BACKEND_URL || 'http://127.0.0.1:8000'; 

        const fixAvatarUrl = (user: any) => {
            if (!user) return null;
            
            const photoField = user.profile_photo_url || user.photo || user.avatar;
            
            console.log('🖼️ Avatar Fix Input:', {
                name: user.name,
                profile_photo_url: user.profile_photo_url,
                photo: user.photo,
                avatar: user.avatar,
                selected: photoField
            });
            
            if (!photoField) return null;
            if (photoField.startsWith('http')) return photoField;
            
            const cleanPath = photoField.startsWith('/') ? photoField : `/${photoField}`;
            const result = `${baseUrl}${cleanPath}`;
            
            console.log('✅ Avatar Fixed URL:', result);
            return result;
        };

        const mappedCall = {
            id: event.call_id,     
            type: event.call_type,      
            status: 'calling',          
            channel_name: event.channel_name, 
            agora_token: event.agora_token,   
            
            caller: {
                id: event.caller.id,
                name: event.caller.name,
                profile_photo_url: fixAvatarUrl(event.caller),
                photo: fixAvatarUrl(event.caller),
                avatar: fixAvatarUrl(event.caller), 
            },
            
            receiver: {
                id: event.callee?.id,
                name: event.callee?.name,
                profile_photo_url: fixAvatarUrl(event.callee),
                photo: fixAvatarUrl(event.callee),
                avatar: fixAvatarUrl(event.callee),
            },

            created_at: new Date().toISOString()
        };

        if (!store.isInCall) {
            store.setIncomingCall(mappedCall as any); 
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
                    event.call.channel_name, 
                    event.call.agora_token, 
                    authStore.user.id
                );
                await toggleAudio(false); 
            }
        }
    };

    const handleCallRejected = () => {
        console.log('🚫 handleCallRejected dipanggil');
        toast.info("Panggilan ditolak");
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