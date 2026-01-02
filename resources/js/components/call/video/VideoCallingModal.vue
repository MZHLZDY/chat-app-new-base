<script setup lang="ts">
import { computed } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useVideoCall } from '@/composables/useVideoCall';
import CallAvatar from '../shared/CallAvatar.vue';
import { X, Video } from 'lucide-vue-next';

const store = useCallStore();
const { endCall } = useVideoCall(); // cuman butuh fungsi end call buat channel

const currentCall = computed(() => store.currentCall);
const recipient = computed(() => currentCall.value?.receiver);

// Status berdering / 'ringing' di pov caller ketika kita menelpon seseorang
const isCallingVideo = computed(() => 
    currentCall.value?.status === 'ringing' && currentCall.value?.type === 'video'
);

const handleCancel = () => {
    if (currentCall.value) {
        endCall(currentCall.value.id);
    }
};
</script>

<template>
    <Transition name="fade">
        <div v-if="isCallingVideo" class="incoming-call-overlay">
            <div class="incoming-card glass-effect">

                <!-- Avatar penerima telepon / callee -->
                <div class="mb-5 d-flex justify-content-center">
                    <CallAvatar
                        :photo-url="recipient?.avatar || '/media/avatars/blank.png'"
                        :display-name="recipient?.name || 'User'"
                        size="120px"
                        :is-calling="true"
                    />
                </div>

                <!-- Info penerima -->
                <h3 class="caller-name">{{ recipient?.name }}...</h3>

                <div class="call-type-badge mb-5">
                    <Video :size="18"/>
                    <span>Panggilan video</span>
                </div>

                <!-- Tombol cancel -->
                <div class="d-flex justify-content-center mt-5">
                    <button @click="handleCancel" class="btn-action btn-reject">
                        <X :size="32"/>
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
    background-color: rgba(0, 0, 0, 0.7); /* Warna agak gelap biar fokus */
    z-index: 9999;
    backdrop-filter: blur(5px);
}

.incoming-card {
    padding: 40px;
    border-radius: 24px;
    text-align: center;
    width: 90%;
    max-width: 350px;

    /* Efek glassmorphism premium */
    background: rgba(30, 30, 40, 0.8);
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
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.btn-action:hover {
    transform: scale(1.1);
}

.btn-reject {
    background: linear-gradient(135deg, #ff4d4d, #f70000);
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