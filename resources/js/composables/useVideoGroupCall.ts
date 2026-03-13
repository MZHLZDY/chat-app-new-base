import { ref } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from '@/stores/authStore'; 
import { useAgora } from '@/composables/useAgora';
import * as callServices from '@/services/callServices';
import { toast } from 'vue3-toastify';
import type { User } from '@/types/call';

export function useVideoGroupCall() {
    const callStore = useCallStore();
    const authStore = useAuthStore();
    
    // Ambil fungsi & state dari useAgora
    const { 
        joinChannel, 
        leaveChannel, 
        toggleAudio, 
        toggleVideo,
        isAudioEnabled,
        isVideoEnabled,
        localAudioTrack,
        localVideoTrack
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
        callStore.clearIncomingCall();
        callStore.isGroupCall = false;
        callStore.backendGroupCall = null;
        callStore.groupParticipants = [];
    };

    // ======================================================
    // 1. API ACTIONS (Dipanggil oleh interaksi UI User)
    // ======================================================

    const startGroupVideoCall = async (groupId: number, participantIds: number[]) => {
        if (!groupId || !participantIds || participantIds.length === 0) {
            toast.error("Gagal: Data grup atau peserta tidak valid.");
            return;
        }

        processing.value = true;
        try {
            // Panggil backend pakai callServices yg baru diupdate (type: 'video')
            const response = await callServices.startGroupCall(groupId, participantIds, 'video');

            // response kembalian: { message, call, token, channel_name }
            const callData = response.call;

            // Set state untuk Group (ke Store)
            callStore.isGroupCall = true;
            callStore.backendGroupCall = callData;
            callStore.groupParticipants = callData.participants || [];

            // Update UI State agar Modal Video Calling muncul
            callStore.setCurrentCall({
                id: callData.id,
                type: 'video',
                isGroup: true,
                status: 'calling',
                channelName: response.channel_name,
                caller: authStore.user as User,
                receiver: authStore.user as User, // TS bypass untuk store lama
            } as any); 
            
            toast.success("Memulai panggilan video grup...");
        } catch (error: any) {
            console.error('❌ Gagal memulai panggilan video grup:', error);
            const errMsg = error.response?.data?.message || error.response?.data?.error || "Gagal memulai panggilan";
            toast.error(errMsg);
        } finally {
            processing.value = false;
        }
    };

    const answerGroupVideoCall = async (callId: number) => {
        processing.value = true;
        try {
            const response = await callServices.joinGroupCall(callId);
            const data = response.call;

            callStore.isGroupCall = true;
            callStore.backendGroupCall = data;
            callStore.groupParticipants = data.participants || [];
            
            // Anggap "host" adalah yg ID nya host_id di data (fallback to self as fallback)
            const hostUser = data.host || authStore.user;

            callStore.setCurrentCall({ 
                id: data.id, 
                type: 'video',
                isGroup: true, 
                status: 'ongoing',
                channelName: response.channel_name,
                caller: hostUser as User,
                receiver: authStore.user as User, 
            } as any);

            callStore.clearIncomingCall(); // matikan modal incoming
            
            console.log('🚀 Peserta bergabung ke channel Agora grup...');
            await joinChannel(response.channel_name, response.token, authStore.user?.id as number);
            
            toast.success("Bergabung ke panggilan video...");
        } catch (error: any) {
            console.error('❌ Gagal menjawab panggilan:', error);
            toast.error('Gagal bergabung ke panggilan.');
        } finally {
            processing.value = false;
        }
    };

    const rejectGroupVideoCall = async (callId: number) => {
        try {
            await callServices.rejectGroupCall(callId);
            callStore.clearIncomingCall();
        } catch (error) {
            console.error('❌ Gagal menolak panggilan:', error);
        }
    };

    const leaveGroupVideoCall = async (callId: number) => {
        try {
            await callServices.leaveGroupCall(callId);
            await stopAndClearCall();
        } catch (error) {
            console.error('❌ Gagal keluar panggilan:', error);
        }
    };

    const endGroupVideoCallForAll = async (callId: number) => {
        try {
            await callServices.endGroupCallForAll(callId);
            await stopAndClearCall();
        } catch (error) {
            console.error('❌ Gagal membubarkan panggilan:', error);
            toast.error("Gagal membubarkan panggilan.");
        }
    };

    const recallParticipant = async (callId: number, userId: number) => {
        try {
            await callServices.recallGroupParticipant(callId, userId);
            toast.success('Peserta dipanggil ulang');
        } catch (error) {
            console.error('❌ Gagal memanggil ulang:', error);
        }
    };

    // ======================================================
    // 2. EVENT HANDLERS (Untuk dipanggil UI / Socket listener)
    // ======================================================

    const handleGroupIncomingCall = (payload: any) => {
        console.log('📡 [Event] Panggilan Group Video Masuk:', payload);
        callStore.setIncomingCall({
            id: payload.call_id,
            type: 'video',
            isGroup: true,
            status: 'ringing',
            channelName: payload.channel_name,
            token: payload.agora_token,
            caller: payload.caller,
            groupName: payload.group_name || 'Group Call',
            groupAvatar: payload.group_avatar || '',
            participants: payload.participants || []
        } as any);
    };

    const handleGroupCallAnswered = (payload: any) => {
        // payload dari event: { call_id, user, accepted, context }
        console.log('📡 [Event] Group Call Answered:', payload);
        callStore.updateGroupParticipantStatus(payload.user.id, payload.accepted ? 'joined' : 'declined');
    };

    const handleGroupParticipantLeft = (payload: any) => {
        // payload dari event: { call_id, group_id, user, status }
        console.log('📡 [Event] Participant Left/Declined:', payload);
        callStore.updateGroupParticipantStatus(payload.user.id, payload.status || 'left');
    };

    const handleGroupParticipantRecalled = (payload: any) => {
        console.log('📡 [Event] Participant Recalled:', payload);
        callStore.setParticipantRecalled(payload.user.id);
    };

    const handleGroupCallEnded = async (payload: any) => {
        console.log('📡 [Event] Host Ended Group Call:', payload);
        toast.info("Panggilan video grup telah diakhiri oleh Host.");
        await stopAndClearCall();
    };

    const handleGroupCallCancelled = async (payload: any) => {
        console.log('📡 [Event] Call Cancelled:', payload);
        if (callStore.incomingCall) {
            toast.info("Panggilan dibatalkan.");
        }
        await stopAndClearCall();
    };

    return {
        isAudioEnabled,
        isVideoEnabled,
        localAudioTrack,
        localVideoTrack,
        processing,
        startGroupVideoCall,
        answerGroupVideoCall,
        rejectGroupVideoCall,
        leaveGroupVideoCall,
        endGroupVideoCallForAll,
        recallParticipant,
        toggleMute: toggleAudio,
        toggleCamera: toggleVideo,
        handleGroupIncomingCall,
        handleGroupCallAnswered,
        handleGroupParticipantLeft,
        handleGroupParticipantRecalled,
        handleGroupCallEnded,
        handleGroupCallCancelled,
    };
}