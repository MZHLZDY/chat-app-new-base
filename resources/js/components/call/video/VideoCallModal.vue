<script setup lang="ts">
import { computed, watch, onMounted } from 'vue'; 
import { useCallStore } from '@/stores/callStore';
import { useVideoCall } from '@/composables/useVideoCall';
import { usePersonalCall } from '@/composables/usePersonalCall';
import { useAgora } from '@/composables/useAgora';
import { useAuthStore } from '@/stores/authStore';
import VideoPlayer from './VideoPlayer.vue';
import CallTimer from '../shared/CallTimer.vue';
import CallControls from '../shared/CallControls.vue';
import { usePage } from '@inertiajs/vue3';

const store = useCallStore();
const page = usePage();
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

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
    isVideoEnabled
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
        const uid = remoteUsers.value[0];
        return {
            uid,
            videoTrack: remoteVideoTracks.value.get(uid),
            audioTrack: remoteAudioTracks.value.get(uid),
            name: currentCall.value?.caller.id === currentUser.value?.id
                ? currentCall.value?.receiver.name
                : currentCall.value?.caller.name
        };
    }
    return null;
});

// join agora channel saat modal muncul
onMounted(async () => {
    console.log('📹 VideoCallModal mounted');
    console.log('📦 IsVideoCallActive:', isVideoCallActive.value);
    console.log('📦 agoraToken:', store.agoraToken);
    console.log('📦 channelName:', store.channelName);
    console.log('📦 currentUser:', currentUser.value);

    if (!isVideoCallActive.value) {
        console.warn('⚠️ Video call tidak aktif, skip join channel');
        return;
    }

    if (!store.agoraToken || !store.channelName) {
        console.error('❌ Token atau channel name tidak ada!');
        return;
    }

    if (!currentUser.value?.id) {
        console.error('❌ User ID tidak ditemukan!');
        return;
    }

    try {
        console.log('🚀 Bergabung ke Agora Channel...');
        console.log('📦 Channel:', store.channelName);
        console.log('📦 UID:', currentUser.value.id);

        await joinChannel(
            store.channelName,
            store.agoraToken,
            currentUser.value.id
        );

        console.log('✅ Berhasil bergabung ke channel Agora');
        console.log('📹 Local video track:', localVideoTrack.value);
        console.log('🎤 Local audio track:', localAudioTrack);

    } catch (error) {
        console.error('❌ Gagal bergabung ke channel Agora:', error);
    }
});

const handleEndCall = async () => {
    if (backendCall.value) {
        try {
            await endCall(backendCall.value.id) // Panggil API untuk mengakhiri panggilan
            await leaveChannel(); // Keluar dari channel Agora
        } catch (error) {
            console.error('Gagal untuk mengakhiri panggilan:', error);
        }
    }
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
                />
                <div v-else class="waiting-state">
                    <p class="text-white">Menunggu koneksi...</p>
                </div>
            </div>

            <!-- Local video (floating kecil) -->
            <div class="local-video-wrapper">
                <VideoPlayer
                    v-if="currentUser"
                    :video-track="localVideoTrack"
                    :audio-track="localAudioTrack"
                    :uid="currentUser.id"
                    :user-name="currentUser.name"
                    :is-local="true"
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

                <!-- kontrol dibawah (seperti button camera, mic, dll yang ada di dock bawah) -->
                <div class="bottom-bar">
                    <CallControls
                        :is-muted="!isAudioEnabled"
                        :is-speaker-on="!isVideoEnabled"
                        :is-camera-on="!isVideoCallActive"
                        @toggle-mute="toggleAudio"
                        @toggle-speaker="() => {}"
                        @toggle-camera="toggleVideo"
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

/* remote video (full screen) */
.remote-video-wrapper {
    width: 100%;
    height: 100%;
}

.waiting-state {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #2c2c3e, #1a1a2e);
}

/* Local video (floating dipojok kanan atas) */
.local-video-wrapper {
    position: absolute;
    top: 20px;
    right: 20px;
    width: 180px;
    height: 240px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    border: 2px solid rgba(255, 255, 255, 0.1);
    z-index: 10;
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
</style>