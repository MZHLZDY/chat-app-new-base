<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useAgora } from '@/composables/useAgora';
import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from '@/stores/authStore';
import VideoPlayer from './VideoPlayer.vue';
import { Maximize2, PhoneOff, MicOff, VideoOff } from 'lucide-vue-next';

const emit = defineEmits(['maximize', 'end-call']);
const store = useCallStore();
const authStore = useAuthStore();
const { remoteUsers } = useAgora();

const currentUser = computed(() => authStore.user);
const currentCall = computed(() => store.currentCall);

// Helper to identify the remote user (video source)
const remoteUser = computed(() => {
    if (remoteUsers.value.length > 0) {
        const user = remoteUsers.value[0];
        if (!user || !user.uid) return null;
        return {
            uid: user.uid,
            videoTrack: user.videoTrack,
            audioTrack: user.audioTrack,
            name: currentCall.value?.caller.id === currentUser.value?.id
                ? currentCall.value?.receiver.name
                : currentCall.value?.caller.name
        };
    }
    return null;
});

const isRemoteMuted = computed(() => remoteUser.value && !remoteUser.value.audioTrack);
const isRemoteVideoOff = computed(() => remoteUser.value && !remoteUser.value.videoTrack);

// Draggable Logic
const position = ref({ x: window.innerWidth - 180, y: 100 });
const isDragging = ref(false);
const dragOffset = ref({ x: 0, y: 0 });
const floatingRef = ref<HTMLElement | null>(null);

// Helper to perform Type Guard and check length
const isTouchEvent = (e: MouseEvent | TouchEvent): e is TouchEvent => {
    return 'touches' in e && e.touches.length > 0;
};

const startDrag = (event: MouseEvent | TouchEvent) => {
    const target = event.target as HTMLElement;
    if (target.closest('button')) return; // Jangan drag kalau user klik tombol

    if (event.cancelable) event.preventDefault();

    if (!floatingRef.value) return;

    let clientX: number;
    let clientY: number;

    // Safe check: Is it a touch event AND does it have touches?
    if (isTouchEvent(event) && event.touches[0]) {
        clientX = event.touches[0].clientX;
        clientY = event.touches[0].clientY;

        window.addEventListener('touchmove', onDrag, { passive: false });
        window.addEventListener('touchend', stopDrag);
    } else {
        // Fallback to Mouse
        const mouseEvent = event as MouseEvent;
        clientX = mouseEvent.clientX;
        clientY = mouseEvent.clientY;

        window.addEventListener('mousemove', onDrag);
        window.addEventListener('mouseup', stopDrag);
    }

    isDragging.value = true;
    dragOffset.value = { 
        x: clientX - position.value.x, 
        y: clientY - position.value.y 
    };
};

const onDrag = (event: MouseEvent | TouchEvent) => {
    if (!isDragging.value) return;
    if (event.cancelable) event.preventDefault();

    let clientX: number;
    let clientY: number;

    if (isTouchEvent(event) && event.touches[0]) {
        clientX = event.touches[0].clientX;
        clientY = event.touches[0].clientY;
    } else {
        const mouseEvent = event as MouseEvent;
        clientX = mouseEvent.clientX;
        clientY = mouseEvent.clientY;
    }

    const maxX = window.innerWidth - (floatingRef.value?.offsetWidth || 150);
    const maxY = window.innerHeight - (floatingRef.value?.offsetHeight || 200);

    position.value = {
        x: Math.min(Math.max(0, clientX - dragOffset.value.x), maxX),
        y: Math.min(Math.max(0, clientY - dragOffset.value.y), maxY)
    };
};

const stopDrag = () => {
    isDragging.value = false;
    window.removeEventListener('mousemove', onDrag);
    window.removeEventListener('mouseup', stopDrag);
    window.removeEventListener('touchmove', onDrag);
    window.removeEventListener('touchend', stopDrag);
};
</script>

<template>
    <div
        ref="floatingRef"
        class="video-floating-container"
        :style="{ top: position.y + 'px', left: position.x + 'px' }"
        @mousedown="startDrag"
        @touchstart="startDrag"
    >
        <div class="video-content">
            <VideoPlayer
                v-if="remoteUser"
                :video-track="remoteUser.videoTrack"
                :audio-track="remoteUser.audioTrack"
                :uid="Number(remoteUser.uid)"
                :user-name="remoteUser.name || 'User'"
                :is-local="false"
                :hide-name-label="true"
            />
            <div v-else class="waiting-state">
                <span class="loading-pulse"></span>
            </div>
            
            <div class="info-overlay">
                <div class="user-name text-truncate">{{ remoteUser?.name || 'Connecting...' }}</div>
                <div class="status-icon-group">
                    <MicOff v-if="isRemoteMuted" :size="14" class="text-danger" />
                    <VideoOff v-if="isRemoteVideoOff" :size="14" class="text-danger" />
                </div>
            </div>
        </div>

        <div class="controls-overlay">
            <button class="btn-control maximize" @click.stop="emit('maximize')">
                <Maximize2 :size="20" />
            </button>
            <button class="btn-control end" @click.stop="emit('end-call')">
                <PhoneOff :size="20" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.video-floating-container {
    position: fixed; width: 140px; aspect-ratio: 9/16;
    background: #000; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    z-index: 10000; overflow: hidden; cursor: grab;
    border: 1px solid rgba(255, 255, 255, 0.15);
}
.video-floating-container:active { cursor: grabbing; }
.video-content :deep(video) { width: 100% !important; height: 100% !important; object-fit: cover !important; }
.controls-overlay {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;
    gap: 12px; opacity: 0; transition: opacity 0.2s; backdrop-filter: blur(2px);
}
.video-floating-container:hover .controls-overlay, .video-floating-container:active .controls-overlay { opacity: 1; }
.btn-control {
    width: 40px; height: 40px; border-radius: 50%; border: none;
    display: flex; align-items: center; justify-content: center; cursor: pointer; color: white;
}
.maximize { background: rgba(255,255,255,0.25); }
.end { background: #ff4444; }
@media (min-width: 768px) {
    .video-floating-container { width: 240px; aspect-ratio: 16/9; }
}
</style>