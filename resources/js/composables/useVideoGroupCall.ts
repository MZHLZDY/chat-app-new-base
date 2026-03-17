import { ref } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from '@/stores/authStore';
import { useAgora } from '@/composables/useAgora';
import * as callServices from '@/services/callServices';
import { toast } from 'vue3-toastify';
import type { User } from '@/types/call';
import { database } from '@/libs/firebase';
import { ref as dbRef, set, onValue } from 'firebase/database';

export function useVideoGroupCall() {
    const callStore = useCallStore();
    const authStore = useAuthStore();

    const {
        joinChannel,
        leaveChannel,
        toggleAudio,
        toggleVideo,
        isAudioEnabled,
        isVideoEnabled,
        localAudioTrack,
        localVideoTrack,
    } = useAgora();

    const processing = ref(false);

    const stopAndClearCall = async () => {
        try {
            await leaveChannel();
        } catch (error) {
            console.error('Error saat leave channel:', error);
        }

        callStore.clearCurrentCall();
        callStore.clearIncomingCall();
        callStore.isGroupCall = false;
        callStore.backendGroupCall = null;
        callStore.groupParticipants = [];
    };

    const startGroupVideoCall = async (groupId: number, participantIds: number[]) => {
        if (!groupId || !participantIds || participantIds.length === 0) {
            toast.error('Gagal: Data grup atau peserta tidak valid.');
            return;
        }

        processing.value = true;
        try {
            const response = await callServices.startGroupCall(groupId, participantIds, 'video');
            const callData = response.call;

            callStore.isGroupCall = true;
            callStore.backendGroupCall = callData;
            callStore.groupParticipants = callData.participants || [];
            callStore.agoraToken = response.token;
            callStore.channelName = response.channel_name;

            callStore.setCurrentCall({
                id: callData.id,
                type: 'video',
                isGroup: true,
                status: 'calling',
                channelName: response.channel_name,
                caller: authStore.user as User,
                receiver: authStore.user as User,
            } as any);

            // Penting: kirim incoming notification ke anggota grup via Firebase.
            await Promise.all(
                participantIds
                    .filter((participantId) => participantId !== authStore.user?.id)
                    .map((participantId) => {
                        const incomingRef = dbRef(database, `calls/${participantId}/incoming`);
                        return set(incomingRef, {
                            call_id: callData.id,
                            call_type: 'group_video',
                            channel_name: response.channel_name,
                            agora_token: response.token || '',
                            token: response.token || '',
                            caller: authStore.user,
                            group_id: callData.group_id || groupId,
                            group_name:
                                callData.group?.name ||
                                callStore.activeGroupName ||
                                'Group Call',
                            group_avatar:
                                callData.group?.photo ||
                                callData.group?.avatar ||
                                callStore.activeGroupAvatar ||
                                '',
                            participants: callData.participants || participantIds,
                            timestamp: Date.now(),
                        });
                    })
            );

            const participantsRef = dbRef(database, `group_calls/${callData.id}/participants`);
            onValue(participantsRef, async (snapshot) => {
                if (snapshot.exists()) {
                    const participantsData = snapshot.val();
                    
                    const isAnyoneJoined = Object.values(participantsData).some((p: any) => p.status === 'joined');
                    
                    // Cek apakah SEMUA peserta di list ini menolak (declined) atau keluar (left)
                    const totalParticipants = Object.keys(participantsData).length;
                    const declinedParticipants = Object.values(participantsData).filter((p: any) => ['declined', 'left'].includes(p.status)).length;
                    
                    const isAllDeclinedOrLeft = totalParticipants > 0 && declinedParticipants === totalParticipants;
                    
                    // Update list participant di store biar UI nampilin Realtime
                    Object.entries(participantsData).forEach(([userId, participantState]: [string, any]) => {
                        callStore.updateGroupParticipantStatus(Number(userId), participantState.status);
                    });

                    // Kalau ada yang join, pindah dari layar 'memanggil' ke 'ngobrol' (ongoing)
                    if (isAnyoneJoined && callStore.callStatus === 'calling') {
                        callStore.updateCallStatus('ongoing');
                        callStore.setInCall(true);
                    } 
                    // Kalau semuanya nolak, Host otomatis kita paksa keluar modalnya!
                    else if (isAllDeclinedOrLeft && callStore.callStatus === 'calling') {
                        setTimeout(async () => {
                            toast.info("Panggilan tidak ada yang menerima.");
                            await leaveGroupVideoCall(callData.id);
                        }, 1000); // Dijeda 1 detik biar user sempet liat avatarnya merah
                    }
                }
            });

            toast.success('Memulai panggilan video grup...');
        } catch (error: any) {
            console.error('Gagal memulai panggilan video grup:', error);
            const errMsg =
                error.response?.data?.message ||
                error.response?.data?.error ||
                'Gagal memulai panggilan';
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

            callStore.clearIncomingCall();

            await joinChannel(response.channel_name, response.token, authStore.user?.id as number);

            // Kirim status accepted ke host agar caller pindah dari calling -> ongoing.
            const hostId = data.host_id || data.host?.id;
            if (hostId && hostId !== authStore.user?.id) {
                const hostStatusRef = dbRef(database, `calls/${hostId}/status`);
                await set(hostStatusRef, {
                    status: 'accepted',
                    call_id: data.id,
                    call_type: 'group_video',
                    user_id: authStore.user?.id,
                    timestamp: Date.now(),
                });
            }

            const myParticipantRef = dbRef(database, `group_calls/${callId}/participants/${authStore.user?.id}`);
            await set(myParticipantRef, { status: 'joined', timestamp: Date.now() });

            toast.success('Bergabung ke panggilan video...');
        } catch (error: any) {
            console.error('Gagal menjawab panggilan:', error);
            toast.error('Gagal bergabung ke panggilan.');
        } finally {
            processing.value = false;
        }
    };

    const rejectGroupVideoCall = async (callId: number) => {
        if (callStore.callStatus === 'calling') {
            try {
                // Tembak backend jika ada endpointnya (dibiarkan opsional lewat leaveGroupCall)
                await callServices.leaveGroupCall(callId);
            } catch (error) {
                console.error('Backend leave/cancel error:', error);
            }

            // 🔥 Tembak status Cancelled (Missed) ke SEMUA peserta di Firebase agar dering mati
            const participants = callStore.groupParticipants || [];
            participants.forEach((p: any) => {
                const participantId = typeof p === 'object' ? (p.user_id || p.id) : p;
                
                if (participantId && participantId !== authStore.user?.id) {
                    const statusRef = dbRef(database, `calls/${participantId}/status`);
                    set(statusRef, {
                        status: 'cancelled',
                        call_id: callId,
                        call_type: 'group_video',
                        timestamp: Date.now()
                    });
                }
            });

            await stopAndClearCall();
            return;
        }

        try {
            await callServices.rejectGroupCall(callId);
            const myParticipantRef = dbRef(database, `group_calls/${callId}/participants/${authStore.user?.id}`);
            await set(myParticipantRef, { status: 'declined', timestamp: Date.now() });
            callStore.clearIncomingCall();
        } catch (error) {
            console.error('Gagal menolak panggilan:', error);
        }
    };

    const leaveGroupVideoCall = async (callId: number) => {
        try {
            await callServices.leaveGroupCall(callId);
            const myParticipantRef = dbRef(database, `group_calls/${callId}/participants/${authStore.user?.id}`);
            await set(myParticipantRef, { status: 'left', timestamp: Date.now() });
            await stopAndClearCall();
        } catch (error) {
            console.error('Gagal keluar panggilan:', error);
        }
    };

    const endGroupVideoCallForAll = async (callId: number) => {
        try {
            await callServices.endGroupCallForAll(callId);
            await stopAndClearCall();
        } catch (error) {
            console.error('Gagal membubarkan panggilan:', error);
            toast.error('Gagal membubarkan panggilan.');
        }
    };
    const recallParticipant = async (callId: number, userId: number) => {
        try {
            await callServices.recallGroupParticipant(callId, userId);
            toast.success('Peserta dipanggil ulang');
        } catch (error) {
            console.error('Gagal memanggil ulang:', error);
        }
    };

    const handleGroupIncomingCall = (payload: any) => {
        console.log('Incoming Group Video:', payload);

        callStore.isGroupCall = true;
        callStore.updateCallStatus('ringing');

        callStore.setIncomingCall({
            id: payload.call_id,
            type: 'video',
            isGroup: true,
            status: 'ringing',
            channelName: payload.channel_name,
            channel: payload.channel_name,
            token: payload.agora_token || payload.token || '',
            caller: payload.caller,
            receiver: authStore.user as User,
            groupName: payload.group_name || 'Group Call',
            groupAvatar: payload.group_avatar || '',
            participants: payload.participants || [],
        } as any);
    };

    const handleGroupCallAnswered = (payload: any) => {
        console.log('Group Call Answered:', payload);

        const userId = payload.user?.id || payload.user_id || payload.id;
        if (userId) {
            callStore.updateGroupParticipantStatus(userId, payload.accepted ? 'joined' : 'declined');
        }

        if ((payload.accepted || payload.status === 'accepted') && callStore.callStatus === 'calling') {
            callStore.updateCallStatus('ongoing');
            callStore.setInCall(true);
        }
    };

    const handleGroupParticipantLeft = (payload: any) => {
        console.log('Participant Left/Declined:', payload);
        const userId = payload.user?.id || payload.user_id || payload.id;
        if (userId) {
            callStore.updateGroupParticipantStatus(userId, payload.status || 'left');
        }
    };

    const handleGroupParticipantRecalled = (payload: any) => {
        console.log('Participant Recalled:', payload);
        const userId = payload.user?.id || payload.user_id || payload.id;
        if (userId) {
            callStore.setParticipantRecalled(userId);
        }
    };

    const handleGroupCallEnded = async (payload: any) => {
        console.log('Host Ended Group Call:', payload);
        toast.info('Panggilan video grup telah diakhiri oleh Host.');
        await stopAndClearCall();
    };

    const handleGroupCallCancelled = async (payload: any) => {
        console.log('Call Cancelled:', payload);
        if (callStore.incomingCall) {
            toast.info('Panggilan dibatalkan.');
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
