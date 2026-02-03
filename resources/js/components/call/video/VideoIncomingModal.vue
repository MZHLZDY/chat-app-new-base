<script setup lang="ts">
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useVideoCall } from '@/composables/useVideoCall';
import { usePersonalCall } from '@/composables/usePersonalCall';
import { useAuthStore } from '@/stores/authStore';
import { useAgora } from '@/composables/useAgora';
import type { CallType } from '@/types/call';
import CallAvatar from '../shared/CallAvatar.vue';
import { Video, X, Check } from 'lucide-vue-next';

const store = useCallStore();
const authStore = useAuthStore();
const { answerCall, rejectCall } = usePersonalCall();
const { joinChannel } = useAgora();

const incomingCall = computed(() => store.incomingCall);
const backendCall = computed(() => store.backendCall);
const caller = computed(() => incomingCall.value?.caller);

// State untuk countdown timer
const remainingSeconds = ref(30);
let countdownInterval: number | null = null;

// State untuk warna pulse berdassarkan countdown
const pulseColor = computed(() => {
    if (remainingSeconds.value >= 16) {
        return 'rgba(177, 173, 173, 0.4)'; // Warna abu" / putih standar (30 - 16dtk)
    } else if (remainingSeconds.value >= 6) {
        return 'rgba(255, 165, 0, 0.5)'; // Warna oranye (15 - 6dtk)
    } else {
        return 'rgba(255, 77, 77, 0.6)'; // Warna merah (5 - 0dtk)
    }
})

// Filter penting: cuman muncul kalau tipe call nya 'video'
const isVideoCallIncoming = computed(() => 
    incomingCall.value !== null && incomingCall.value.type === 'video'
);

const handleAccept = async () => {
    if (backendCall.value) {
        try {
            console.log('✅ VideoIncomingModal: handleAccept dipanggil');
            console.log('📦 Backend Call ID:', backendCall.value.id);
            console.log('🔍 Sebelum answerCall - store.agoraToken:', store.agoraToken?.substring(0, 50));

            // Hit API /call/answer
            const response = await answerCall(backendCall.value.id);
            console.log('✅ API answerCall berhasil dipanggil');
            console.log('✅ Setelah answerCall - Ful Response:', response);
            console.log('🔍 response.agora_token:', response.agora_token?.substring(0, 50));
            console.log('🔍 store.agoraToken setelah answerCall:', store.agoraToken?.substring(0, 50));

            console.log('✅ API answerCall berhasil dipanggil');
            console.log('📦 Response:', response);

            const calleeToken = response.agora_token || store.agoraToken;
            console.log('🔑 Token yang akan dipakai:', calleeToken);
            console.log('🔍 Menggunakan token dari:', response.agora_token ? 'RESPONSE' : 'STORE');

            // Join Channel Agora
            if (store.agoraToken && store.channelName && authStore.user?.id) {
                console.log('🎤 Bergabung ke Channel Agora...');
                console.log('📦 Channel:', store.channelName);
                console.log('📦 Token:', calleeToken.substring(0, 50));
                console.log('📦 User ID:', authStore.user.id);

                await joinChannel(
                    store.channelName,
                    calleeToken,
                    Number(authStore.user.id)
                );

                store.setHasJoinedAgora(true);
                console.log('✅ hasJoinedAgora diset ke true');

                console.log('✅ Berhasil bergabung ke Channel Agora');
                console.log('📦 store.hasJoinedAgora:', store.hasJoinedAgora);
            } else {
                console.error('❌ Gagal saat bergabung ke Channel Agora: Data tidak lengkap');
                console.error('📦 agoraToken:', store.agoraToken);
                console.error('📦 channelName:', store.channelName);
                console.error('📦 user.id:', authStore.user?.id);
                return;
            }

            // Set curretnCall (Trigger VideoCallModal)
            if (store.backendCall && store.agoraToken && store.channelName) {
                console.log('📦 Setting currentCall untuk callee...');

                // Ambil caller dari response atau dari incomingCall
                const callerData = response?.caller || store.backendCall.caller || store.incomingCall?.caller;
                const calleeData = authStore.user;

                console.log('📦 Caller Data:', callerData);
                console.log('📦 Callee Data:', calleeData);

                if (callerData && calleeData) {
                    store.setCurrentCall({
                        id: store.backendCall.id,
                        type: 'video' as CallType,
                        caller: {
                            id: callerData.id,
                            name: callerData.name,
                            email: callerData.email || '',
                            avatar: callerData.avatar || callerData.profile_photo_url || '',
                        },
                        receiver: {
                            id: calleeData.id,
                            name: calleeData.name,
                            email: calleeData.email,
                            avatar: calleeData.avatar || calleeData.profile_photo_url || '',
                        },
                        status: 'ongoing',
                        token: store.agoraToken,
                        channel: store.channelName,
                    });

                    console.log('✅ currentCall berhasil diset');
                    console.log('📦 store.currentCall:', store.currentCall);
                } else {
                    console.error('❌ Data Caller atau Callee tidak ditemukan');
                    console.error('📦 callerData:', callerData);
                    console.error('📦 calleeData:', calleeData);
                }
            } else {
                console.error('❌ Store data tidak lengkap');
                console.error('📦 backendCall:', store.backendCall);
                console.error('📦 agoraToken:', store.agoraToken);
                console.error('📦 channelName:', store.channelName);
            }

            // Update status
            store.updateCallStatus('ongoing');
            store.setInCall(true);

            console.log('✅ State setelah dipanggil:');
            console.log('📦 callStatus:', store.callStatus);
            console.log('📦 isInCall:', store.isInCall);

        } catch (error: any) {
            console.error('Gagal untuk menjawab panggilan:', error);
            console.error('📦 Error code:', error);
            console.error('📦 Error message:', error.message);
            store.clearIncomingCall();
        }
    }
};

const handleReject = async () => {
    if (backendCall.value) {
        try {
            await rejectCall(backendCall.value.id); // Panggil API untuk menolak panggilan
        } catch (error) {
            console.error('Gagal untuk menolak panggilan:', error);
        }
    }
};

watch(() => store.callStatus, (newStatus) => {
    // Bersihkan countdown jika panggilan sudah diterima
    if (newStatus === 'ongoing' || newStatus === 'rejected' || newStatus === 'missed') {
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }
    }
    
    if (newStatus === 'cancelled') {
        // Penelpon membatalkan panggilan sebelum kita (callee / penerima) jawab
        setTimeout(() => {
            store.clearIncomingCall();
            store.clearCurrentCall();
        }, 2000);
    }
});

// Start countdown timer saat komponen mounted
onMounted(() => {
    countdownInterval = window.setInterval(() => {
        if (remainingSeconds.value > 0) {
            remainingSeconds.value--;
        } else {
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
        }
    }, 1000);
});

// Cleanup countdown saat unmount
onUnmounted(() => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
});
</script>

<template>
    <Transition name="fade">
        <!-- Overlay muncul kalau panggilan masuk -->
        <div v-if="isVideoCallIncoming" class="incoming-call-overlay">
            <div class="incoming-card glass-effect">

                <!-- Efek animasi pulse di avatar -->
                <div class="mb-4 d-flex justify-content-center">
                    <CallAvatar
                        :photo-url="caller?.avatar || '/media/avatars/blank.png'"
                        :display-name="caller?.name || 'Unknown'"
                        size="100px"
                        :is-calling="true"
                        :pulse-color="pulseColor"
                    />
                </div>

                <!-- Info caller -->
                <h3 class="caller-name">{{ caller?.name }}</h3>

                <!-- Status message -->
                <p class="call-status">
                    {{  
                        store.callStatus === 'cancelled' ? 'panggilan dibatalkan' :
                        'Panggilan video masuk....'
                    }}
                </p>

                <div class="call-type-badge">
                    <Video :size="18" />
                    <span>Panggilan video masuk</span>
                </div>

                <!-- Tombol aksi -->
                <div v-if="store.callStatus !== 'cancelled'" class="d-flex gap-4 justify-content-center mt-5">
                    <!-- Tombol tolak panggilan (warna merah) -->
                    <button @click="handleReject" class="btn-action btn-reject">
                        <X :size="28"/>
                    </button>

                    <!-- Tombol terima panggilan (warna hijau) -->
                    <button @click="handleAccept" class="btn-action btn-accept">
                        <Check :size="28"/>
                    </button>
                </div>     
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.incoming-call-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: rgba(0, 0, 0, 0.75); /* Warna agak gelap biar fokus */
    z-index: 9999;
    backdrop-filter: blur(8px);
}

.incoming-card {
    padding: 40px;
    border-radius: 24px;
    text-align: center;
    width: 90%;
    max-width: 380px;

    /* Efek glassmorphism premium */
    background: rgba(30, 30, 40, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
}

.caller-name {
    color: white;
    font-weight: 700;
    font-size: 1.5rem;
    margin-bottom: 8px;
}

/* style untuk status message */
.call-status {
    color: #a1a5b7;
    font-size: 0.95rem;
    margin-bottom: 16px;
}

.call-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.1);
    padding: 6px 16px;
    border-radius: 20px;
    color: #a1a5b7;
    font-size: 0.9rem;
}

.btn-action {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.btn-action:hover {
    transform: scale(1.1) translateY(-2px);
}

.btn-action:active {
    transform: scale(0.95);
}

.btn-reject {
    background: linear-gradient(135deg, #ff4d4d, #f70000);
}

.btn-accept {
    background: linear-gradient(135deg, #00b341, #008a32);
}

/* Animasi fade in / out */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>