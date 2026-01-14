import { ref } from "vue";
import { useCallStore } from "@/stores/callStore";
import { useAgora } from "./useAgora";
import { useAuthStore } from "@/stores/authStore";
import * as callService from "@/services/callServices";
import type { Call, CallType } from "@/types/call";
import { usePage } from "@inertiajs/vue3";

export const useVideoCall = () => {
    const store = useCallStore();
    const authStore = useAuthStore();
    const { joinChannel, leaveChannel, toggleAudio, toggleVideo, localAudioTrack, localVideoTrack } = useAgora();
    const processing = ref(false);

    // Dapatkan otentikasi user saat ini (dari inertia shared props atau manapun kamu simpan user auth)
    const page = usePage();
    const currentUser = authStore.user;
    // @ts-ignore
    const appId = import.meta.env.VITE_AGORA_APP_ID;

    // Start / Invite call
    const startCall = async (receiverId: number, type: CallType = 'video') => {
        console.log('🚀 useVideoCall: startCall dipanggil');
        console.log('📦 receiverId:', receiverId);
        console.log('📦 type:', type);
        console.log('📦 currentUser:', currentUser);

        if (processing.value) {
            console.warn('⚠️ Processing masih berjalan, skip call');
            return
        };

        if (!currentUser?.id) {
            console.error('❌ Current user tidak ditemukan!');
            return;
        }

        processing.value = true;

        try {
            console.log('Memanggil API /call/invite...');
            const response = await callService.inviteCall(receiverId, type);

            console.log('✅ Respon API:', response.data);

            // Simpan call ke store dengan status ringing
            store.setCurrentCall(response.data);

            console.log('✅ Call tersimpan di store');
            console.log('📦 currentCall:', store.currentCall);
            console.log('📦 callStatus:', store.callStatus);

        } catch (error: any) {
            console.error('❌ Error saat melakukan startCall:', error);
            console.error('Pesan:', error.response?.data?.message || error.message);
        } finally {
            processing.value = false;
        }
    };

    // Accept call
    const acceptCall = async (callId: number) => {
        console.log('✅ useVideoCall: acceptCall dipanggil');
        console.log('📦 callId:', callId);

        if (processing.value) {
            console.warn('⚠️ Processing masih berjalan, skip accept call');
            return
        };

        if (!currentUser?.id) {
            console.error('❌ Current user tidak ditemukan!');
            return;
        };

        processing.value = true;

        try {
            console.log('Memanggil API /call/answer...');
            const response = await callService.answerCall(callId);
            const callData = response.data;

            console.log('✅ Respon API:', callData);

            store.setCurrentCall(callData);
            
            // Join channel agora sekarang
            console.log('🚀 Bergabung ke channel Agora...');
            console.log('📦 App ID:', appId);
            console.log('📦 Channel:', callData.channel);
            console.log('📦 Token:', callData.token);
            console.log('📦 UID:', currentUser.id);

            await joinChannel(callData.channel, callData.token, currentUser.id);

        } catch (error: any) {
            console.error('❌ Error pada saat acceptCall:', error);
            console.error('Pesan:', error.response?.data?.message || error.message);
            store.clearIncomingCall();
        } finally {
            processing.value = false;
        }
    };

    // Menolak panggilan masuk
    const rejectCall = async (callId: number) => {
        console.log('🚫 useVideoCall: rejectCall dipanggil');
        console.log('📦 Call Id:', callId);

        try {
            await callService.rejectCall(callId);
            store.clearIncomingCall();

        } catch (error) {
            console.error('❌ Error pada saat rejectCall:', error);
        }
    };

    // Mengakhiri panggilan
    const endCall = async (callId: number) => {
        console.log('🔚 useVideoCall: endCall dipanggil...');
        console.log('📦 Call Id:', callId);

        try {
            // Leave agora dulu biar feedback UI nya cepet
            console.log('👋 Meninggalkan channel Agora...');
            await leaveChannel();

            // Hit API backend
            console.log('Memanggil API /call/end...');
            await callService.endCall(callId);

            // Cleanup store
            store.clearCurrentCall();

        } catch (error) {
            console.error('❌ Error pada saat endCall:', error);
        }
    };

    // Handle websocket events (nanti dipanggil di main app / layout)
    const handleIncomingCall = (event: any) => {
        console.log('🔔 useVideoCall: handleIncomingcall dipanggil');
        console.log('📦 Event:', event);

        // Cek kalau kita lagi ga di panggilan lain
        if (!store.isInCall) {
            store.setIncomingCall(event.call);
            console.log('✅ Incoming call diset ke store');
        } else {
            console.warn('⚠️ Sedang dalam panggilan lain, mengabaikan panggilan masuk');
        }
    };

    const handleCallAccepted = async (event: any) => {
        console.log('✅ useVideoCall: handleCallAccepted dipanggil');
        console.log('📦 Event:', event);

        // Update status di store
        if (store.currentCall && store.currentCall.id === event.call.id) {
            store.updateCallStatus('ongoing');

            if (!currentUser?.id) {
                console.error('❌ Current user tidak ditemukan!');
                return;
            }

            if (event.call.token) {
                await joinChannel(event.call.channel, event.call.token, currentUser.id);
            }
        }
    };

    const handleCallRejected = () => {
        console.warn('🚫 useVideoCall: handleCallRejected dipanggil');
        store.clearCurrentCall();
    };

    const handleCallEnded = async () => {
        console.log('🔚 useVideoCall: handleCallEnded dipanggil');
        await leaveChannel();
        store.clearCurrentCall();
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