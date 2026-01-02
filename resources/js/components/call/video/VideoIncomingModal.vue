<script setup lang="ts">
import { computed } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useVideoCall } from '@/composables/useVideoCall';
import CallAvatar from '../shared/CallAvatar.vue';
import { Video, X, Check } from 'lucide-vue-next';

const store = useCallStore();
const { acceptCall, rejectCall } = useVideoCall();

const incomingCall = computed(() => store.incomingCall);
const caller = computed(() => incomingCall.value?.caller);

// Filter penting: cuman muncul kalau tipe call nya 'video'
const isVideoCallIncoming = computed(() => 
    incomingCall.value !== null && incomingCall.value.type === 'video'
);

const handleAccept = () => {
    if (incomingCall.value) {
        acceptCall(incomingCall.value.id);
    }
};

const handleReject = () => {
    if (incomingCall.value) {
        rejectCall(incomingCall.value.id);
    }
};
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
                    />
                </div>

                <!-- Info caller -->
                <h3 class="caller-name">{{ caller?.name }}</h3>

                <div class="call-type-badge">
                    <Video :size="18" />
                    <span>Panggilan video masuk</span>
                </div>

                <!-- Tombol aksi -->
                <div class="d-flex gap-4 justify-content-center mt-5">
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