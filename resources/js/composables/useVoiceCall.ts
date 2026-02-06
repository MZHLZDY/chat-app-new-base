import { ref, watch } from "vue";
import { useCallStore } from "@/stores/callStore";
import { useAuthStore } from "@/stores/auth";
import { useAgora } from "./useAgora";
import * as callService from "@/services/callServices";
import type { CallType } from "@/types/call";
import { toast } from "vue3-toastify";

export const useVoiceCall = () => {
    const store = useCallStore();
    const authStore = useAuthStore();
    
    // Gunakan semua fungsi yang diperlukan dari useAgora
    const { 
        joinChannel, 
        leaveChannel, 
        toggleAudio, 
        toggleVideo, 
        localAudioTrack,
        localVideoTrack,
        isAudioEnabled,
        isVideoEnabled,
        remoteUsers,
        remoteAudioTracks,
        remoteVideoTracks,
        isJoined
    } = useAgora();
    
    const processing = ref(false);
    
    // --- FUNGSI HELPER UNTUK MENGATUR TRACK ---
    
    // Pastikan microphone menyala setelah join channel
    const ensureMicrophoneOn = async () => {
        let attempts = 0;
        const maxAttempts = 10;
        
        while (attempts < maxAttempts) {
            if (localAudioTrack.value) {
                try {
                    await localAudioTrack.value.setEnabled(true);
                    // Pastikan state di useAgora terupdate
                    if (!isAudioEnabled.value) {
                        isAudioEnabled.value = true;
                    }
                    console.log("🎤 Microphone forced ON (Voice Call)");
                    return true;
                } catch (error) {
                    console.error("❌ Gagal mengaktifkan microphone:", error);
                }
            }
            
            // Tunggu 200ms sebelum mencoba lagi
            await new Promise(resolve => setTimeout(resolve, 200));
            attempts++;
        }
        
        console.warn("⚠️ Tidak bisa mengaktifkan microphone setelah beberapa kali percobaan");
        return false;
    };
    
    // Pastikan kamera mati untuk voice call
    const ensureCameraOff = async () => {
        if (localVideoTrack.value) {
            try {
                await localVideoTrack.value.setEnabled(false);
                if (isVideoEnabled.value) {
                    isVideoEnabled.value = false;
                }
                console.log("📹 Camera forced OFF (Voice Call)");
            } catch (error) {
                console.error("❌ Gagal mematikan kamera:", error);
            }
        }
    };
    
    // Subscribe audio remote user (sama seperti di video call)
    const subscribeRemoteAudio = () => {
        if (remoteUsers.value.length > 0) {
            const user = remoteUsers.value[0];
            if (user && user.audioTrack) {
                console.log("🔊 Subscribing to remote audio track");
                // Audio sudah otomatis disubscribe oleh Agora SDK
                // Pastikan volume cukup tinggi
                if (user.audioTrack.setVolume) {
                    user.audioTrack.setVolume(100);
                }
            }
        }
    };
    
    // Watch untuk remote users (mirip dengan video call)
    watch(() => remoteUsers.value.length, (count, oldCount) => {
        console.log(`👥 Voice call - Remote users count: ${oldCount} -> ${count}`);
        
        if (count > 0) {
            // Ada remote user, subscribe audionya
            subscribeRemoteAudio();
            
            // Juga subscribe ke audio tracks map
            const user = remoteUsers.value[0];
            if (user && user.uid) {
                const uidStr = user.uid.toString();
                console.log('🔍 Checking remote audio tracks for UID:', uidStr);
                console.log('📦 remoteAudioTracks keys:', Array.from(remoteAudioTracks.value.keys()));
                
                const audioTrack = remoteAudioTracks.value.get(uidStr);
                if (audioTrack) {
                    console.log('✅ Found remote audio track, setting volume...');
                    audioTrack.play();
                    if (audioTrack.setVolume) {
                        audioTrack.setVolume(100);
                    }
                }
            }
        }
    });
    
    // Watch untuk remote audio tracks
    watch(() => remoteAudioTracks.value.size, (size) => {
        console.log('🎤 Voice call - Remote audio tracks count:', size);
        if (size > 0) {
            subscribeRemoteAudio();
        }
    });
    
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
            console.log("📦 RAW Response Invite:", response);

            // 3. Normalisasi Data
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
            if (tokenToUse && channelToUse) {
                console.log('🚀 Joining Agora channel for voice call...');
                console.log('📦 Channel:', channelToUse);
                console.log('📦 UID:', authStore.user?.id);
                
                // await joinChannel(channelToUse, tokenToUse, authStore.user?.id || 0);
                
                console.log('✅ Joined channel, ensuring microphone is on...');
                
                // Tunggu sebentar untuk memastikan track sudah tersedia
                // setTimeout(async () => {
                //     // Matikan kamera (voice call)
                //     await ensureCameraOff();
                    
                //     // Pastikan microphone menyala
                //     await ensureMicrophoneOn();
                    
                //     console.log('🎤 Voice call setup complete');
                    
                //     // Subscribe ke audio remote jika ada
                //     if (remoteUsers.value.length > 0) {
                //         subscribeRemoteAudio();
                //     }
                // }, 500);
                
            } else {
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
            console.log('🚀 Receiver joining Agora channel for voice call...');
            await joinChannel(channelToUse, tokenToUse, authStore.user?.id || 0);
            
            // 3. Hardware Logic (Force Config)
            setTimeout(async () => {
                // A. Matikan Video
                await ensureCameraOff();

                // B. Nyalakan Mic
                await ensureMicrophoneOn();
                
                console.log('🎤 Receiver voice call setup complete');
                
                // Subscribe ke audio remote jika ada
                if (remoteUsers.value.length > 0) {
                    subscribeRemoteAudio();
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
        }
    };

    const endVoiceCall = async (callId: string | number | undefined, notifyOther: boolean = true) => {
    try {
        if (!callId) {
            console.warn('⚠️ No call ID provided, performing local cleanup');
            await localCleanupOnly();
            return;
        }
        
        const numericCallId = Number(callId);
        console.log('❌ Mengakhiri voice call ID:', numericCallId);
        
        // 1. Tentukan peran
        const isCaller = store.currentCall?.caller?.id === authStore.user?.id;
        console.log('🎭 Role:', isCaller ? 'Caller' : 'Callee');
        
        // 2. Langsung update UI state (Optimistic UI)
        store.updateCallStatus('ended');
        
        // 3. Leave Agora Channel
        console.log('👋 Leaving Agora channel...');
        await leaveChannel();
        console.log('✅ Left Agora channel');
        
        // 4. Kirim ke backend HANYA jika perlu notifikasi
        if (notifyOther) {
            console.log('📤 Notifying other party...');
            await callService.endCall(numericCallId, notifyOther);
            console.log('✅ Notification sent');
        }
        
        // 5. Tunda toast untuk sinkronisasi
        // Tunggu sebentar agar server punya waktu mengirim notifikasi ke pihak lain
        setTimeout(() => {
            toast.info("Panggilan berakhir");
            
            // 6. Clear store setelah toast muncul
            setTimeout(() => {
                store.clearCurrentCall();
                store.clearIncomingCall();
            }, 500);
        }, 800); // Delay 800ms untuk sinkronisasi
        
    } catch (error: any) {
        console.error('❌ Error endVoiceCall:', error);
        
        // Fallback cleanup
        await localCleanupOnly();
        toast.error("Gagal mengakhiri panggilan");
    }
};

    // Fungsi baru: End call sebagai caller
    const endCallAsCaller = async (callId: string | number) => {
        console.log('👑 Ending call as CALLER');
        return await endVoiceCall(callId, true); // Notify callee
    };

    // Fungsi baru: End call sebagai callee
    const endCallAsCallee = async (callId: string | number) => {
        console.log('📞 Ending call as CALLEE');
        return await endVoiceCall(callId, true); // Notify caller
    };

    // Fungsi baru: Local cleanup tanpa notify
    const localCleanupOnly = async () => {
      console.log('🧹 Performing local cleanup only');
    
      try {
          await leaveChannel();
      } catch (error) {
          console.error('❌ Error leaving channel:', error);
      }
    
      store.clearCurrentCall();
      store.clearIncomingCall();
    };

    const cancelVoiceCall = async () => {
        if (!store.currentCall) return;

        try {
            await callService.cancelCall(store.currentCall.id); 
        } catch (error) {
            console.error("Gagal mengirim sinyal cancel ke server", error);
        } finally {
            store.clearCurrentCall();
            store.updateCallStatus('ended');
        }
    };

    // --- EVENT HANDLERS ---
    
    const handleIncomingCall = (event: any) => {
        console.log("📥 Handle Incoming Voice Call Data Raw:", event);

        const baseUrl = import.meta.env.VITE_BACKEND_URL || 'http://127.0.0.1:8000'; 

        // Helper untuk memperbaiki URL avatar dengan prioritas field
        const fixAvatarUrl = (user: any) => {
            if (!user) {
                console.warn('⚠️ User object is null/undefined');
                return null;
            }
            
            const photoField = 
                user.profile_photo_url || 
                user.photo || 
                user.avatar ||
                user.profile_photo ||
                user.image ||
                user.picture;
            
            if (!photoField) {
                console.warn('⚠️ No photo field found for user:', user.name);
                return null;
            }
            
            if (photoField.startsWith('http')) {
                return photoField;
            }
            
            const cleanBase = baseUrl.endsWith('/') ? baseUrl : `${baseUrl}/`;
            let cleanPath = photoField.startsWith('/') ? photoField.substring(1) : photoField;
            
            if (cleanPath.startsWith('storage/')) {
                cleanPath = cleanPath;
            } else {
                cleanPath = `storage/${cleanPath}`;
            }
            
            return `${cleanBase}${cleanPath}`;
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
        console.log('✅ Voice call handleCallAccepted triggered', event);

        const rawData = event.call || event; 
        const eventCallId = rawData.id || rawData.call_id;

        if (store.currentCall && store.currentCall.id === 0) {
            store.currentCall.id = eventCallId;
        }

        if (store.currentCall && store.currentCall.id == eventCallId) {
            console.log('✅ Voice Call Accepted! Updating status to ongoing...');
            
            store.updateCallStatus('ongoing');
            store.setInCall(true);

            // Pastikan microphone menyala setelah call diterima
            setTimeout(async () => {
                await ensureMicrophoneOn();
                
                // Subscribe ke audio remote
                if (remoteUsers.value.length > 0) {
                    subscribeRemoteAudio();
                }
            }, 300);
            
        } else {
            console.warn(`⚠️ Mismatch Call ID. Store: ${store.currentCall?.id}, Event: ${eventCallId}`);
        }
    };
    
    const handleCallRejected = () => {
        console.log('🚫 handleCallRejected dipanggil');
        toast.info("Panggilan ditolak");
        store.clearCurrentCall();
    };

    // Fungsi baru: Synchronized end call untuk kedua pihak
    const synchronizedEndCall = async (callId: string | number) => {
        console.log('🔄 Starting synchronized end call');
    
        const numericCallId = Number(callId);
        const isCaller = store.currentCall?.caller?.id === authStore.user?.id;
    
        // 1. Countdown bersama (3, 2, 1)
        const countdown = async () => {
            return new Promise<void>((resolve) => {
            let count = 3;
            const countdownInterval = setInterval(() => {
                if (count > 0) {
                    console.log(`⏱️ Ending call in ${count}...`);
                    count--;
                } else {
                    clearInterval(countdownInterval);
                    resolve();
                }
            }, 1000);
        });
    };
    
    try {
        // 2. Mulai countdown
        await countdown();
        
        // 3. Update status UI
        store.updateCallStatus('ended');
        
        // 4. Leave channel
        await leaveChannel();
        
        // 5. Kirim ke backend
        await callService.endCall(numericCallId, true);
        
        // 6. Tampilkan toast BERSAMAAN (tanpa delay)
        toast.info("Panggilan berakhir");
        
        // 7. Clear store
        setTimeout(() => {
            store.clearCurrentCall();
            store.clearIncomingCall();
        }, 1000);
        
    } catch (error) {
        console.error('❌ Synchronized end call failed:', error);
        await localCleanupOnly();
        toast.error("Gagal mengakhiri panggilan");
    }
};

    const handleCallEnded = async (event?: any) => {
    console.log('❌ Voice call handleCallEnded dipanggil', event);
    
    // 1. Langsung update UI state
    store.updateCallStatus('ended');
    
    // 2. Cleanup Agora
    try {
        console.log('👋 Leaving Agora channel...');
        await leaveChannel();
        console.log('✅ Left Agora channel');
    } catch (error) {
        console.error('❌ Error leaving Agora channel:', error);
    }
    
    // 3. Tampilkan toast segera
    toast.info("Panggilan berakhir");
    
    // 4. Clear store dengan delay
    setTimeout(() => {
        console.log('🧹 Clearing call store...');
        store.clearCurrentCall();
        store.clearIncomingCall();
    }, 1000);
};

    const handleCallCancelled = () => {
        console.log('🚫 Voice call dibatalkan oleh penelepon');

        if (store.incomingCall) {
            toast.info("Panggilan Dibatalkan", {
                autoClose: 3000,
            });

            store.clearIncomingCall(); 
        }
        
        store.clearCurrentCall();
    };

    return {
        startVoiceCall,
        acceptVoiceCall,
        rejectVoiceCall,
        endVoiceCall,
        endCallAsCaller,
        endCallAsCallee,
        localCleanupOnly,
        cancelVoiceCall,
        handleIncomingCall,
        handleCallAccepted,
        handleCallRejected,
        synchronizedEndCall,
        handleCallEnded,
        handleCallCancelled,
        toggleAudio,
        toggleVideo,
        localAudioTrack,
        processing,
        // Export fungsi helper untuk debugging
        ensureMicrophoneOn,
        ensureCameraOff,
        subscribeRemoteAudio
    };
};