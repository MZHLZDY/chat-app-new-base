import { ref } from 'vue';
import { database } from '@/libs/firebase';
import { ref as dbRef, set, remove, onValue } from 'firebase/database';
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
        callStore.setBackendGroupCall(data.call, data.token, data.channel_name);
        callStore.setCurrentCall({
            id: data.call.id,
            isGroup: true,
            type: 'voice',
            status: 'calling',
            channelName: data.channel_name,
            caller: authStore.user as User,
            receiver: authStore.user as User, 
        } as any);
        
        callStore.updateCallStatus('calling');
        callStore.setInCall(true);

        // Join Agora
        await joinChannel(data.channel_name, data.token, authStore.user?.id as number);
        
        participantIds.forEach((participantId) => {
            if (participantId !== authStore.user?.id) {
                const incomingRef = dbRef(database, `calls/${participantId}/incoming`);
                set(incomingRef, {
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

        // 👇 [TAMBAHKAN INI] Host mendengarkan perubahan status peserta di Firebase 👇
        const participantsRef = dbRef(database, `group_calls/${data.call.id}/participants`);
        onValue(participantsRef, (snapshot) => {
            if (snapshot.exists()) {
                const participantsData = snapshot.val();
                
                // 1. Cek apakah ada minimal 1 peserta yang berstatus 'joined'
                const isAnyoneJoined = Object.values(participantsData).some((p: any) => p.status === 'joined');
                
                // 2. Jika ada yang join dan host masih nyangkut di layar 'calling', pindahkan host ke 'ongoing'
                if (isAnyoneJoined && callStore.callStatus === 'calling') {
                    callStore.updateCallStatus('ongoing');
                }

                // 3. Update status setiap peserta ke store agar UI Grid / Participant List tersinkronisasi
                Object.entries(participantsData).forEach(([userId, participantState]: [string, any]) => {
                    callStore.updateGroupParticipantStatus(Number(userId), participantState.status);
                });
            }
        });
        // 👆 ====================================================================== 👆

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
            set(myParticipantRef, { status: 'joined', timestamp: Date.now() });

            // b. Dengarkan perubahan dari peserta lain (Real-time update UI)
            onValue(participantsRef, (snapshot) => {
                if (snapshot.exists()) {
                    const participantsData = snapshot.val();
                    
                    // Update status peserta lain di UI peserta ini
                    Object.entries(participantsData).forEach(([userId, participantState]: [string, any]) => {
                        callStore.updateGroupParticipantStatus(Number(userId), participantState.status);
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
                // (Opsional) Memanggil API backend untuk update status ke database
                await axios.post('/group-call/cancel', { call_id: callId });
            } catch (error) {
                console.error('Backend cancel error:', error);
            }

            // =========================================================
            // [FIX FIREBASE] Broadcast Cancel ke semua node 'status' peserta
            // =========================================================
            const participants = callStore.groupParticipants || [];
            participants.forEach((p: any) => {
                // Ambil ID peserta dengan aman (tergantung array-nya isinya object atau number langsung)
                const participantId = typeof p === 'object' ? (p.user_id || p.id) : p;
                
                if (participantId && participantId !== authStore.user?.id) {
                    // 🔥 Tembak ke node yang persis didengarkan oleh listener mu
                    const statusRef = dbRef(database, `calls/${participantId}/status`);
                    set(statusRef, {
                        status: 'cancelled', // Wajib ada agar terbaca oleh switch(data.status)
                        call_id: callId,
                        call_type: 'group_voice', // Wajib sama dengan if di listener
                        timestamp: Date.now()
                    });
                }
            });
            // =========================================================
            
            toast.info("Panggilan grup dibatalkan.");
        } else {
            // Jika statusnya 'ongoing', berarti user hanya keluar dari panggilan (Leave)
            try {
                // await axios.post('/group-call/leave', { call_id: callId });
            } catch (error) {}
        }

        // Hapus state dan matikan Agora
        await stopAndClearCall();
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
        console.log('📡 [Firebase] Participant Answered/Joined:', payload);
        
        // Update status partisipan di store
        callStore.updateGroupParticipantStatus(payload.user.id, 'joined');

        // JIKA KITA HOST, ubah ke 'ongoing' HANYA jika yang join BUKAN diri kita sendiri
        if (
            callStore.currentCall && 
            callStore.callStatus === 'calling' && 
            Number(payload.user.id) !== Number(authStore.user?.id) // <--- INI KUNCI FIX-NYA
        ) {
            callStore.updateCallStatus('ongoing');
        }
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
        toggleMute: toggleAudio,
        handleGroupIncomingCall,
        handleGroupVoiceCallAnswered,
        handleGroupParticipantLeft,
        handleGroupParticipantRecalled,
        handleGroupCallEnded,
        handleGroupCallCancelled,
    };
}