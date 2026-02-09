<script setup lang="ts">
import { computed, watch, onMounted, onUnmounted, ref } from 'vue'; 
import { useCallStore } from '@/stores/callStore';
import { useVideoCall } from '@/composables/useVideoCall';
import { usePersonalCall } from '@/composables/usePersonalCall';
import { useAgora } from '@/composables/useAgora';
import { useAuthStore } from '@/stores/authStore';
import VideoPlayer from './VideoPlayer.vue';
import CallControls from '../shared/CallControls.vue';
import { MicOff } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';

const store = useCallStore();
const page = usePage();
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);
const localVideoOrientation = ref<'landscape' | 'portrait'>('landscape');

const { toggleAudio, toggleVideo } = useVideoCall();
const { endCall } = usePersonalCall();

const {
    joinChannel,
    leaveChannel,
    localAudioTrack,
    localVideoTrack,
    remoteUsers, // Array UID remote user
    remoteAudioTracks,
    remoteVideoTracks,
    isAudioEnabled,
    isVideoEnabled,
    isJoined,
} = useAgora();

const currentCall = computed(() => store.currentCall);
const backendCall = computed(() => store.backendCall);

// Video call aktif ketika panggilan dalam status 'ongoing' dan tipe nya video
const isVideoCallActive = computed(() => 
    currentCall.value?.status === 'ongoing' && currentCall.value?.type === 'video'
);

// Ambil remote user
const remoteUser = computed(() => {
    if (remoteUsers.value.length > 0) {
        const user = remoteUsers.value[0];

        if (!user || !user.uid) {
            console.warn('⚠️ Remote user atau UID tidak didefinisikan');
            return null;
        }

        console.log('📦 user:', user);
        console.log('📦 user.uid:', user?.uid);

        const uid = user.uid;
        const uidStr = uid.toString();

        console.log('📦 Checking tracks with key:', uidStr);
        console.log('📦 remoteVideoTracks keys:', Array.from(remoteVideoTracks.value.keys()));
        console.log('📦 remoteAudioTracks keys:', Array.from(remoteAudioTracks.value.keys()));

        return {
            uid,
            videoTrack: user.videoTrack,
            audioTrack: user.audioTrack,
            name: currentCall.value?.caller.id === currentUser.value?.id
                ? currentCall.value?.receiver.name
                : currentCall.value?.caller.name
        };
    }

    console.log('⚠️ remoteUsers array kosong')
    return null;
});

// join agora channel saat modal muncul
onMounted(async () => {
    console.log('📹 VideoCallModal mounted');
    console.log('📦 IsVideoCallActive:', isVideoCallActive.value);
    console.log('📦 isJoined:', isJoined.value);
    console.log('📦 store.hasJoinedAgora:', store.hasJoinedAgora);
    console.log('📦 localVideoTrack:', localVideoTrack.value ? 'AVAILABLE' : 'NULL');
    console.log('📦 currentUser:', currentUser.value);
    console.log('📦 currentUser.id:', currentUser.value?.id);
    console.log('📦 agoraToken:', store.agoraToken ? 'AVAILABLE' : 'NULL');
    console.log('📦 channelName:', store.channelName);

    if (!isVideoCallActive.value) {
        console.warn('⚠️ Video call tidak aktif, skip join channel');
        return;
    }

    if (!store.agoraToken || !store.channelName) {
        console.error('❌ Token atau channel name tidak ada!');
        console.error('📦 agoraToken:', store.agoraToken);
        console.error('📦 channelName:', store.channelName);
        return;
    }

    if (!currentUser.value?.id) {
        console.error('❌ User ID tidak ditemukan!');
        console.error('📦 currentUser:', currentUser.value);
        return;
    }

    // Cek apakah sudah bergabung (unutk callee yang sudah join di videoIncomingModal)
    if (isJoined.value || store.hasJoinedAgora) {
        console.log('✅ Sudah bergabung ke channel Agora via VideoIncomingModal, skip joinChannel');
        console.log('📦 isJoined:', isJoined.value);
        console.log('📦 hasJoinedAgora:', store.hasJoinedAgora);
        console.log('📹 Local video track:', localVideoTrack.value ? 'AVAILABLE' : 'NULL');
        console.log('🎤 Local audio track:', localAudioTrack.value ? 'AVAILABLE' : 'NULL');
        console.log('👥 Remote users:', remoteUser.value);
        return;
    }

    try {
        console.log('🚀 Bergabung ke Agora Channel (Caller)...');
        console.log('📦 Channel:', store.channelName);

        console.log('🔍 Verifikasi source UID');
        console.log('📦 authStore.user.id:', authStore.user?.id);
        console.log('📦 currentUser.value.id:', currentUser.value?.id);
        console.log('📦 store.currentCall?.caller.id:', store.currentCall?.caller.id);
        console.log('📦 store.currentCall?.receiver.id:', store.currentCall?.receiver.id);
        console.log('📦 Akan memakai UID:', Number(currentUser.value.id));


        console.log('📦 UID:', currentUser.value.id, '(type:', typeof currentUser.value.id, ')');
        console.log('📦 Token:', store.agoraToken.substring(0, 20) + '...');

        await joinChannel(
            store.channelName,
            store.agoraToken,
            Number(currentUser.value.id)
        );

        console.log('✅ Berhasil bergabung ke channel Agora');
        console.log('📹 Local video track:', localVideoTrack.value ? 'AVAILABLE' : 'NULL');
        console.log('🎤 Local audio track:', localAudioTrack.value ? 'AVAILABLE' : 'NULL');

    } catch (error) {
        console.error('❌ Gagal bergabung ke channel Agora:', error);

        if ((error as any).code === 'UID_CONFLICT') {
            alert('⚠️ Gagal bergabung ke panggilan: UID sudah digunakan di channel ini. Silakan coba lagi.');
        }

        if ((error as any).code === 'INVALID_OPERATION') {
            console.warn('⚠️ Client sudah join channel, skip error ini');
            return;
        }

        alert('Gagal bergabung ke panggilan video. Silakan coba lagi.');
        await handleEndCall();
    }
});

// Durasi panggilan
const durationSeconds = ref(0);
let timerInterval: any = null;

const formattedDuration = computed(() => {
    const m = Math.floor(durationSeconds.value / 60).toString().padStart(2, '0');
    const s = (durationSeconds.value % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
});

// Fungsi update timer
const updateTimer = () => {
    if (currentCall.value?.startedAt) {
        // Hitung sesisih waktu sekarang sampai waktu mulai
        const startTime = new Date(currentCall.value.startedAt).getTime();
        const now = Date.now();
        const diff = Math.floor((now - startTime) / 1000);

        // Pastikan tidak berkurang / minus
        durationSeconds.value = diff > 0 ? diff : 0;
    } else {
        durationSeconds.value++
    }
}

// Memulai durasi ketika panggilan berlangsung
watch(() => isVideoCallActive.value, (active) => {
    if (active) {
        durationSeconds.value = 0;
        timerInterval = setInterval(updateTimer, 1000);
    } else {
        if (timerInterval) clearInterval(timerInterval);
    }
}, { immediate: true });

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

// Deteksi remote mute untuk trigger animasi dynamic island
const isRemoteMuted = computed(() => {
    if (!remoteUser.value) return false;
    // Kalau track audionya null / undefined, kita anggap dia mute
    return !remoteUser.value.audioTrack;
});

const handleEndCall = async () => {
    console.log('🔚 Tombol End Call diklik');

    if (!backendCall.value) {
        console.warn('⚠️ backendCall tidak ada, melakukan cleanup secara paksa');
        await leaveChannel(); // Keluar dari channel Agora
        store.clearCurrentCall();
        store.clearIncomingCall();
        return;
    }

    try {
        // Leave Agora dulu (feedback instan dari UI)
        console.log('👋 Meninggalkan channel Agora...');
        await leaveChannel();

        // Hit backend API
        console.log('📞 Memamnggil API /call/end...')
        await endCall(backendCall.value.id);

        console.log('✅ Panggilan berhasil diakhiri');

        } catch (error) {
            console.error('Gagal untuk mengakhiri panggilan:', error);

            // Cleanup secara paksa
            store.clearCurrentCall();
            store.clearIncomingCall();
    }
};

const handleToggleMute = () => {
    toggleAudio();
};

const handleToggleCamera = () => {
    toggleVideo();
};

// watch status 'ended' dari backend
watch(() => store.callStatus, (newStatus) => {
    if (newStatus === 'ended') {
        // Panggilan diakhiri oleh salah satu pihak
        leaveChannel();
        setTimeout(() => {
            store.clearCurrentCall();
        }, 2000); // Muncul pesan "Panggilan berakhir" selama 2 detik lalu hilang
    }
});

// watch remote user (auto end klo remote disconnect)
watch(() => remoteUsers.value.length, (count, oldCount) => {
    console.log(`👥 Hitungan remote users berubah: ${oldCount} -> ${count}`);
    console.log('👥 Remote users:', remoteUsers.value);

    if (oldCount > 0 && count === 0 && isVideoCallActive.value) {
        console.log('Remote user disconnect, panggilan otomatis ditutup dalam 5 detik...');
        setTimeout(() => {
            if (remoteUsers.value.length === 0) {
                console.log('Otomatis menutup panggilan (Remote disconnect)');
                handleEndCall(); // otomatis menutup panggilan
            }
        }, 5000);
    }
});

// Watch Agora token & channel changes
watch(() => store.agoraToken, (newToken) => {
    console.log('🔑 Agora token updated:', newToken ? 'AVAILABLE' : 'NULL');
});

watch(() => store.channelName, (newChannel) => {
    console.log('📺 Channel name updated:', newChannel);
});

watch(() => localVideoTrack.value, (track) => {
    console.log('📹 Local video track updated:', track ? 'AVAILABLE' : 'NULL');
});

watch(() => remoteVideoTracks.value.size, (size) => {
    console.log('📹 Remote video tracks count:', size);
});
</script>

<template>
    <Transition name="fade">
        <div v-if="isVideoCallActive" class="video-call-container">

            <!-- Remote video (layar utama) -->
            <div class="remote-video-wrapper">
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
                    <p class="text-white">Menunggu koneksi...</p>
                </div>
            </div>

            <!-- Local video (floating kecil) -->
            <div class="local-video-wrapper" :class="localVideoOrientation">
                <VideoPlayer
                    v-if="currentUser"
                    :video-track="localVideoTrack"
                    :audio-track="localAudioTrack"
                    :uid="currentUser.id"
                    :user-name="currentUser.name"
                    :is-local="true"
                    @orientation-detected="(o) => localVideoOrientation = o"
                />
            </div>

            <!-- UI overlay (timer + kontrol) -->
            <div class="call-ui-overlay">
                <!-- timer diatas -->
                <div class="top-bar">
                    <CallTimer
                        :start-time="currentCall?.startedAt"
                    />
                </div>

                <!-- Dynamic island -->
                <div class="dynamic-header">
                    <div class="island name-island" :class="{ 'is-muted': isRemoteMuted }">

                        <span class="remote-name text-truncate">
                            {{ remoteUser?.name || 'Menghubungkan...' }}
                        </span>

                        <Transition name="slide-icon">
                            <div v-if="isRemoteMuted" class="icon-wrapper">
                                <MicOff :size="14" class="text-red-400"/>
                            </div>
                        </Transition>

                    </div>

                    <div class="island timer-island">
                        <div class="rec-dot"></div>
                        <span class="timer-text">{{ formattedDuration }}</span>
                    </div>
                </div>

                <!-- kontrol dibawah (seperti button camera, mic, dll yang ada di dock bawah) -->
                <div class="bottom-bar">
                    <CallControls
                    call-type="video"
                        :is-muted="!isAudioEnabled"
                        :is-speaker-on="false"
                        :is-camera-on="isVideoEnabled"
                        @toggle-mute="handleToggleMute"
                        @toggle-speaker="() => {}"
                        @toggle-camera="handleToggleCamera"
                        @end-call="handleEndCall"
                    />
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.video-call-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: #1a1a1a;
    z-index: 9998;
}

/* remote video */
.remote-video-wrapper :deep(video) {
    width: 100% !important;
    height: 100% !important;
    object-fit: contain !important; /* KUNCI: Jangan di-crop! */
    position: static !important;
}

.remote-video-wrapper {
    position: relative;
    width: 100%;
    height: 100dvh;

    /* Background transparan gelap + blur */
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Komponen player */
.main-remote-video {
    width: 100%;
    height: 100%;
    position: absolute;
    top: 0;
    left: 0;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

:deep(.video-player-container),
:deep(.video-feed),
:deep(.video-feed > div) {
    /* Reset ukuran biar ikut wrapper */
    width: 100% !important;
    height: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: static !important;
    background: transparent !important;
}

.waiting-state {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #2c2c3e, #1a1a2e);
}

/* UI overlay */
.call-ui-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none; /* biar video tetep bisa diklik */
    z-index: 20;
}

.call-ui-overlay > * {
    pointer-events: auto; /* tapi childnya (timer, controls) tetep bisa diklik */
}

.top-bar {
    position: absolute;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
}

.bottom-bar {
    position: absolute;
    bottom: 40px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 30;
}

/* Local video (floating dipojok kanan atas) */
.local-video-wrapper :deep(video) {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important; /* KUNCI: Penuhin kotak! */
    position: static !important;
    border-radius: 12px !important; /* Ikut wrapper */
}

.local-video-wrapper {
    position: absolute;
    top: 20px;
    right: 20px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    border: 2px solid rgba(255, 255, 255, 0.1);
    z-index: 10;
    transition: all 0.3s ease;
}

/* Orientasi landscape untuk kamera laptop / pc */
.local-video-wrapper.landscape {
    width: clamp(360px, 25vw, 240px);
    aspect-ratio: 16/9;
}

/* Orientasi portrait untuk kamera hp */
.local-video-wrapper.portrait {
    width: clamp(140px, 15vw, 180px);
    aspect-ratio: 9/16;
}

/* Responsive - Tablet */
@media (max-width: 768px) {
    .local-video-wrapper.landscape {
        width: clamp(140px, 20vw, 180px);
        top: 15px;
        right: 15px;
    }
    
    .local-video-wrapper.portrait {
        width: clamp(100px, 15vw, 130px);
        top: 15px;
        right: 15px;
    }
}

/* Responsive - Mobile */
@media (max-width: 480px) {
    .local-video-wrapper.landscape {
        width: clamp(100px, 25vw, 140px);
        top: 10px;
        right: 10px;
    }
    
    .local-video-wrapper.portrait {
        width: clamp(80px, 20vw, 110px);
        top: 10px;
        right: 10px;
    }
}

/* Animasi */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Wrapper Overlay biar bisa klik tombol tapi tembus pandang */
.call-ui-overlay {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    pointer-events: none; /* Biar bisa klik video di belakangnya */
}
.call-ui-overlay > * { pointer-events: auto; } /* Tombol & Island tetep bisa diklik */

/* Posisi Island di Kiri Atas */
.dynamic-header {
    position: absolute;
    top: 20px; left: 20px;
    display: flex; align-items: center; gap: 8px;
    z-index: 50;
    padding-top: env(safe-area-inset-top); /* Aman dari poni HP */
}

/* Style Dasar Kotak (Pill) */
.island {
    height: 36px;
    display: flex; align-items: center; padding: 0 14px;
    background: rgba(20, 20, 20, 0.65); /* Gelap Transparan */
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    color: white; font-weight: 600; font-size: 0.9rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); /* Animasi smooth */
}

/* Spesifik: Island Nama */
.name-island { gap: 0; max-width: 200px; }
.name-island.is-muted { 
    background: rgba(40, 20, 20, 0.8); /* Agak merah kalau mute */
    border-color: rgba(255, 100, 100, 0.2);
}
.remote-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Spesifik: Island Timer */
.timer-island { gap: 8px; min-width: 70px; justify-content: center; font-variant-numeric: tabular-nums; }
.rec-dot { width: 6px; height: 6px; background: #ef4444; border-radius: 50%; animation: blink 2s infinite; }

/* Animasi Kedip Rec Dot */
@keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

/* Style icon permanen */
.icon-wrapper {
    display: flex;
    align-items: center;
    margin-left: 10px;
}

/* Animasi Icon Mic Geser (Sliding) */
.slide-icon-enter-active, .slide-icon-leave-active {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    overflow: hidden; max-width: 20px; opacity: 1; margin-left: 10px;
}
.slide-icon-enter-from, .slide-icon-leave-to {
    max-width: 0; opacity: 0; margin-left: 0;
}
</style>