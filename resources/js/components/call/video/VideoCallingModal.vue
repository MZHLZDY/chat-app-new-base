<script setup lang="ts">
import { computed, watch } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useVideoCall } from '@/composables/useVideoCall';
import { usePersonalCall } from '@/composables/usePersonalCall';
import CallAvatar from '../shared/CallAvatar.vue';
import { X, Video } from 'lucide-vue-next';

const store = useCallStore();
const { cancelCall } = usePersonalCall();

const currentCall = computed(() => store.currentCall);
const backendCall = computed(() => store.backendCall);
const recipient = computed(() => currentCall.value?.receiver);

// Status berdering / 'ringing' di pov caller ketika kita menelpon seseorang
const isCallingVideo = computed(() => 
    currentCall.value?.status === 'ringing' && currentCall.value?.type === 'video'
);

const handleCancel = async () => {
    if (backendCall.value) {
        try {
            await cancelCall(backendCall.value.id); // Panggil API untuk membatalkan panggilan
        } catch (error) {
            console.error('Gagal untuk membatalkan panggilan:', error);
        }
    }
};

// watch status changes dari backend (rejected, cancelled, missed)
watch(() => store.callStatus, (newStatus) => {
    if (newStatus === 'rejected') {
        // Penerima menolak panggilan
        setTimeout(() => {
            store.clearCurrentCall();
        }, 2000); // Muncul pesan "Panggilan ditolak" selama 2 detik lalu hilang
    } else if (newStatus === 'cancelled') {
        // Penelpon membatalkan panggilan
        store.clearCurrentCall();
    } else if (newStatus === 'missed') {
        // Tidak ada jawaban (timeout 30dtk)
        setTimeout(() => {
            store.clearCurrentCall();
        }, 2000)
    }
});
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

                <!-- Status panggilan -->
                <p class="call-status">
                    {{ 
                        store.callStatus === 'rejected' ? 'Panggilan Ditolak' :
                        store.callStatus === 'missed' ? 'Tidak ada jawaban' :
                        'Memanggil...'
                    }}
                </p>

                <div class="call-type-badge mb-5">
                    <Video :size="18"/>
                    <span>Panggilan video</span>
                </div>

                <!-- Tombol cancel -->
                <div v-if="store.callStatus === 'ringing'" class="d-flex justify-content-center mt-5">
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

/* Style untuk status panggilan */
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