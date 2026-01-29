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
                    call-type="video"
                        :is-muted="!isAudioEnabled"
                        :is-speaker-on="false"
                        :is-camera-on="isVideoEnabled"
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