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
    
    // Asumsi: joinChannel di useAgora definisinya (channel, token, uid)
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

    const startVoiceCall = async (receiver: any, type: CallType = 'voice') => {
        if (processing.value) return;
        processing.value = true;

        // 1. OPTIMISTIC UPDATE
        const tempCall = {
            id: 0,
            type: type,
            status: 'calling',
            caller: authStore.user,
            receiver: receiver, // Pakai data receiver lengkap dari UI
            token: '',
            channel: ''
        };
        
        store.setCurrentCall(tempCall as any);
        store.updateCallStatus('calling' as any);

        try {
            const response = await callService.inviteCall(receiver.id, type);
            console.log('📞 Response dari inviteCall:', response);
            
            const mergedCall = {
                ...response.call,
                receiver: receiver // Paksa pakai data receiver UI
            };

            store.setCurrentCall(mergedCall);
            store.updateCallStatus('calling' as any);
            
        } catch (error: any) {
            console.error('❌ Error startVoiceCall:', error);
            toast.error(error.response?.data?.message || "Gagal memulai panggilan");
            store.clearCurrentCall();
        } finally {
            processing.value = false;
        }
    }

    // Ubah tipe parameter jadi string | number agar fleksibel
    const acceptVoiceCall = async (callId: string | number) => {
        if (processing.value) return;
        processing.value = true;

        try {
            // FIX ERROR 1: Convert ke Number
            const callData = await callService.answerCall(Number(callId));
            
            console.log('📞 Response dari answerCall:', callData);
            
            store.setCurrentCall(callData);
            
            if (authStore.user && authStore.user.id) {
                // FIX ERROR 2: Hapus appId (karena expected 3 arguments)
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
            // FIX ERROR 3: Convert ke Number
            await callService.rejectCall(Number(callId));
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
            // FIX ERROR 4: Convert ke Number
            await callService.endCall(Number(callId)); 
            store.clearCurrentCall();
            toast.info("Panggilan berakhir");
        } catch (error: any) {
            console.error('❌ Error endVoiceCall:', error);
            toast.error("Gagal mengakhiri panggilan");
        }
    };

    const cancelVoiceCall = async () => {
    if (!store.currentCall) return;

    try {
        // 1. Panggil API ke Backend memberitahu panggilan dibatalkan
        // (Pastikan endpoint ini mentrigger event Firebase ke lawan bicara)
        await callService.cancelCall(store.currentCall.id); 
    } catch (error) {
        console.error("Gagal mengirim sinyal cancel ke server", error);
    } finally {
        // 2. Bersihkan state di sisi penelepon sendiri
        store.clearCurrentCall();
        store.updateCallStatus('ended'); // atau reset status
    }
};

    // --- EVENT HANDLERS ---
    
    const handleIncomingCall = (event: any) => {
    console.log("📥 Handle Incoming Data Raw:", event);

    // --- SOLUSI AVATAR INCOMING ---
    const baseUrl = import.meta.env.VITE_BACKEND_URL || 'http://127.0.0.1:8000'; 

    // Helper untuk memperbaiki URL avatar dengan prioritas field
    const fixAvatarUrl = (user: any) => {
        if (!user) {
            console.warn('⚠️ User object is null/undefined');
            return null;
        }
        
        // Expanded priority check - backend bisa kirim field berbeda
        const photoField = 
            user.profile_photo_url || 
            user.photo || 
            user.avatar ||
            user.profile_photo ||
            user.image ||
            user.picture;
        
        console.log('🖼️ Avatar Fix Input:', {
            name: user.name,
            id: user.id,
            profile_photo_url: user.profile_photo_url,
            photo: user.photo,
            avatar: user.avatar,
            profile_photo: user.profile_photo,
            selected: photoField,
            allKeys: Object.keys(user)
        });
        
        if (!photoField) {
            console.warn('⚠️ No photo field found for user:', user.name);
            return null;
        }
        
        // Jika sudah URL lengkap
        if (photoField.startsWith('http')) {
            console.log('✅ Using full URL:', photoField);
            return photoField;
        }
        
        // Build URL dari path relative
        const cleanBase = baseUrl.endsWith('/') ? baseUrl : `${baseUrl}/`;
        let cleanPath = photoField.startsWith('/') ? photoField.substring(1) : photoField;
        
        // Pastikan path tidak double 'storage/'
        if (cleanPath.startsWith('storage/')) {
            cleanPath = cleanPath; // sudah benar
        } else {
            cleanPath = `storage/${cleanPath}`; // tambahkan storage/ jika belum ada
        }
        
        const result = `${cleanBase}${cleanPath}`;
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
            // PERBAIKAN: Gunakan helper dengan prioritas lengkap
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
                // FIX ERROR 5: Hapus appId, sesuaikan argumen
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

    // di dalam useVoiceCall.ts

const handleCallCancelled = () => {
    console.log('🚫 Panggilan dibatalkan oleh penelepon');

    // 1. Cek apakah user sedang membuka modal Incoming Call
    if (store.incomingCall) {
         // 2. Munculkan Notifikasi
         toast.info("Panggilan Dibatalkan", {
            autoClose: 3000,
            position: toast.POSITION.TOP_CENTER,
         });

         // 3. "Redirect" / Tutup Modal
         store.clearIncomingCall(); 
    }
    
    // Bersihkan state lain jika perlu
    store.clearCurrentCall();
};

    return {
        startVoiceCall,
        acceptVoiceCall,
        rejectVoiceCall,
        endVoiceCall,
        cancelVoiceCall,
        handleIncomingCall,
        handleCallAccepted,
        handleCallRejected,
        handleCallEnded,
        handleCallCancelled,
        toggleAudio,
        toggleVideo,
        localAudioTrack,
        processing,
    };
};