import { defineStore } from "pinia";
import { ref } from "vue";
import type { Call, CallStatus, PersonalCall, GroupCall, GroupParticipant } from "@/types/call";

export const useCallStore = defineStore('call', () => {
    // State
    const currentCall = ref<Call | null>(null);
    const incomingCall = ref<Call | null>(null);
    const isInCall = ref<boolean>(false);
    const callStatus = ref<CallStatus | null>(null);
    const remoteUsers = ref<number[]>([]);
    const isMinimized = ref<boolean>(false);
    const backendCall = ref<PersonalCall | null>(null); // Data dari backend
    const agoraToken = ref<string | null>(null); // token agora dari backend
    const channelName = ref<string | null>(null); // channel name dari backend
    const hasJoinedAgora = ref<boolean>(false); // apakah sudah join agora
    const callTimeout = ref<any>(null); // untuk menyimpan ID timeout (tak ubah jadi any)
    const callTimeoutDuration = ref<number>(30); // durasi timeout dalam detik
    const timerCount = ref<number>(30); // untuk output interval timeout
    const isLocalEnd = ref<boolean>(false);
    // Simpan waktu mulai call
    const callStartTime = ref<number | null>(null);
    // state baru telepon grup
    const isGroupCall = ref<boolean>(false);
    const backendGroupCall = ref<GroupCall | null>(null);
    const groupParticipants = ref<GroupParticipant[]>([]);

    // Actions
    const setCurrentCall = (call: Call) => {
        currentCall.value = call;
        callStatus.value = call.status;
        isInCall.value = call.status === 'ongoing';
        isMinimized.value = false;

        console.log('✅ setCurrentCall:', {
            id: call.id,
            type: call.type,
            status: call.status,
            isInCall: isInCall.value
        });
    };

    const clearCurrentCall = () => {
        clearCallTimeout();
        currentCall.value = null;
        callStatus.value = null;
        isInCall.value = false;
        backendCall.value = null;
        agoraToken.value = null;
        channelName.value = null;
        remoteUsers.value = [];
        hasJoinedAgora.value = false;
        // Reset waktu
        callStartTime.value = null;
    };

    const setIncomingCall = (call: Call) => {
        incomingCall.value = call;
    };

    const clearIncomingCall = () => {
        incomingCall.value = null;
        hasJoinedAgora.value = false;
    };

    const resetLocalEnd = () => {
        isLocalEnd.value = false;
    };

    const setCallStartTime = (time: number) => {
        callStartTime.value = time;
    };

    const updateCallStatus = (status: CallStatus) => {
        callStatus.value = status;
        if (currentCall.value) {
            currentCall.value.status = status;
        }

        if (status === 'ongoing') {
            isInCall.value = true;
        } else if (status === 'ended') {
            isInCall.value = false;
            hasJoinedAgora.value = false;
        }
    };

    const setInCall = (value: boolean) => {
        isInCall.value = value;
    };

    const addRemoteUser = (uid: number) => {
        if (!remoteUsers.value.includes(uid)) {
            remoteUsers.value.push(uid);
        }
    };

    const removeRemoteUser = (uid: number) => {
        const index = remoteUsers.value.indexOf(uid);
        if (index > -1) {
            remoteUsers.value.splice(index, 1);
        }
    };

    const clearRemoteUsers = () => {
        remoteUsers.value = [];
    };

    const setBackendCall = (call: PersonalCall, token: string, channel: string) => {
        backendCall.value = call;
        agoraToken.value = token;
        channelName.value = channel;
    };

    const updateBackendCall = (call: PersonalCall) => {
        backendCall.value = call;
        if (call.status) {
            callStatus.value = call.status;
        }
    };

    const setBackendGroupCall = (call: GroupCall, token: string, channel: string) => {
        backendGroupCall.value = call;
        agoraToken.value = token;
        channelName.value = channel;
        isGroupCall.value = true;
        
        if (call.participants) {
            groupParticipants.value = call.participants;
        }
    };

    const updateGroupParticipantStatus = (userId: number, status: GroupParticipant['status']) => {
        const participant = groupParticipants.value.find(p => p.user_id === userId);
        if (participant) {
            participant.status = status;
        }
    };

    // Fungsi untuk mereset/mengubah status partisipan kembali menjadi 'ringing' saat di-recall
    const setParticipantRecalled = (userId: number) => {
        updateGroupParticipantStatus(userId, 'ringing');
    };

    // Fungsi yang dijalankan ketika panggilan grup dibubarkan oleh Host
    // Fungsi ini dipanggil setelah sukses API endGroupCallForAll atau menerima WebSocket/Pusher 'dismissed'
    const dismissGroupCall = () => {
        console.log('🛑 Panggilan grup telah DIBUBARKAN oleh Host');
        updateCallStatus('ended');
        clearCurrentCall();
    };

    const setHasJoinedAgora = (value: boolean) => {
        hasJoinedAgora.value = value;
        console.log('✅ callStore.hasJoinedAgora set di:', value);
    }

    // fitur voice
    const toggleMinimize = () => {
        isMinimized.value = !isMinimized.value;
    };

    const setCallTimeout = (timeoutId: number | null) => {
        callTimeout.value = timeoutId;
    };

    // Fungsi Baru untuk memulai timeout
    const startCallTimeout = (onTimeout: () => void) => {
        // 1. Bersihkan timer lama
        clearCallTimeout();

        console.log('⏳ Starting Call Countdown...');
        
        // 2. Reset angka ke 30
        timerCount.value = callTimeoutDuration.value;

        // 3. Mulai Interval (Gunakan window.setInterval agar eksplisit browser)
        callTimeout.value = window.setInterval(() => {
            timerCount.value--; // Kurangi angka
            
            console.log("Tick:", timerCount.value); // Cek console browser untuk debug

            // 4. Cek jika waktu habis
            if (timerCount.value <= 0) {
                console.log('⏰ Call Timeout Reached!');
                clearCallTimeout(); // Stop timer
                onTimeout(); // Jalankan callback (cancel/reject)
            }
        }, 1000);
    };

    const clearCallTimeout = () => {
        if (callTimeout.value) {
            clearTimeout(callTimeout.value);
            callTimeout.value = null;
        }
        // timerCount.value = 30;
    };

    return {
        // State
        currentCall,
        incomingCall,
        isInCall,
        callStatus,
        remoteUsers,
        isMinimized,
        backendCall,
        agoraToken,
        channelName,
        hasJoinedAgora,
        callTimeout,
        callTimeoutDuration,
        timerCount,
        isLocalEnd,
        callStartTime,
        isGroupCall,
        backendGroupCall,
        groupParticipants,
        // Actions
        setCurrentCall,
        clearCurrentCall,
        setIncomingCall,
        clearIncomingCall,
        resetLocalEnd,
        updateCallStatus,
        setInCall,
        addRemoteUser,
        removeRemoteUser,
        clearRemoteUsers,
        toggleMinimize,
        setBackendCall,
        updateBackendCall,
        setHasJoinedAgora,
        setCallTimeout,
        startCallTimeout,
        clearCallTimeout,
        setCallStartTime,
        setBackendGroupCall,
        updateGroupParticipantStatus,
        setParticipantRecalled,
        dismissGroupCall,
    }
});