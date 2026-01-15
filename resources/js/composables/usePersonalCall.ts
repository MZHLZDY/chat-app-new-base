import { ref } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from '@/stores/authStore';  // Asumsi ada auth store
import * as callService from '@/services/callServices';
import type { CallStatus, CallType, User, PersonalCall } from '@/types/call';

export const usePersonalCall = () => {
    const callStore = useCallStore();
    const authStore = useAuthStore();
    
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // Invite call (caller)
    const initiateCall = async (callee: User, callType: CallType) => {
        // Validasi authStore.user
        if (!authStore.user?.id) {
            error.value = 'User belum login';
            throw new Error('User belum login');
        }

        // Validasi callee
        if (!callee?.id) {
            error.value = 'Penerima panggilan tidak valid';
            throw new Error('Penerima panggilan tidak valid');
        }

        try {
            isLoading.value = true;
            error.value = null;

            console.log('📞Memulai panggilan ke:', callee.name, 'Type:', callType);

            // Hit API backend
            const response = await callService.inviteCall(callee.id, callType);

            console.log('✅ Respon API:', response);

            // Parse response sesuai struktur backend
            const callId = response.call_id;
            const token = response.agora_token;
            const channelName = response.channel_name;
            const status= response.status || 'ringing';

            console.log('📦 Data diuraikan:', { callId, token, channelName, status });

            if (!callId || !token || !channelName) {
                console.error('❌ Bidang yang diperlukan hilang:', {
                    hasCallId: !!callId,
                    hasToken: !!token,
                    hasChannelName: !!channelName,
                });
                throw new Error('Respon tidak valid dari server');
            }

            // Buat personal object lengkap
            const backendCall: PersonalCall = {
                id: callId,
                caller_id: authStore.user!.id,
                callee_id: callee.id,
                call_type: callType,
                channel_name: channelName,
                status: status,
                answered_at: null,
                ended_at: null,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
                duration: null,
                ended_by: null,
            }

            // Simpan data backend ke store
            callStore.setBackendCall(
                backendCall,
                token,
                channelName
            );

            // Set current call (untuk UI)
            callStore.setCurrentCall({
                id: callId,
                type: callType,
                caller: authStore.user!,  // User yang login
                receiver: callee,
                status: status as CallStatus,
                token: token,
                channel: channelName,
            });

            console.log('✅ Memulai panggilan berhasil');

            // Log state setelah set
            console.log('State setelah setCurrentCall');
            console.log('callStore.currentCall:', callStore.currentCall);
            console.log('callStore.backendCall:', callStore.backendCall);
            console.log('callStore.agoraToken:', callStore.agoraToken);
            console.log('callStore.channelName:', callStore.channelName);

            return response;
        } catch (err: any) {
            console.error('❌ Gagal untuk memulai panggilan:', err);
            error.value = err.response?.data?.message || 'Gagal untuk menginisiasi panggilan';

            // Clear current call jika ada error
            callStore.clearCurrentCall();

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