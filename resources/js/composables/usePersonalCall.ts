import { ref } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from '@/stores/authStore';  // Asumsi ada auth store
import * as callService from '@/services/callServices';
import type { CallType, User } from '@/types/call';

export const usePersonalCall = () => {
    const callStore = useCallStore();
    const authStore = useAuthStore();
    
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // Invite call (caller)
    const initiateCall = async (callee: User, callType: CallType) => {
        try {
            isLoading.value = true;
            error.value = null;

            // Hit API backend
            const response = await callService.inviteCall(callee.id, callType);

            // Simpan data backend ke store
            callStore.setBackendCall(
                response.call,
                response.token,
                response.channel_name
            );

            // Set current call (untuk UI)
            callStore.setCurrentCall({
                id: response.call.id,
                type: callType,
                caller: authStore.user!,  // User yang login
                receiver: callee,
                status: 'ringing',
                token: response.token,
                channel: response.channel_name,
            });

            return response;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Gagal untuk menginisiasi panggilan';
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    // Answer call (callee)
    const answerCall = async (callId: number) => {
        try {
            isLoading.value = true;
            error.value = null;

            const response = await callService.answerCall(callId);

            // Update status di store
            callStore.updateCallStatus('ongoing');
            callStore.updateBackendCall(response.call);

            return response;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Gagal untuk menjawab panggilan';
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    // Reject call (callee)
    const rejectCall = async (callId: number) => {
        try {
            isLoading.value = true;
            error.value = null;

            const response = await callService.rejectCall(callId);

            // Clear call
            callStore.clearIncomingCall();
            callStore.clearCurrentCall();

            return response;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Gagal untuk menolak panggilan';
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    // Cancel call (caller)
    const cancelCall = async (callId: number) => {
        try {
            isLoading.value = true;
            error.value = null;

            const response = await callService.cancelCall(callId);

            // Clear call
            callStore.clearCurrentCall();

            return response;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Gagal untuk membatalkan panggilan';
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    // End call
    const endCall = async (callId: number) => {
        try {
            isLoading.value = true;
            error.value = null;

            const response = await callService.endCall(callId);

            // Update status
            callStore.updateCallStatus('ended');
            callStore.updateBackendCall(response.call);

            // Clear after 2 seconds (biar user liat "Call ended")
            setTimeout(() => {
                callStore.clearCurrentCall();
            }, 2000);

            return response;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Gagal untuk mengakhiri panggilan';
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    return {
        isLoading,
        error,
        initiateCall,
        answerCall,
        rejectCall,
        cancelCall,
        endCall,
    };
};