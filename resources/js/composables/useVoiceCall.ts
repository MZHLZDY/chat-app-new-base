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
        localAudioTrack,
        localVideoTrack,
        isAudioEnabled
    } = useAgora();
    
    const processing = ref(false);
    
    // @ts-ignore
    const appId = import.meta.env.VITE_AGORA_APP_ID;
    
    // --- ACTIONS ---

    const startVoiceCall = async (receiver: any, type: CallType = 'voice') => {
        if (processing.value) return;
        processing.value = true;

        // 1. OPTIMISTIC UI (Tampilkan Calling Dulu)
        const tempCall = {
            id: 0,
            type: type,
            status: 'calling',
            caller: authStore.user,
            receiver: receiver,
            token: '',
            channel: ''
        };
        
        store.setCurrentCall(tempCall as any);
        store.updateCallStatus('calling' as any);

        try {
            // 2. Request ke Backend
            const response = await callService.inviteCall(receiver.id, type);
            console.log("📦 RAW Response Invite:", response); // Cek console untuk debugging

            // 3. Normalisasi Data
            // Kadang data ada di response.call, kadang di response langsung
            const callData = response.call || response; 
            const callId = callData.id || response.call_id;

            // Update ID asli ke Store (PENTING)
            if (store.currentCall) {
                store.currentCall.id = callId; 
            }

            // 4. STRATEGI MENCARI TOKEN & CHANNEL
            let tokenToUse = response.token || callData.token || callData.agora_token;
            let channelToUse = response.channel_name || callData.channel_name;

            // 🔥 EMERGENCY FIX: Jika Token/Channel kosong, kita GENERATE MANUAL
            if (!tokenToUse || !channelToUse) {
                console.warn("⚠️ Token tidak ada di response invite. Mencoba generate manual...");
                
                // Fallback nama channel: "call_{id}"
                if (!channelToUse && callId) {
                    channelToUse = `call_${callId}`;
                }

                // Request Token Baru
                if (channelToUse && authStore.user?.id) {
                    try {
                        const tokenResponse = await callService.generateToken(channelToUse, authStore.user.id);
                        tokenToUse = tokenResponse.token;
                        console.log("✅ Emergency Token Generated:", tokenToUse);
                    } catch (tokenErr) {
                        console.error("❌ Gagal generate emergency token:", tokenErr);
                    }
                }
            }

            // Update Store dengan data final
            if (store.currentCall) {
                store.currentCall.token = tokenToUse;
                store.currentCall.channel = channelToUse;
                // Simpan juga data backend lengkap
                store.setBackendCall(callData, tokenToUse, channelToUse);
            }

            // 5. HARDWARE LOGIC (Join & Force Mic)
            // Hanya lakukan ini jika kita BERHASIL dapat token/channel
            if (tokenToUse && channelToUse) {
                await joinChannel(channelToUse, tokenToUse, authStore.user?.id || 0);

                // Force Mic Nyala & Kamera Mati
                if (localVideoTrack.value) await localVideoTrack.value.setEnabled(false);
                
                if (localAudioTrack.value) {
                    await localAudioTrack.value.setEnabled(true);
                    isAudioEnabled.value = true; // Paksa icon hijau
                    console.log("🎤 Mic Caller: FORCE ON");
                }
            } else {
                // Jika masih gagal juga, ya sudah kita tunggu 'Accepted' saja.
                // Jangan throw error agar UI tidak crash / tertutup.
                console.warn("⚠️ Tidak bisa pre-join channel (Missing Credentials), menunggu call diangkat...");
            }

        } catch (err: any) {
            console.error("❌ Gagal startVoiceCall:", err);
            toast.error(err.response?.data?.message || "Gagal memulai panggilan");
            store.clearCurrentCall();
        } finally {
            processing.value = false;
        }
    };

    // Ubah tipe parameter jadi string | number agar fleksibel
    const acceptVoiceCall = async () => {
        if (!store.incomingCall) return;
        const callId = store.incomingCall.id;
        
        // 1. UI First
        store.clearIncomingCall(); 
        processing.value = true;

        try {
            const response = await callService.answerCall(callId);
            const callData = response.call || response;
            const tokenToUse = response.token || callData.token || callData.agora_token;
            const channelToUse = response.channel_name || callData.channel_name;

            store.setBackendCall(callData, tokenToUse, channelToUse);
            store.setCurrentCall({
                ...callData, 
                status: 'ongoing',
                type: 'voice' 
            } as any);
            store.setInCall(true);

            // 2. Join Agora
            await joinChannel(channelToUse, tokenToUse, authStore.user?.id || 0);
            
            // 3. Hardware Logic (Force Config)
            setTimeout(async () => {
                // A. Matikan Video
                if (localVideoTrack.value) {
                    await localVideoTrack.value.setEnabled(false);
                }

                // B. Nyalakan Mic
                if (localAudioTrack.value) {
                    await localAudioTrack.value.setEnabled(true);
                    isAudioEnabled.value = true; // Paksa icon hijau
                    console.log("🎤 Mic Receiver: FORCE ON");
                }
            }, 500); // Beri jeda dikit biar aman

            toast.success("Terhubung");

        } catch (err: any) {
            console.error(err);
            store.clearCurrentCall();
        } finally {
            processing.value = false;
        }
    };

    const rejectVoiceCall = async () => {
       // Pastikan ada data incomingCall
       if (!store.incomingCall) {
          console.error("Tidak ada panggilan masuk untuk ditolak");
          return; 
        }
    
        // Simpan ID sebelum di-clear
        const callId = store.incomingCall.id;

        // Bersihkan UI dulu agar responsif (Optimistic UI)
        store.clearIncomingCall();
        store.clearCurrentCall();
        await toggleAudio(); // Matikan ringtone

        try {
           // Kirim request ke backend
           await callService.rejectCall(callId);
        } catch (error) {
          console.error("Gagal reject di backend:", error);
          // Tidak perlu memunculkan error toast ke user jika statusnya 400 (karena UI sudah tertutup)
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

    // Di dalam useVoiceCall.ts -> handleCallAccepted

const handleCallAccepted = async (event: any) => {
    console.log('✅ handleCallAccepted triggered', event);

    const rawData = event.call || event; 
    const eventCallId = rawData.id || rawData.call_id;

    // 🔥 FIX 2: LOGIC SIMPEL (MIRIP VIDEO CALL)
    
    // Cek 1: Jika ID di store masih 0 (kasus race condition), paksa update
    if (store.currentCall && store.currentCall.id === 0) {
        store.currentCall.id = eventCallId;
    }

    // Cek 2: Validasi ID (Pastikan event ini untuk call kita)
    if (store.currentCall && store.currentCall.id == eventCallId) {
        
        console.log('✅ Call Accepted! Updating status to ongoing...');
        
        // Update Status
        store.updateCallStatus('ongoing');
        
        // Paksa flag isInCall menyala (untuk memicu Computed Property di Index.vue)
        store.setInCall(true);

        // Note: Kita TIDAK perlu joinChannel lagi di sini. 
        // Karena sebagai Caller, kita sudah join di startVoiceCall.
        
    } else {
        console.warn(`⚠️ Mismatch Call ID. Store: ${store.currentCall?.id}, Event: ${eventCallId}`);
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