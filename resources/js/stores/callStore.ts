import { defineStore } from "pinia";
import { ref } from "vue";
import type { Call, CallStatus } from "@/types/call";

export const useCallStore = defineStore('call', () => {
    // State
    const currentCall = ref<Call | null>(null);
    const incomingCall = ref<Call | null>(null);
    const isInCall = ref<boolean>(false);
    const callStatus = ref<CallStatus | null>(null);
    const remoteUsers = ref<number[]>([]);

    // Actions
    const setCurrentCall = (call: Call) => {
        currentCall.value = call;
        callStatus.value = call.status;
        isInCall.value = true;
    };

    const clearCurrentCall = () => {
        currentCall.value = null;
        callStatus.value = null;
        isInCall.value = false;
    };

    const setIncomingCall = (call: Call) => {
        incomingCall.value = call;
    };

    const clearIncomingCall = () => {
        incomingCall.value = null;
    };

    const updateCallStatus = (status: CallStatus) => {
        callStatus.value = status;
        if (currentCall.value) {
            currentCall.value.status = status;
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

    return {
        // State
        currentCall,
        incomingCall,
        isInCall,
        callStatus,
        remoteUsers,

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
    }
});