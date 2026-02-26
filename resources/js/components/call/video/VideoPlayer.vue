<script setup lang="ts">
import { onMounted, onUnmounted, nextTick, watch, ref } from 'vue';

// Definisikan props (apa yang diterima di komponen ini)
const props = defineProps<{
    videoTrack?: any; // Track kamera (bisa undefined klo off cam)
    audioTrack?: any; // Track suara
    uid: string | number; // ID user
    userName: string // Nama user buat tabel
    avatarUrl?: string // URL avatar user
    isLocal?: boolean // mirror effect klo video itu kita sendiri
    hideNameLabel?: boolean // Sembunyiin label nama user
}>();

// Definisikan emit
const emit = defineEmits<{
    orientationDetected: [orientation: 'landscape' | 'portrait']
}>();

const videoContainer = ref<HTMLElement | null>(null);

// Fungsi untuk play video
const playVideo = () => {
    if (props.videoTrack && videoContainer.value) {
        nextTick(() => {
            props.videoTrack.play(videoContainer.value);

            setTimeout(() => {
                const videoElement = videoContainer.value?.querySelector('video') as HTMLVideoElement;

                if (videoElement) {
                    const checkDimension = () => {
                        const width = videoElement.videoWidth;
                        const height = videoElement.videoHeight;

                        if (width > 0 && height > 0) {
                            const orientation = width > height ? 'landscape' : 'portrait';
                            emit('orientationDetected', orientation);
                        } else {
                            requestAnimationFrame(checkDimension);
                        }
                    };
                    checkDimension();
                }
            }, 500);
        });
    }
};

// Fungsi play audio (khusus untuk remote user, local audio ga perlu di play nanti bakal ke feedback)
const playAudio = () => {
    if (props.audioTrack && !props.isLocal) {
        props.audioTrack.play();
    }
};

// komponen muncul pertama kali
onMounted(() => {
    playVideo();
    playAudio();
});

// Pantau klo videoTrack berubah (misal user off / on cam)
watch(() => props.videoTrack, (newTrack) => {
    if (newTrack) {
        playVideo();
    }
}, { immediate: true });

// Stop video waktu komponen ditutup
onUnmounted(() => {
    if (props.videoTrack) {
        props.videoTrack.stop(); // Stop playback di elemen ini aja, track tetep idup
    }
    if (props.audioTrack && !props.isLocal) {
        props.audioTrack.stop();
    }
});
</script>

<template>
    <div class="video-player-container">
         <div
            ref="videoContainer"
            class="video-feed"
            :class="{ 'mirror-mode': isLocal }"
            key="video-state"
            ></div>

        <Transition name="fade-scale">
            <div v-if="!videoTrack" class="avatar-state" key="avatar-state">
                <div class="avatar-circle">
                    <img
                        :src="avatarUrl || 'https://ui-avatars.com/api/?name=' + userName + '&background=random'"
                        alt="User">
                </div>
            </div>
        </Transition>

        <!-- Label nama user -->
        <div v-if="!isLocal && !hideNameLabel" class="user-label">
            <span class="fw-bold text-white">{{ userName }}</span>
        </div>
    </div>
</template>

<style scoped>
.video-player-container {
    width: 100%;
    height: 100%;
    position: relative;
    background: #000;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.video-feed {
    position: absolute;
    width: 100%;
    height: 100%;
    z-index: 1;
    object-fit: contain; /* Biar video full kotak */

    /* Transisi fade in waktu on cam */
    opacity: 1;
    transition: opacity 0.3s ease-in 0.3s;
}

.video-feed.video-hidden {
    opacity: 0;
    /* Transisi fade out waktu off cam */
    transition: opacity 0.3s ease-out 0s;
}

/* Efek mirror untuk video sendiri */
.mirror-mode {
    transform: scaleX(-1);
}

/* State untuk avatar */
.avatar-state {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.avatar-circle img {
    width: 90px;
    height: 90px; /* Ukuran dasar */
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.2);
    box-shadow: 0 0 30px rgba(255,255,255,0.1);
}
.status-text {
    margin-top: 10px;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.8);
    background: rgba(0,0,0,0.4);
    padding: 4px 12px;
    border-radius: 20px;
    backdrop-filter: blur(4px);
}

.user-label {
    position: absolute;
    bottom: 20px;
    left: 20px;
    z-index: 10;
    background: rgba(0, 0, 0, 0.5); /* Hitam transparan */
    padding: 4px 14px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.user-label span {
    font-size: 0.85rem;
    text-shadow: 0 1px 2px rgba(0,0,0,0.5);
}

/* Animasi transisi */
.fade-scale-enter-active {
    transition: opacity 0.3s ease-out;
    /* Delay 0.3dtk biar ga ketimpa */
    transition-delay: 0.3s;
}
.fade-scale-leave-active {
    transition: opacity 0.3s ease-in;
    transition-delay: 0s;
}
.fade-scale-enter-from,
.fade-scale-leave-to {
    opacity: 0;
    }
</style>