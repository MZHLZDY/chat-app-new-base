import { ref } from 'vue';
import { database } from '@/libs/firebase';
import { ref as dbRef, set, remove, onValue, off } from 'firebase/database';
import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from '@/stores/auth'; // Sesuaikan path jika berbeda
import { useAgora } from '@/composables/useAgora';
import axios from '@/libs/axios';
import { toast } from 'vue3-toastify';
import type { User } from '@/types/call';
import { upgradeCallToGroup } from '@/services/callServices';

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

        if (callStore.backendGroupCall?.id) {
            const participantsRef = dbRef(database, `group_calls/${callStore.backendGroupCall.id}/participants`);
            off(participantsRef); // Matikan pendengar dari Firebase
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
        if (!groupId || !participantIds || participantIds.length === 0) {
            toast.error("Gagal: Data grup atau peserta tidak valid.");
            return;
        }

        processing.value = true;
        try {
            const response = await axios.post('/group-call/invite', { 
                group_id: groupId,
                participants: participantIds, 
                call_type: 'voice' 
            });

            const data = response.data;

            // 1. SET SEMUA STATE UI DULU AGAR MODAL LANGSUNG MUNCUL
            callStore.isGroupCall = true;
            callStore.backendGroupCall = data.call;
            callStore.groupParticipants = data.call.participants || [];
            
            callStore.setBackendGroupCall(data.call, data.token, data.channel_name);
            callStore.setCurrentCall({
                id: data.call.id,
                type: 'voice', 
                isGroup: true,
                status: 'calling',
                channelName: data.channel_name,
                caller: authStore.user as User,
                receiver: authStore.user as User, 
            } as any);

            // Munculkan layar Calling SEKARANG JUGA
            callStore.updateCallStatus('calling');
            callStore.setInCall(true);

            // 2. KIRIM UNDANGAN KE FIREBASE SECEPATNYA 
            participantIds.forEach((participantId) => {
                if (participantId !== authStore.user?.id) {
                    const incomingRef = dbRef(database, `calls/${participantId}/incoming`);
                    set(incomingRef, {
                        // ... [kode bawaan kamu biarkan sama]
                        call_id: data.call.id,
                        call_type: 'group_voice', 
                        channel_name: data.channel_name,
                        caller: authStore.user,
                        group: data.call.group || { id: groupId, name: callStore.activeGroupName || 'Group Call', photo: callStore.activeGroupAvatar || '' },
                        participants: data.call.participants || participantIds,
                        token: data.token || '', 
                        timestamp: Date.now()
                    });
                }
            });

            // 🔥 [FIX 1] HOST HARUS MENULIS DATANYA SENDIRI KE FIREBASE AGAR PESERTA LAIN TAHU PROFIL HOST
            const myParticipantRef = dbRef(database, `group_calls/${data.call.id}/participants/${authStore.user?.id}`);
            set(myParticipantRef, { 
                status: 'joined', 
                timestamp: Date.now(),
                user_id: authStore.user?.id,
                name: authStore.user?.name,
                photo: authStore.user?.photo || (authStore.user as any)?.profile_photo_url || '',
            });

            // 🔥 Langsung update diri Host sendiri di Store
            callStore.updateGroupParticipantStatus(authStore.user?.id as number, 'joined');
            callStore.updateGroupParticipantInfo(authStore.user?.id as number, {
                name: authStore.user?.name,
                photo: authStore.user?.photo || (authStore.user as any)?.profile_photo_url || '',
            });

            /// 3. PASANG LISTENER STATUS PARTICIPANT
            const participantsRef = dbRef(database, `group_calls/${data.call.id}/participants`);
            onValue(participantsRef, (snapshot) => {
                if (callStore.backendGroupCall?.call_type !== 'voice' && callStore.currentCall?.type !== 'voice') return;

                if (snapshot.exists()) {
                    const participantsData = snapshot.val();
                    const hostIdStr = String(authStore.user?.id);
                    let foundOtherParticipantJoined = false;

                    Object.entries(participantsData).forEach(([key, participantState]: [string, any]) => {
                        const actualUserId = Number(participantState.user_id || participantState.id || key);

                        callStore.updateGroupParticipantStatus(actualUserId, participantState.status);

                        // 🔥 [FIX 2] HOST HARUS UPDATE INFO JIKA CALLEE MENGIRIM NAMA/FOTO KE FIREBASE
                        if (participantState.name || participantState.photo) {
                            callStore.updateGroupParticipantInfo(actualUserId, {
                                name: participantState.name,
                                photo: participantState.photo,
                            });
                        }

                        if (String(actualUserId) !== hostIdStr && participantState.status === 'joined') {
                            foundOtherParticipantJoined = true;
                        }
                    });

                    if (foundOtherParticipantJoined && callStore.callStatus === 'calling') {
                        callStore.updateCallStatus('ongoing');
                    }
                }
            });

            // ==========================================
            // 4. JOIN AGORA DI LATAR BELAKANG (PERUBAHAN UTAMA)
            // ==========================================
            // Kita hapus "await" di sini dan gunakan .then() dan .catch().
            // Dengan begini, Modal Calling akan langsung muncul tanpa harus 
            // menunggu loading izin Mikrofon/Kamera dari browser!
            joinChannel(data.channel_name, data.token, authStore.user?.id as number)
                .then(() => {
                    console.log('✅ Host berhasil terhubung ke jaringan Agora (Background)');
                })
                .catch((err) => {
                    console.error('❌ Gagal join Agora:', err);
                    toast.error("Gagal mengakses mikrofon atau terhubung ke server panggilan.");
                    stopAndClearCall();
                });

            toast.success("Memulai panggilan grup...");
        } catch (error: any) {
            console.error('❌ Gagal memulai panggilan grup:', error);
            toast.error("Gagal memulai panggilan grup");
            stopAndClearCall();
        } finally {
            processing.value = false;
        }
    };

    const answerGroupVoiceCall = async (callId: number) => {
        try {
            // Hapus node incoming agar modal pop-up hilang
            const userIncomingRef = dbRef(database, `users/${authStore.user?.id}/incoming_group_calls`);
            remove(userIncomingRef);

            const incoming = callStore.incomingCall;
            if (!incoming) return;

            // 1. Hit API untuk konfirmasi join ke backend sekaligus mengambil TOKEN BARU & DATA GRUP
            const response = await axios.post('/group-call/answer', { call_id: callId });
            
            const newToken = response.data.token || response.data.agora_token;
            const channel = incoming.channel;

            if (!newToken) {
                toast.error("Gagal mendapatkan token dari server");
                return;
            }

            // ====================================================================
            // 🔥 [FIX 1] SIMPAN DATA BACKEND KE STORE AGAR UI TIDAK KOSONG
            // ====================================================================
            if (response.data.call) {
                callStore.setBackendGroupCall(response.data.call, newToken, channel);
                callStore.groupParticipants = response.data.call.participants || [];
            }

            // 2. Set State agar Modal Ongoing Muncul
            callStore.isGroupCall = true;
            callStore.isGroupCall = true;
            callStore.setCurrentCall({
                id: incoming.id,
                type: 'voice',
                isGroup: true,
                status: 'ongoing',
                channelName: channel,
                caller: incoming.caller,
                receiver: authStore.user as any,
            } as any);
            
            // PASTIKAN DUA FUNGSI DI BAWAH INI HANYA PUNYA 1 ARGUMEN (Jangan ada koma dan variabel lain di dalamnya)
            callStore.updateCallStatus('ongoing');
            callStore.setInCall(true);
            callStore.isMinimized = false; // Paksa modal buka layar penuh

            // Bersihkan modal incoming
            callStore.clearIncomingCall();

            // 3. Join Agora menggunakan TOKEN BARU milik peserta
            await joinChannel(channel, newToken, authStore.user?.id as number);

            // ====================================================================
            // 🔥 [FIX 2] LISTENER REAL-TIME FIREBASE UNTUK PESERTA (CALLEE)
            // ====================================================================
            const participantsRef = dbRef(database, `group_calls/${callId}/participants`);
            
            // a. Lapor ke Firebase kalau kita sudah join
            const myParticipantRef = dbRef(database, `group_calls/${callId}/participants/${authStore.user?.id}`);
            set(myParticipantRef, { 
                status: 'joined', 
                timestamp: Date.now(),
                user_id: authStore.user?.id,
                name: authStore.user?.name,
                photo: authStore.user?.photo || (authStore.user as any)?.profile_photo_url || '',
            });

            // 🔥 [FIX] Langsung update info diri sendiri di store (jangan tunggu listener)
            // karena listener mungkin tidak match akibat type mismatch user_id (string vs number)
            callStore.updateGroupParticipantStatus(authStore.user?.id as number, 'joined');
            callStore.updateGroupParticipantInfo(authStore.user?.id as number, {
                name: authStore.user?.name,
                photo: authStore.user?.photo || (authStore.user as any)?.profile_photo_url || '',
            });

            // b. Dengarkan perubahan dari peserta lain (Real-time update UI)
            onValue(participantsRef, (snapshot) => {
                if (snapshot.exists()) {
                    const participantsData = snapshot.val();

                    // 🔥 [FIX] Selalu konversi ke Number agar cocok dengan store (string vs number mismatch)
                    Object.entries(participantsData).forEach(([key, participantState]: [string, any]) => {
                        const actualUserId = Number(participantState.user_id || key);

                        callStore.updateGroupParticipantStatus(actualUserId, participantState.status);

                        if (participantState.name || participantState.photo) {
                            callStore.updateGroupParticipantInfo(actualUserId, {
                                name: participantState.name,
                                photo: participantState.photo,
                            });
                        }
                    });
                }
            });

            // c. Beri tahu Host bahwa panggilan sudah diterima (jaga-jaga jika Host nyangkut)
            if (incoming.caller?.id) {
                const hostStatusRef = dbRef(database, `calls/${incoming.caller.id}/status`);
                set(hostStatusRef, {
                    status: 'accepted',
                    call_id: callId,
                    call_type: 'group_voice',
                    user_id: authStore.user?.id,
                    timestamp: Date.now()
                });
            }

        } catch (error) {
            console.error('❌ Gagal menerima panggilan grup:', error);
            toast.error("Gagal terhubung ke panggilan. Pastikan token valid.");
            stopAndClearCall();
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
        // Jika statusnya masih 'calling', berarti Host membatalkan panggilan (Cancel)
        if (callStore.callStatus === 'calling') {
            try {
                await axios.post('/group-call/cancel', { call_id: callId });
            } catch (error) {
                console.error('Backend cancel error:', error);
            }

            // Broadcast Cancel ke semua node 'status' peserta
            const participants = callStore.groupParticipants || [];
            participants.forEach((p: any) => {
                const participantId = typeof p === 'object' ? (p.user_id || p.id) : p;
                if (participantId && participantId !== authStore.user?.id) {
                    const statusRef = dbRef(database, `calls/${participantId}/status`);
                    set(statusRef, {
                        status: 'cancelled', 
                        call_id: callId,
                        call_type: 'group_voice', 
                        timestamp: Date.now()
                    });
                }
            });
            toast.info("Panggilan grup dibatalkan.");
        } else {
            // =========================================================
            // 🔥 [FIX] Jika status 'ongoing', user keluar dari panggilan (Leave)
            // =========================================================
            try {
                await axios.post('/group-call/leave', { call_id: callId });
                
                // Beritahu Firebase bahwa kita 'left' agar UI peserta lain (terutama Host) terupdate
                const myParticipantRef = dbRef(database, `group_calls/${callId}/participants/${authStore.user?.id}`);
                set(myParticipantRef, { 
                    status: 'left', 
                    timestamp: Date.now(),
                    user_id: authStore.user?.id,
                });
            } catch (error) {
                console.error('❌ Gagal leave panggilan:', error);
            }
            toast.info("Anda keluar dari panggilan grup.");
        }

        // Hapus state dan matikan Agora
        await stopAndClearCall();
    };

    const endGroupVoiceCallForAll = async (callId: number) => {
        try {
            // 1. Tembak API Backend
            await axios.post('/group-call/end-all', { call_id: callId });

            // =========================================================
            // 🔥 [FIX] Broadcast 'ended' via Firebase ke semua peserta 
            // =========================================================
            const participants = callStore.groupParticipants || [];
            participants.forEach((p: any) => {
                const participantId = typeof p === 'object' ? (p.user_id || p.id) : p;
                
                // Jangan kirim ke diri sendiri (host)
                if (participantId && participantId !== authStore.user?.id) {
                    const statusRef = dbRef(database, `calls/${participantId}/status`);
                    set(statusRef, {
                        status: 'ended', // 👈 Ini akan memicu listener 'ended' di DefaultLayout peserta
                        call_id: callId,
                        call_type: 'group_voice',
                        timestamp: Date.now()
                    });
                }
            });
            // =========================================================

            // 2. Bersihkan UI Host
            await stopAndClearCall();
            toast.info("Anda telah membubarkan panggilan grup.");
        } catch (error) {
            console.error('❌ Gagal membubarkan panggilan:', error);
            toast.error("Terjadi kesalahan saat membubarkan panggilan.");
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

    // 🔥 FUNGSI BARU: Transisi dari Personal ke Group Call
    const upgradeToGroupCall = async (newParticipantIds: number[]) => {
        if (!callStore.currentCall) return;
        processing.value = true;

        try {
            // 1. Tentukan siapa lawan bicara (callee/caller) yang lama
            const currentOpponentId = callStore.currentCall.caller.id === authStore.user?.id 
                ? callStore.currentCall.receiver.id 
                : callStore.currentCall.caller.id;

            // Gabungkan ID peserta lama dan peserta baru
            const allParticipantIds = [currentOpponentId, ...newParticipantIds];

            // 2. Request ke Backend API untuk membuat Panggilan Grup Baru
            // Memanggil endpoint Laravel via fungsi service (callServices.ts)
            const data = await upgradeCallToGroup(callStore.currentCall.id, allParticipantIds);

            // 3. Tinggalkan channel Panggilan Personal (Agora)
            await leaveChannel();

            // Opsional: Hapus record panggilan personal di Firebase untuk lawan bicara saat ini
            const personalIncomingRef = dbRef(database, `calls/${currentOpponentId}/incoming`);
            await remove(personalIncomingRef);

            // 4. Update Store ke Mode Panggilan Grup secara terpusat
            callStore.transitionToGroupCall(data.call, allParticipantIds);

            // 5. Join Channel Grup Baru (Agora)
            await joinChannel(data.token, data.channel_name, authStore.user?.id as number);

            // 6. Kirim undangan Firebase ke SEMUA peserta (Lama & Baru)
            allParticipantIds.forEach((participantId) => {
                if (participantId !== authStore.user?.id) {
                    const incomingRef = dbRef(database, `calls/${participantId}/incoming`);
                    set(incomingRef, {
                        call_id: data.call.id,
                        call_type: 'group_voice',
                        channel_name: data.channel_name,
                        caller: authStore.user,
                        group: data.call.group || { id: 'upgrade', name: 'Group Call', photo: '' }, // Fallback info grup
                        participants: allParticipantIds,
                        token: data.token || '',
                        timestamp: Date.now(),
                        is_upgrade: true // 🔥 Penanda khusus agar UI lawan bicara bisa transisi mulus
                    });
                }
            });

            // 7. Host mendaftarkan dirinya sendiri ke Firebase
            const myParticipantRef = dbRef(database, `group_calls/${data.call.id}/participants/${authStore.user?.id}`);
            set(myParticipantRef, { 
                status: 'joined', 
                timestamp: Date.now(),
                user_id: authStore.user?.id,
                name: authStore.user?.name,
                photo: authStore.user?.photo || (authStore.user as any)?.profile_photo_url || '',
            });

            callStore.updateGroupParticipantStatus(authStore.user?.id as number, 'joined');
            callStore.updateGroupParticipantInfo(authStore.user?.id as number, {
                name: authStore.user?.name,
                photo: authStore.user?.photo || (authStore.user as any)?.profile_photo_url || '',
            });

            // 8. Pasang Listener untuk memantau status peserta yang diundang
            const participantsRef = dbRef(database, `group_calls/${data.call.id}/participants`);
            onValue(participantsRef, (snapshot) => {
                if (!callStore.isGroupCall) return;

                if (snapshot.exists()) {
                    const participantsData = snapshot.val();
                    Object.entries(participantsData).forEach(([key, participantState]: [string, any]) => {
                        const actualUserId = Number(participantState.user_id || participantState.id || key);
                        
                        // Update status (ringing/joined/rejected)
                        callStore.updateGroupParticipantStatus(actualUserId, participantState.status);

                        // Update profil jika mereka mengirimkan data
                        if (participantState.name || participantState.photo) {
                            callStore.updateGroupParticipantInfo(actualUserId, {
                                name: participantState.name,
                                photo: participantState.photo,
                            });
                        }
                    });
                }
            });

        } catch (error) {
            console.error('❌ Gagal upgrade ke group call:', error);
            toast.error("Gagal menambahkan peserta, silakan coba lagi.");
        } finally {
            processing.value = false;
        }
    };

    // ======================================================
    // 2. FIREBASE EVENT HANDLERS (Dipanggil dari Global Listener)
    // ======================================================

    const handleGroupIncomingCall = (payload: any) => {
        console.log('📡 [Firebase] Incoming Group Call:', payload);

        // Set state incoming call ke store
        callStore.incomingCall = {
            id: payload.call_id || payload.id,
            type: 'voice', // Wajib ada di interface Call
            status: 'incoming', // Wajib ada
            isGroup: true,
            caller: payload.host || payload.caller,
            receiver: authStore.user, // Wajib ada
            token: payload.token || '', // Wajib ada
            channel: payload.channel_name || '', // Wajib ada
            
            // --- Custom properties ---
            channelName: payload.channel_name,
            groupName: payload.group?.name || payload.group_name || 'Group Call',
            groupAvatar: payload.group?.photo || payload.group?.avatar || '',
            participants: payload.participants || [],
        } as any;

        // Opsional: Mainkan nada dering panggilan masuk di sini
        // playIncomingRingtone();
    };

    const handleGroupVoiceCallAnswered = async (payload: any) => {
    // 1. GUARD: Hentikan kalau ini bukan panggilan Voice!
    if (callStore.backendGroupCall?.call_type !== 'voice' && callStore.currentCall?.type !== 'voice') return;

    console.log('📡 [Firebase/Event] Participant Answered/Joined:', payload);
    
    // 2. Update status partisipan di store Pinia
    callStore.updateGroupParticipantStatus(payload.user.id, 'joined');

    // 3. JIKA KITA HOST, ubah ke 'ongoing' HANYA jika yang join BUKAN diri kita sendiri
    if (
        callStore.currentCall && 
        callStore.callStatus === 'calling' && 
        Number(payload.user.id) !== Number(authStore.user?.id) // <--- INI KUNCI FIX-NYA
    ) {
        console.log("✅ Orang lain benar-benar angkat telpon, pindah ke layar Stream/Ongoing!");
        callStore.updateCallStatus('ongoing');
    }
};

    const handleGroupParticipantLeft = (payload: any) => {
        if (callStore.backendGroupCall?.call_type !== 'voice' && callStore.currentCall?.type !== 'voice') return;
        console.log('📡 [Firebase] Participant Left/Declined:', payload);
        callStore.updateGroupParticipantStatus(payload.user.id, payload.status || 'left');
    };

    const handleGroupParticipantRecalled = (payload: any) => {
        if (callStore.backendGroupCall?.call_type !== 'voice' && callStore.currentCall?.type !== 'voice') return;
        console.log('📡 [Firebase] Participant Recalled:', payload);
        callStore.updateGroupParticipantStatus(payload.user.id, 'ringing');
    };

    const handleGroupCallEnded = async (payload: any) => {
        if (callStore.backendGroupCall?.call_type !== 'voice' && callStore.currentCall?.type !== 'voice') return;
        console.log('📡 [Firebase] Host Ended Group Call:', payload);
        toast.info("Panggilan grup telah diakhiri oleh Host.");
        await stopAndClearCall();
    };

    const handleGroupCallCancelled = async (payload: any) => {
        if (callStore.backendGroupCall?.call_type !== 'voice' && callStore.currentCall?.type !== 'voice') return;
        console.log('📡 [Firebase] Call Cancelled:', payload);
        if (callStore.incomingCall) {
            toast.info("Panggilan grup dibatalkan.");
        }
        await stopAndClearCall();
        toast.info("Panggilan grup dibatalkan oleh host");
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
        upgradeToGroupCall,
        toggleMute: toggleAudio,
        handleGroupIncomingCall,
        handleGroupVoiceCallAnswered,
        handleGroupParticipantLeft,
        handleGroupParticipantRecalled,
        handleGroupCallEnded,
        handleGroupCallCancelled,
    };
}