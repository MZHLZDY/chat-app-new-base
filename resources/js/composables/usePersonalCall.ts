import { ref } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useAgora } from './useAgora';
import { useAuthStore } from '@/stores/authStore';  // Asumsi ada auth store
import * as callService from '@/services/callServices';
import type { CallStatus, CallType, User, PersonalCall } from '@/types/call';
import { ref as dbRef, set } from 'firebase/database';
import { database } from '@/libs/firebase';
import { ca } from 'date-fns/locale';

export const usePersonalCall = () => {
    const callStore = useCallStore();
    const authStore = useAuthStore();
    const { joinChannel ,leaveChannel } = useAgora();
    
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

            await joinChannel(
                channelName,
                token,
                Number(authStore.user.id)
            );

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

            console.log('✅ usePersonalCall: answerCall dipanggil');
            console.log('📦 Call ID:', callId);

            const response = await callService.answerCall(callId);

            console.log('✅ API /call/answer berhasil');
            console.log('📦 Full Response:', JSON.stringify(response, null, 2)); // Lihat struktur response secara lengkap

            // Clear incoming call dulu sebelum update status
            callStore.clearIncomingCall();

            // Update status di store
            callStore.updateCallStatus('ongoing');
            callStore.setInCall(true);

            // Cek berbagai kemungkinan struktur response
            let callData = null;

            // Reconstruct call object dari response
            if (response.call_id && callStore.backendCall) {
                // Response punya call_id. tapi gapunya full call object (jadi kita update backendCall yang udah ada dari incoming)
                
                console.log('📦 Memperbarui backendCall dengan data dari response');

                callStore.updateBackendCall({
                    ...callStore.backendCall,
                    id: response.call_id,
                    status: response.status || 'ongoing',
                    answered_at: new Date().toISOString(),
                    channel_name: response.channel_name || callStore.backendCall.channel_name,
                });

                // Update agora token dan channel kalau ada response
                if (response.agora_token) {
                    callStore.agoraToken = response.agora_token;
                }
                if (response.channel_name) {
                    callStore.channelName = response.channel_name;
                }

                console.log('✅ Backend Call berhasil diupdate');

            } else if (response.call) {
                // Kalau response punya full call object
                console.log('📦 Memperbarui dengan response.call');
                callStore.updateBackendCall(response.call as PersonalCall);

            } else if (response.id) {
                // Struktur 3: response langsung adalah call object
                console.log('📦 Memperbarui dengan response langsung');
                callStore.updateBackendCall(response as PersonalCall);

            } else {
                console.warn('⚠️ Response tidak memiliki data panggilan yang valid');
                console.warn('📦 Response keys:', Object.keys(response));

                // Fallback: Update backendCall yang ada dengan status ongoing
                if (callStore.backendCall) {
                    console.log('📦 Fallback: update status backendCall yang ada');
                    callStore.updateBackendCall({
                        ...callStore.backendCall,
                        status: 'ongoing',
                        answered_at: new Date().toISOString(),
                    });
                }
            }

            return response;
        } catch (err: any) {
            console.error('❌ Gagal untuk menjawab panggilan:', err);
            error.value = err.response?.data?.message || 'Gagal untuk menjawab panggilan';

            // Clear incoming call jika ada error
            callStore.clearIncomingCall();

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

            console.log('🔚 usePersonalCall: endCall dipanggil');
            console.log('📦 Call ID:', callId);

            // Hit API backend
            const response = await callService.endCall(callId);

            console.log('✅ API /call/end berhasil');

            // Update status
            callStore.updateCallStatus('ended');

            if (response.call) {
                callStore.updateBackendCall(response.call);
            }

            // Broadcast ke firebase (notify remote user)
            if (authStore.user?.id && callStore.currentCall) {
                const remoteUserId =
                    callStore.currentCall.caller.id === authStore.user.id
                        ? callStore.currentCall.receiver?.id
                        : callStore.currentCall.caller.id;

                if (remoteUserId) {
                    console.log('📡 Status broadcasting "ended" ke firebase...') ;
                    const statusRef = dbRef(database, `calls/${remoteUserId}/status`);
                    await set(statusRef, {
                        status: 'ended',
                        call_type: 'video',
                        ended_by: authStore.user.id,
                        timestamp: Date.now(),
                        
                    });
                    console.log('✅ Status "ended" berhasil dikirim ke firebase');
                }
            }

            // Clear after 2 seconds (biar user liat "Call ended")
            setTimeout(() => {
                callStore.clearCurrentCall();
                callStore.clearIncomingCall();
            }, 2000);

            return response;

        } catch (err: any) {
            console.error('❌ Gagal untuk mengakhiri panggilan:', err);
            error.value = err.response?.data?.message || 'Gagal untuk mengakhiri panggilan';
            
            // Cleanup secara paksa jika error
            callStore.clearCurrentCall();
            callStore.clearIncomingCall();

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