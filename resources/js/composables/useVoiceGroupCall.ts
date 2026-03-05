import { ref } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from '@/stores/auth'; // Sesuaikan path jika berbeda
import { useAgora } from '@/composables/useAgora';
import axios from '@/libs/axios';
import { toast } from 'vue3-toastify';
import type { User } from '@/types/call';

export function useVoiceGroupCall() {
    const callStore = useCallStore();
    const authStore = useAuthStore();
    
    // Ambil fungsi & state dari useAgora
    const { 
        joinChannel, 
        leaveChannel, 
        toggleAudio, 
        isAudioEnabled 
    } = useAgora();

    const processing = ref(false);

    // Fungsi helper untuk membersihkan state dan mematikan panggilan
    const stopAndClearCall = async () => {
        try {
            await leaveChannel();
        } catch (error) {
            console.error('❌ Error saat leave channel:', error);
        }
        
        callStore.clearCurrentCall();
        callStore.clearIncomingCall(); // Jaga-jaga jika masih nyangkut
        callStore.isGroupCall = false;
        callStore.backendGroupCall = null;
        callStore.groupParticipants = [];
    };

    // ======================================================
    // 1. API ACTIONS (Dipanggil oleh interaksi UI User)
    // ======================================================

    const startGroupVoiceCall = async (groupId: number, participantIds: number[]) => {
        // Cegah request jika data kosong
        if (!groupId || !participantIds || participantIds.length === 0) {
            toast.error("Gagal: Data grup atau peserta tidak valid.");
            return;
        }

        processing.value = true;
        try {
            // [FIX] Menggunakan "participants" sesuai validasi AgoraController.php
            const response = await axios.post('/group-call/invite', { 
                group_id: groupId,
                participants: participantIds, 
                call_type: 'voice'
            });

            const data = response.data; // Response.data punya 'call', 'token', 'channel_name'

            // Set state untuk Group
            callStore.isGroupCall = true;
            callStore.backendGroupCall = data.call;
            callStore.groupParticipants = data.call.participants || [];

            // Update UI State agar Modal Calling muncul
            callStore.setCurrentCall({
                id: data.call.id,
                isGroup: true,
                status: 'calling',
                channelName: data.channel_name,
                caller: authStore.user as User,
                receiver: authStore.user as User, // TS Bypass fallback
            } as any); 
            
            callStore.updateCallStatus('calling');
            callStore.setInCall(true);

            // Join Agora (Urutan parameter sudah disesuaikan)
            await joinChannel(data.channel_name, data.token, authStore.user?.id as number);

            toast.success("Memulai panggilan grup...");
        } catch (error: any) {
            console.error('❌ Gagal memulai panggilan grup:', error);
            const errMsg = error.response?.data?.message || error.response?.data?.error || "Gagal memulai panggilan grup";
            toast.error(errMsg);
        } finally {
            processing.value = false;
        }
    };

    const answerGroupVoiceCall = async (callId: number) => {
        processing.value = true;
        try {
            const response = await axios.post('/group-call/answer', { call_id: callId });
            const data = response.data;

            callStore.isGroupCall = true;
            callStore.backendGroupCall = data.call;
            callStore.groupParticipants = data.call.participants || [];
            
            callStore.setCurrentCall({ 
                id: data.call.id, 
                isGroup: true, 
                status: 'ongoing',
                channelName: data.channel_name,
                caller: data.call.host as User,
                receiver: authStore.user as User, // TS Bypass fallback
            } as any);

            callStore.clearIncomingCall();
            
            await joinChannel(data.channel_name, data.token, authStore.user?.id as number);
        } catch (error: any) {
            console.error('❌ Gagal menjawab panggilan:', error);
            toast.error('Gagal bergabung ke panggilan.');
        } finally {
            processing.value = false;
        }
    };

    const rejectGroupVoiceCall = async (callId: number) => {
        try {
            await axios.post('/group-call/reject', { call_id: callId });
            callStore.clearIncomingCall();
        } catch (error) {
            console.error('❌ Gagal menolak panggilan:', error);
        }
    };

    const leaveGroupVoiceCall = async (callId: number) => {
        try {
            await axios.post('/group-call/leave', { call_id: callId });
            await stopAndClearCall();
        } catch (error) {
            console.error('❌ Gagal keluar panggilan:', error);
        }
    };

    const endGroupVoiceCallForAll = async (callId: number) => {
        try {
            await axios.post('/group-call/end-all', { call_id: callId });
            await stopAndClearCall();
        } catch (error) {
            console.error('❌ Gagal membubarkan panggilan:', error);
        }
    };

    const recallParticipant = async (callId: number, userId: number) => {
        try {
            await axios.post('/group-call/recall', { call_id: callId, user_id: userId });
            toast.success('Peserta dipanggil ulang');
        } catch (error) {
            console.error('❌ Gagal memanggil ulang:', error);
        }
    };

    // ======================================================
    // 2. FIREBASE EVENT HANDLERS (Dipanggil dari Global Listener)
    // ======================================================

    const handleGroupIncomingCall = (data: any) => {
        console.log("📥 Handle Incoming Group Call via Firebase:", data);

        // Ambil string 'voice' atau 'video' dari 'group_voice' / 'group_video'
        const actualType = data.call_type.replace('group_', ''); 

        const incomingCall = {
            id: data.call_id,
            isGroup: true,
            type: actualType, // Akan menjadi 'voice' atau 'video'
            caller: data.caller,
            receiver: authStore.user as User,
            status: 'ringing',
            channelName: data.channel_name,
            group: data.group
        };

        callStore.isGroupCall = true;
        callStore.setIncomingCall(incomingCall as any);
    };

    const handleGroupVoiceCallAnswered = (payload: any) => {
        console.log('📡 [Firebase] Group Call Answered:', payload);
        callStore.updateGroupParticipantStatus(payload.user.id, payload.accepted ? 'joined' : 'declined');
    };

    const handleGroupParticipantLeft = (payload: any) => {
        console.log('📡 [Firebase] Participant Left/Declined:', payload);
        callStore.updateGroupParticipantStatus(payload.user.id, payload.status || 'left');
    };

    const handleGroupParticipantRecalled = (payload: any) => {
        console.log('📡 [Firebase] Participant Recalled:', payload);
        callStore.updateGroupParticipantStatus(payload.user.id, 'ringing');
    };

    const handleGroupCallEnded = async (payload: any) => {
        console.log('📡 [Firebase] Host Ended Group Call:', payload);
        toast.info("Panggilan grup telah diakhiri oleh Host.");
        await stopAndClearCall();
    };

    const handleGroupCallCancelled = async (payload: any) => {
        console.log('📡 [Firebase] Call Cancelled:', payload);
        if (callStore.incomingCall) {
            toast.info("Panggilan grup dibatalkan.");
        }
        await stopAndClearCall();
    };

    return {
        isAudioEnabled,
        processing,
        startGroupVoiceCall,
        answerGroupVoiceCall,
        rejectGroupVoiceCall,
        leaveGroupVoiceCall,
        endGroupVoiceCallForAll,
        recallParticipant,
        toggleMute: toggleAudio,
        handleGroupIncomingCall,
        handleGroupVoiceCallAnswered,
        handleGroupParticipantLeft,
        handleGroupParticipantRecalled,
        handleGroupCallEnded,
        handleGroupCallCancelled,
    };
}