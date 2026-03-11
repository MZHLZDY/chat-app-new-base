import { ref } from 'vue';
import { database } from '@/libs/firebase';
import { ref as dbRef, set, remove } from 'firebase/database';
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
            // [FIX] Menggunakan "participants" sesuai validasi AgoraController.php
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
            callStore.setCurrentCall({
                id: data.call.id,
                isGroup: true,
                status: 'calling',
                channelName: data.channel_name,
                caller: authStore.user as User,
                receiver: authStore.user as User, // TS Bypass fallback
            } as any); 
            
            callStore.updateCallStatus('calling');
            callStore.setInCall(true);

            // Join Agora (Urutan parameter sudah disesuaikan)
            await joinChannel(data.channel_name, data.token, authStore.user?.id as number);
            participantIds.forEach((participantId) => {
            if (participantId !== authStore.user?.id) {
                // 1. Path harus sama dengan listener (calls/{id}/incoming)
                const incomingRef = dbRef(database, `calls/${participantId}/incoming`);
                
                set(incomingRef, {
                    call_id: data.call.id,
                    call_type: 'group_voice', // 2. HARUS SAMA dengan if condition di listener
                    channel_name: data.channel_name,
                    caller: authStore.user,   // 3. Listener mu butuh 'caller'
                    group: data.call.group || { id: groupId, name: callStore.activeGroupName || 'Group Call', photo: callStore.activeGroupAvatar || '' },
                    participants: data.call.participants || participantIds,
                    token: data.token || '', 
                    timestamp: Date.now()
                });
            }
        });
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

            // 1. [WAJIB] Hit API untuk konfirmasi join ke backend sekaligus mengambil TOKEN BARU
            // Pastikan endpoint ini mengembalikan token untuk user yang sedang login (peserta)
            const response = await axios.post('/group-call/answer', { call_id: callId });
            
            // Ambil token baru dari response backend. Jika backend menggunakan key lain (misal agora_token), sesuaikan ya!
            const newToken = response.data.token || response.data.agora_token;
            const channel = incoming.channel;

            if (!newToken) {
                toast.error("Gagal mendapatkan token dari server");
                return;
            }

            // 2. Set State agar Modal Ongoing Muncul
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
            
            callStore.updateCallStatus('ongoing');
            callStore.setInCall(true);

            // Bersihkan modal incoming
            callStore.clearIncomingCall();

            // 3. Join Agora menggunakan TOKEN BARU milik peserta
            await joinChannel(channel, newToken, authStore.user?.id as number);

            // 4. [FIREBASE] Beri tahu Host dan Peserta lain bahwa kamu sudah join
            const participantRef = dbRef(database, `group_calls/${callId}/participants/${authStore.user?.id}`);
            set(participantRef, { status: 'joined', timestamp: Date.now() });

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

    const handleGroupVoiceCallAnswered = (payload: any) => {
        console.log('📡 [Firebase] Participant Joined:', payload);
        
        // 1. LANGSUNG PAKSA UPDATE STATE TANPA SYARAT IF
        callStore.updateCallStatus('ongoing');
        callStore.setInCall(true); // Pastikan aplikasi tahu kita sedang di dalam panggilan
        callStore.isGroupCall = true; // Berjaga-jaga memastikan ini mode grup
        callStore.isMinimized = false; // Paksa modal utama terbuka (bukan floating)

        // 2. Update status peserta di UI Host menjadi 'joined' (Hijau)
        // Pastikan ID user didapatkan dengan benar dari payload
        const userId = payload.user_id || payload.id;
        if (userId) {
            callStore.updateGroupParticipantStatus(userId, 'joined');
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