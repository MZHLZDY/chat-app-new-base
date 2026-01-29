import { defineStore } from "pinia";
import { ref } from "vue";
import type { Call, CallStatus, PersonalCall } from "@/types/call";

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
        currentCall.value = null;
        callStatus.value = null;
        isInCall.value = false;
        backendCall.value = null;
        agoraToken.value = null;
        channelName.value = null;
        remoteUsers.value = [];
        hasJoinedAgora.value = false;
    };

    const setIncomingCall = (call: Call) => {
        incomingCall.value = call;
    };

    const clearIncomingCall = () => {
        incomingCall.value = null;
        hasJoinedAgora.value = false;
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

    const setHasJoinedAgora = (value: boolean) => {
        hasJoinedAgora.value = value;
        console.log('✅ callStore.hasJoinedAgora set di:', value);
    }

    // fitur voice
    const toggleMinimize = () => {
        isMinimized.value = !isMinimized.value;
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
        // Actions
        setCurrentCall,
        clearCurrentCall,
        setIncomingCall,
        clearIncomingCall,
        updateCallStatus,
        setInCall,
        addRemoteUser,
        removeRemoteUser,
        clearRemoteUsers,
        toggleMinimize,
        setBackendCall,
        updateBackendCall,
        setHasJoinedAgora,
    }
});