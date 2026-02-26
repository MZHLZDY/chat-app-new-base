<script setup lang="ts">
import { computed, ref } from 'vue';
import { useAgora } from '@/composables/useAgora';
import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from '@/stores/authStore';
import VideoPlayer from './VideoPlayer.vue';
import { Maximize2, PhoneOff, Mic, MicOff, Video, VideoOff } from 'lucide-vue-next';

const emit = defineEmits(['maximize', 'end-call']);
const store = useCallStore();
const authStore = useAuthStore();
const { 
    remoteUsers, 
    localVideoTrack, 
    localAudioTrack,
    toggleAudio,
    toggleVideo,
    isAudioEnabled,
    isVideoEnabled
} = useAgora();

const currentUser = computed(() => authStore.user);
const currentCall = computed(() => store.currentCall);

// Remote User Logic
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

// Deteksi remote mute
const isRemoteMuted = computed(() => {
    if (!remoteUser.value) return false;
    return !remoteUser.value.audioTrack;
});

// Deteksi remote off cam
const isRemoteOffCam = computed(() => {
    if (!remoteUser.value) return true;
    return !remoteUser.value.videoTrack;
});

const localOrientation = ref<'landscape' | 'portrait'>('landscape');
const remoteOrientation = ref<'landscape' | 'portrait'>('landscape');

// ========== DRAGGABLE LOGIC (TIDAK BERUBAH) ==========
const position = ref({ x: window.innerWidth - 360, y: 100 });
const isDragging = ref(false);
const dragOffset = ref({ x: 0, y: 0 });
const floatingRef = ref<HTMLElement | null>(null);

const startDrag = (event: MouseEvent | TouchEvent) => {
    const target = event.target as HTMLElement;
    if (target.closest('button')) return;

    if (event.cancelable && !('touches' in event)) event.preventDefault();
    if (!floatingRef.value) return;

    let clientX: number, clientY: number;

    if ('touches' in event) {
        const touch = (event as TouchEvent).touches[0];
        if (!touch) return;
        clientX = touch.clientX;
        clientY = touch.clientY;
        window.addEventListener('touchmove', onDrag, { passive: false });
        window.addEventListener('touchend', stopDrag);
    } else {
        clientX = (event as MouseEvent).clientX;
        clientY = (event as MouseEvent).clientY;
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

    let clientX: number, clientY: number;

    if ('touches' in event) {
        const touch = (event as TouchEvent).touches[0];
        if (!touch) return;
        clientX = touch.clientX;
        clientY = touch.clientY;
    } else {
        clientX = (event as MouseEvent).clientX;
        clientY = (event as MouseEvent).clientY;
    }

    position.value = {
        x: clientX - dragOffset.value.x,
        y: clientY - dragOffset.value.y
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
        class="floating-frame"
        :class="remoteOrientation"
        :style="{ top: position.y + 'px', left: position.x + 'px' }"
        @mousedown="startDrag"
        @touchstart="startDrag"
    >
        <!-- Baris 1: Header (Island Nama) -->
        <div class="frame-header">
            <div class="island-pill">
                <span class="name">{{ remoteUser?.name || 'User' }}</span>
                <div class="status-icons">
                    <Transition name="slide-icon">
                        <div v-if="isRemoteMuted" class="icon-item">
                            <MicOff :size="11" class="text-red-400" />
                        </div>
                    </Transition>
                    <Transition name="slide-icon">
                        <div v-if="isRemoteOffCam" class="icon-item">
                            <VideoOff :size="11" class="text-red-400" />
                        </div>
                    </Transition>
                </div>
            </div>
        </div>

        <!-- Baris 2: Video Area -->
        <div class="video-area">
            <!-- Remote Video -->
            <div class="remote-layer">
                <VideoPlayer 
                    v-if="remoteUser && remoteUser.videoTrack"
                    :key="`remote-${remoteUser.uid}-${isRemoteOffCam}`"
                    :video-track="remoteUser.videoTrack"
                    :audio-track="remoteUser.audioTrack"
                    :uid="Number(remoteUser.uid)"
                    :user-name="remoteUser.name || 'User'"
                    :is-local="false"
                    :hide-name-label="true"
                    @orientation-detected="(o) => remoteOrientation = o"
                />
                <div v-else class="fallback">
                    <span>{{ isRemoteOffCam ? 'Camera Off' : '...' }}</span>
                </div>
            </div>

            <!-- Local PIP (di dalam video area) -->
            <div class="local-pip" :class="localOrientation">
                <VideoPlayer 
                    :video-track="localVideoTrack" 
                    :uid="currentUser?.id || 0"
                    user-name="Me"
                    :is-local="true"
                    :hide-name-label="true"
                    @orientation-detected="(o) => localOrientation = o"
                />
            </div>
        </div>

        <!-- Baris 3: Footer (Dock Buttons) -->
        <div class="frame-footer">
            <button class="dock-btn" :class="{ off: !isAudioEnabled }" @click.stop="toggleAudio">
                <MicOff v-if="!isAudioEnabled" :size="14" />
                <Mic v-else :size="14" />
            </button>
            <button class="dock-btn end" @click.stop="emit('end-call')">
                <PhoneOff :size="14" />
            </button>
            <button class="dock-btn" :class="{ off: !isVideoEnabled }" @click.stop="toggleVideo">
                <VideoOff v-if="!isVideoEnabled" :size="14" />
                <Video v-else :size="14" />
            </button>
            <button class="dock-btn" @click.stop="emit('maximize')">
                <Maximize2 :size="14" />
            </button>
        </div>
    </div>
</template>

<style scoped>
/* ========== FRAME UTAMA (Grid 3 Baris) ========== */
.floating-frame {
    position: fixed;
    z-index: 9999;
    cursor: grab;

    /* Glassmorphism */
    background: rgba(25, 25, 35, 0.75);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);

    /* GRID 3 baris: header / video / footer */
    display: grid;
    grid-template-rows: auto 1fr auto;

    transition: width 0.3s ease;
}
.floating-frame:active { cursor: grabbing; }

/* Responsive Size (ikut orientasi remote) */
.floating-frame.landscape { width: 320px; }
.floating-frame.portrait { width: 200px; }


/* ========== BARIS 1: HEADER (Island) ========== */
.frame-header {
    padding: 8px 12px 4px;
    display: flex;
    align-items: center;
}

.island-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.name {
    font-size: 11px;
    font-weight: 700;
    color: #ddd;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100px;
}

.status-icons {
    display: flex;
    align-items: center;
    gap: 3px;
}

.icon-item {
    display: flex;
    align-items: center;
}


/* ========== BARIS 2: VIDEO AREA ========== */
.video-area {
    position: relative;
    margin: 4px 10px;
    border-radius: 12px;
    overflow: hidden;
    background: #000;
}

/* Aspect ratio ikut orientasi remote */
.floating-frame.landscape .video-area { aspect-ratio: 16 / 9; }
.floating-frame.portrait .video-area { aspect-ratio: 9 / 16; }

.remote-layer { width: 100%; height: 100%; }
.remote-layer :deep(video) {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
}

.fallback {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,0.4);
    font-size: 11px;
}

/* Local PIP (absolute di dalam video-area) */
.local-pip {
    position: absolute;
    top: 8px;
    right: 8px;
    border-radius: 8px;
    overflow: hidden;
    border: 1.5px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
    background: #1a1a1a;
    z-index: 10;
}
.local-pip :deep(video) {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
}

.local-pip.landscape { width: 70px; aspect-ratio: 16 / 9; }
.local-pip.portrait { width: 45px; aspect-ratio: 9 / 16; }


/* ========== BARIS 3: FOOTER (Dock) ========== */
.frame-footer {
    padding: 4px 12px 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.dock-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.12);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.dock-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: scale(1.08);
}
.dock-btn.off {
    background: rgba(255, 255, 255, 0.9);
    color: #000;
}
.dock-btn.end {
    background: #ff3b30;
    color: white;
}


/* ========== ANIMASI SLIDE ICON ========== */
.slide-icon-enter-active,
.slide-icon-leave-active {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    max-width: 20px;
    opacity: 1;
    overflow: hidden;
}
.slide-icon-enter-from,
.slide-icon-leave-to {
    max-width: 0;
    opacity: 0;
}


/* ========== MOBILE ========== */
@media (max-width: 768px) {
    .floating-frame.landscape { width: 240px; }
    .floating-frame.portrait { width: 160px; }

    .local-pip.landscape { width: 55px; }
    .local-pip.portrait { width: 35px; }

    .dock-btn { width: 24px; height: 24px; }
    .frame-footer { gap: 8px; }
}
</style>