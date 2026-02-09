<script setup lang="ts">
import { onMounted, onUnmounted, nextTick, watch } from 'vue';

// Definisikan props (apa yang diterima di komponen ini)
const props = defineProps<{
    videoTrack?: any; // Track kamera (bisa undefined klo off cam)
    audioTrack?: any; // Track suara
    uid: string | number; // ID user
    userName: string // Nama user buat tabel
    isLocal?: boolean // mirror effect klo video itu kita sendiri
    hideNameLabel?: boolean // Sembunyiin label nama user
}>();

// Definisikan emit
const emit = defineEmits<{
    orientationDetected: [orientation: 'landscape' | 'portrait']
}>();

const playerId = `player-${props.uid}`;

// Fungsi untuk play video
const playVideo = () => {
    if (props.videoTrack) {
        nextTick(() => {
            props.videoTrack.play(playerId);

            // Detect orientation dari video element (khusus local video)
            if (props.isLocal) {
                setTimeout(() => {
                    const container = document.getElementById(playerId);
                    const videoElement = container?.querySelector('video') as HTMLVideoElement;

                    if (videoElement) {
                        const checkDimensions = () => {
                            const width = videoElement.videoWidth;
                            const height = videoElement.videoHeight;

                            if (width > 0 && height > 0) {
                                const orientation = width > height ? 'landscape' : 'portrait';
                                emit('orientationDetected', orientation);
                                console.log(`📐 Video dimensions: ${width}x${height} (${orientation})`);
                            }
                        };

                        // Check immediately jika sudah loaded
                        if (videoElement.videoWidth > 0) {
                            checkDimensions();
                        } else {
                            // Atau tunggu metadata loaded
                            videoElement.addEventListener('loadedmetadata', checkDimensions);
                        }
                    }
                }, 500); // Delay untuk ensure DOM ready
            }
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
});

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

        <!-- Kotak video -->
        <!-- ID dinamis sesuai UID user -->
        <div
            :id="playerId"
            class="video-feed"
            :class="{ 'mirror-mode': isLocal }"
        ></div>

        <!-- Label nama user -->
        <div v-if="!isLocal && !hideNameLabel" class="user-label">
            <span class="fw-bold text-white">{{ userName }}</span>
        </div>

    </div>
</template>

<style scoped>
.video-player-container {
    position: relative;
    width: 100%;
    height: 100%;
    border-radius: 12px;
    overflow: hidden;
    background-color: #2c2c2c; /* Warna abu gelap kalau video loading / off */
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.video-feed {
    width: 100%;
    height: 100%;
    object-fit: cover; /* Biar video full kotak */
}

/* Efek mirror untuk video sendiri */
.mirror-mode {
    transform: scaleX(-1);
}

.user-label {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: rgba(0, 0, 0, 0.5); /* Hitam transparan */
    padding: 4px 12px;
    border-radius: 20px;
    z-index: 10;
    backdrop-filter: blur(4px);
}
</style>