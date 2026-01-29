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
    const acceptVoiceCall = async () => {
        // Cek incoming call
        if (!store.incomingCall) return;

        const callId = store.incomingCall.id;
        
        // 1. Tutup modal Incoming Call segera (Optimistic UI)
        store.clearIncomingCall(); 
        
        processing.value = true;

        try {
            // 2. Request ke Backend
            const response = await callService.answerCall(callId);
            
            // --- DEBUGGING: Cek apa yang sebenarnya dikirim backend ---
            console.log("✅ RAW RESPONSE ANSWER CALL:", response); 
            // ----------------------------------------------------------

            // 3. Normalisasi Data (Cara Aman membaca properti)
            // Cek apakah response punya properti 'call' atau response itu sendiri adalah datanya
            const callData = response.call || response; 
            
            // Ambil token & channel dengan pengecekan bertingkat (Optional Chaining)
            const tokenToUse = response.token || callData.token || callData.agora_token;
            const channelToUse = response.channel_name || callData.channel_name;

            // Jika masih gagal mendapatkan data vital
            if (!tokenToUse || !channelToUse) {
                console.error("❌ Gagal mendapatkan Token/Channel. Struktur Response:", response);
                throw new Error("Token atau Channel Name kosong dari backend");
            }

            // 4. Update Store
            store.setBackendCall(callData, tokenToUse, channelToUse);
            
            store.setCurrentCall({
                ...callData, // Gunakan spread operator agar semua field masuk
                status: 'ongoing',
                type: callData.call_type || 'voice'
            } as any);

            // 5. Join Agora Channel
            // Gunakan fallback '|| 0' untuk ID user
            await joinChannel(
                channelToUse, 
                tokenToUse, 
                authStore.user?.id || 0 
            );
            
            await toggleAudio(false); // Unmute mic
            toast.success("Panggilan terhubung");

        } catch (err: any) {
            console.error("❌ Gagal di acceptVoiceCall:", err);
            
            // Tampilkan pesan error yang lebih jelas
            const errorMsg = err.response?.data?.error || err.message || "Gagal menyambungkan panggilan";
            toast.error(errorMsg);
            
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
        await toggleAudio(false); // Matikan ringtone

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

    const handleCallAccepted = async (event: any) => {
        console.log('✅ handleCallAccepted dipanggil dengan data:', event);
        
        // 1. Normalisasi Data
        const callData = event.call || event; 

        if (!callData || !callData.id) {
            console.error("❌ Data call tidak valid di handleCallAccepted:", event);
            return;
        }

        // 2. Validasi ID Panggilan
        // Pastikan event ini untuk panggilan yang sedang kita lakukan
        if (store.currentCall && store.currentCall.id !== 0 && store.currentCall.id !== callData.id) {
             console.warn("⚠️ Menerima event accept untuk ID yang berbeda", { current: store.currentCall.id, incoming: callData.id });
             return;
        }

        // 3. Update status di store agar UI berubah (Misal: menutup modal calling)
        store.updateCallStatus('ongoing');
        
        // 4. JOIN AGORA CHANNEL
        // PERBAIKAN UTAMA DI SINI:
        // Gunakan token dari Firebase jika ada. Jika tidak ada (null/undefined), 
        // gunakan token yang sudah disimpan di store saat kita memulai panggilan (inviteCall).
        const tokenToUse = callData.agora_token || store.agoraToken;
        const channelToUse = callData.channel_name || store.channelName;

        console.log('🎧 Mencoba join channel dengan:', { 
            channel: channelToUse, 
            hasToken: !!tokenToUse 
        });

        if (channelToUse && tokenToUse) {
            try {
                await joinChannel(
                    channelToUse, 
                    tokenToUse, 
                    authStore.user?.id || 0
                );
                
                // Pastikan audio nyala (unmute) saat tersambung
                // Note: toggleAudio(false) artinya "setMuted(false)" -> Unmute
                await toggleAudio(false); 
                
                toast.success("Panggilan terhubung");
            } catch (err) {
                console.error("❌ Gagal Join Channel Agora:", err);
                toast.error("Gagal menyambungkan suara");
            }
        } else {
            console.error("❌ Token atau Channel Name hilang!", { 
                firebaseToken: callData.agora_token, 
                storeToken: store.agoraToken 
            });
            toast.error("Koneksi gagal: Token tidak ditemukan");
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