<script setup lang="ts">
import { computed } from 'vue'; 
import { useCallStore } from '@/stores/callStore';
import { useVideoCall } from '@/composables/useVideoCall';
import { useAgora } from '@/composables/useAgora';
import VideoPlayer from './VideoPlayer.vue';
import CallTimer from '../shared/CallTimer.vue';
import CallControls from '../shared/CallControls.vue';
import { usePage } from '@inertiajs/vue3';

const store = useCallStore();
const page = usePage();
const currentUser = (page.props.auth as any)?.user;

const {
    endCall, toggleAudio, toggleVideo
} = useVideoCall();

const {
    localAudioTrack,
    localVideoTrack,
    remoteUsers, // Array UID remote user
    remoteAudioTracks,
    remoteVideoTracks,
    isAudioEnabled,
    isVideoEnabled
} = useAgora();

const currentCall = computed(() => store.currentCall);

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
            name: currentCall.value?.caller.id === currentUser?.id
                ? currentCall.value?.receiver.name
                : currentCall.value?.caller.name
        };
    }
    return null;
});

const handleEndCall = () => {
    if (currentCall.value) {
        endCall(currentCall.value.id);
    }
}
</script>

<template>
    <Transition name="fade">
        <div v-if="isVideoCallActive" class="video-call-container">

            <!-- Remote video (layar utama) -->
            <div class="remote-video-wrapper">
                <VideoPlayer
                    v-if="remoteUser"
                    :video-track="remoteUser.videoTrack"
                    :auido-track="remoteUser.audioTrack"
                    :uid="remoteUser.uid"
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
                        :audio-enabled="isAudioEnabled"
                        :video-enabled="isVideoEnabled"
                        :has-video="true"
                        @toggle-audio="toggleAudio"
                        @toggle-video="toggleVideo"
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