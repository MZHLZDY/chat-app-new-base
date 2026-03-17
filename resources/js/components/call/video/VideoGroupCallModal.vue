<script setup lang="ts">
import { computed, watch, ref, onMounted, onUnmounted } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from '@/stores/authStore';
import { useAgora } from '@/composables/useAgora';
import { useVideoGroupCall } from '@/composables/useVideoGroupCall';
import VideoPlayer from './VideoPlayer.vue';
import CallControls from '../shared/CallControls.vue';
import { Minimize2, SwitchCamera, UserPlus, MicOff } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';

const store = useCallStore();
const authStore = useAuthStore();
const emit = defineEmits(['minimize', 'addUser']);

const currentUser = computed(() => authStore.user);

const { leaveGroupVideoCall, toggleMute, toggleCamera } = useVideoGroupCall();

const {
    isJoined,
    joinChannel,
    localAudioTrack,
    localVideoTrack,
    remoteUsers,
    isAudioEnabled,
    isVideoEnabled,
    switchCamera,
    hasMultipleCameras,
} = useAgora();

// Cek apakah ini video call grup yang lagi aktif
const isGroupVideoActive = computed(() => 
    store.currentCall?.type === 'video' && 
    store.isGroupCall && 
    store.callStatus === 'ongoing'
);

const localAvatarUrl = computed(() => {
    const u = currentUser.value;
    return u?.avatar || u?.profile_photo_url || u?.photo || '';
});

const getParticipantName = (uid: string | number) => {
    // 1️⃣ Cek dulu apakah dia itu Host / Penelepon Asli
    if (store.currentCall?.caller?.id === Number(uid)) {
        return store.currentCall.caller.name;
    }
    if (store.backendGroupCall?.host?.id === Number(uid)) {
        return store.backendGroupCall.host.name;
    }

    // 2️⃣ Kalau bukan, cari di list member grup participant
    const participant = store.groupParticipants.find((p: any) => {
        const pId = typeof p === 'object' ? (p.user_id || p.id) : p;
        return Number(pId) === Number(uid);
    });
    if (participant && typeof participant === 'object') {
        return participant?.user?.name || (participant as any).name || `User ${uid}`;
    }
    return `User ${uid}`;
};

const getParticipantAvatar = (uid: string | number) => {
    // Cek host
    if (store.currentCall?.caller?.id === Number(uid)) {
        const c = store.currentCall.caller;
        return c.avatar || (c as any).photo || (c as any).profile_photo_url || '';
    }
    if (store.backendGroupCall?.host?.id === Number(uid)) {
        const c = store.backendGroupCall.host;
        return c.avatar || (c as any).photo || (c as any).profile_photo_url || '';
    }

    // Member lainnya
    const participant = store.groupParticipants.find((p: any) => {
        const pId = typeof p === 'object' ? (p.user_id || p.id) : p;
        return Number(pId) === Number(uid);
    });
    if (participant && typeof participant === 'object') {
        return (participant.user as any)?.avatar || (participant.user as any)?.photo || (participant.user as any)?.profile_photo_url || (participant as any).avatar || (participant as any).photo || '';
    }
    return '';
};

// Kombinasikan local user + remote user buat hitung total element di grid
const totalStreams = computed(() => 1 + remoteUsers.value.length);
const gridClass = computed(() => {
    const total = totalStreams.value;
    if (total === 1) return 'grid-1';
    if (total === 2) return 'grid-2';
    if (total === 3 || total === 4) return 'grid-4';
    if (total > 4 && total <= 6) return 'grid-6';
    return 'grid-many';
});

const handleEndCall = async () => {
    if (store.currentCall) {
        await leaveGroupVideoCall(store.currentCall.id);
    }
};

const handleAddUser = () => {
    emit('addUser');
    toast.info('Fitur tambah peserta akan segera hadir!');
};

// UI Hiding logic
const showMinimizeDesktop = ref(false);
const showControls = ref(true);
let minimizeHideTimeout: any = null;
let controlsHideTimeout: any = null;

// Tambahkan Fungsi penahan hover ini
const clearHideTimeout = () => {
    if (controlsHideTimeout) clearTimeout(controlsHideTimeout);
};

const startHideTimeout = () => {
    if (controlsHideTimeout) clearTimeout(controlsHideTimeout);
    controlsHideTimeout = setTimeout(() => {
        showControls.value = false;
    }, 3000);
};

const toggleControls = () => {
    showControls.value = !showControls.value;
};

const handleMouseMove = (e: MouseEvent) => {
    if (window.innerWidth > 768) {
        // Cek kalau kursor arah atas (Minimize)
        if (e.clientY < 120) {
            showMinimizeDesktop.value = true;
            if (minimizeHideTimeout) {
                clearTimeout(minimizeHideTimeout);
                minimizeHideTimeout = null;
            }
        } else {
            if (showMinimizeDesktop.value && !minimizeHideTimeout) {
                minimizeHideTimeout = setTimeout(() => {
                    showMinimizeDesktop.value = false;
                    minimizeHideTimeout = null;
                }, 2000);
            }
        }

        // Tampilkan controls kalau mouse gerak
        showControls.value = true;
        if (controlsHideTimeout) clearTimeout(controlsHideTimeout);
        controlsHideTimeout = setTimeout(() => {
            showControls.value = false;
        }, 3000);
    }
};

// Timer logic
const durationSeconds = ref(0);
let timerInterval: any = null;

const formattedDuration = computed(() => {
    const m = Math.floor(durationSeconds.value / 60).toString().padStart(2, '0');
    const s = (durationSeconds.value % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
});

const updateTimer = () => {
    if (store.callStatus === 'ongoing' && store.callStartTime) {
        const now = new Date().getTime();
        const start = new Date(store.callStartTime).getTime();
        durationSeconds.value = Math.floor((now - start) / 1000);
    }
};

watch(() => isGroupVideoActive.value, async (active) => {
    if (active) {
        document.body.style.overflow = "hidden";
        timerInterval = setInterval(updateTimer, 1000);
        if (!isJoined.value && store.agoraToken && store.channelName && currentUser.value) {
            try {
                await joinChannel(store.channelName, store.agoraToken, Number(currentUser.value.id));
            } catch (error) {
                console.error('Gagal join channel Group:', error);
            }
        }
    } else {
        document.body.style.overflow = "";
        if (timerInterval) clearInterval(timerInterval);
    }
}, { immediate: true });

onMounted(async () => {
    window.addEventListener('mousemove', handleMouseMove);

    // KUNCI PENTING: Memastikan Host join channel otomatis saat window stream terbuka!
    if (isGroupVideoActive.value && !isJoined.value && store.agoraToken && store.channelName && currentUser.value) {
        try {
            await joinChannel(store.channelName, store.agoraToken, Number(currentUser.value.id));
        } catch (error) {
            console.error('Gagal join channel Group:', error);
        }
    }
});

onUnmounted(() => {
    window.removeEventListener('mousemove', handleMouseMove);
    if (timerInterval) clearInterval(timerInterval);
    document.body.style.overflow = "";
});
</script>

<template>
    <Transition name="fade">
        <div v-if="isGroupVideoActive" class="video-group-modal" @click="toggleControls">
            
            <!-- OVERLAY UTAMA (Island & Controls) -->
            <div class="call-ui-overlay">
                
                <!-- Dynamic Island (Kiri Atas) -->
                <div class="dynamic-header" @click.stop.prevent>
                    <div class="island name-island">
                        <!-- TAMBAHKAN FALLBACK GROUP NAME -->
                        <span class="remote-name">
                            {{ store.activeGroupName || (store.incomingCall as any)?.groupName || store.backendGroupCall?.group?.name || 'Group Video Call' }}
                        </span>
                    </div>

                    <div class="island timer-island">
                        <div class="rec-dot" :class="{ 'animate-pulse': totalStreams === 1 }"></div>
                        <span class="timer-text">{{ formattedDuration }}</span>
                    </div>

                    <!-- Button minimize versi island (khusus mobile) -->
                    <div class="island minimize-island custom-clickable" @click="emit('minimize')">
                        <Minimize2 :size="14" />
                    </div>
                </div>

                <!-- Bagian Kanan Atas (Opsi Tambahan Kamera) -->
                <div class="top-right-actions" @click.stop.prevent>
                    <button v-if="hasMultipleCameras" class="btn btn-icon btn-sm action-btn float-btn" @click="switchCamera()" title="Tukar Kamera">
                        <SwitchCamera :size="20"/>
                    </button>
                    <button class="btn btn-icon btn-sm action-btn float-btn" @click="handleAddUser" title="Tambah Peserta">
                        <UserPlus :size="20"/>
                    </button>
                </div>

                <!-- Button minimize muncul dari atas (khusus desktop) -->
                <Transition name="slide-down">
                    <div v-if="showMinimizeDesktop" class="desktop-minimize-wrapper" @click="emit('minimize')">
                        <div class="minimize-btn-content">
                            <Minimize2 :size="20" />
                            <span>Minimize</span>
                        </div>
                    </div>
                </Transition>

                <!-- Controls Bottom -->
                <Transition name="slide-up">
                    <div v-show="showControls" class="bottom-bar" @click.stop.prevent>
                        <CallControls
                            call-type="video"
                            :is-muted="!isAudioEnabled"
                            :is-speaker-on="false"
                            :is-camera-on="isVideoEnabled"
                            :is-group-call="true"
                            @toggle-mute="toggleMute"
                            @toggle-video="toggleCamera"
                            @end-call="handleEndCall"
                        />
                    </div>
                </Transition>
            </div>

            <!-- STREAM GRID BACKGROUND GELAP -->
            <div class="stream-backdrop"></div>
            <div class="video-grid-container" :class="gridClass">
                <!-- Local User -->
                <div class="video-item local-user">
                    <VideoPlayer
                        v-if="currentUser"
                        :video-track="isVideoEnabled ? localVideoTrack : undefined"
                        :audio-track="localAudioTrack"
                        :uid="currentUser.id"
                        :user-name="currentUser.name || 'Anda'"
                        :avatar-url="localAvatarUrl"
                        :is-local="true"
                        :hide-name-label="true"
                    />
                    <!-- Small Dynamic Island per User -->
                    <div class="grid-island" :class="{ 'is-muted': !isAudioEnabled }">
                        <span class="user-name">Anda</span>
                        <Transition name="slide-icon">
                            <div v-if="!isAudioEnabled" class="icon-wrapper">
                                <MicOff :size="12" color="#ff4444"/>
                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- Remote Users -->
                <div v-for="user in remoteUsers" :key="user.uid" class="video-item remote-user">
                    <VideoPlayer
                        :video-track="user.videoTrack"
                        :audio-track="user.audioTrack"
                        :uid="Number(user.uid)"
                        :user-name="getParticipantName(user.uid)"
                        :avatar-url="getParticipantAvatar(user.uid)"
                        :is-local="false"
                        :hide-name-label="true"
                    />
                    <div class="grid-island" :class="{ 'is-muted': !user.audioTrack }">
                        <span class="user-name">{{ getParticipantName(user.uid) }}</span>
                        <Transition name="slide-icon">
                            <div v-if="!user.audioTrack" class="icon-wrapper">
                                <MicOff :size="12" color="#ff4444"/>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

        </div>
    </Transition>
</template>

<style scoped>
.video-group-modal {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100dvh;
    background-color: transparent !important;
    z-index: 9998; display: flex; flex-direction: column; overflow: hidden;
}

.stream-backdrop {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    /* Gunakan 0.2 atau 0.4 di angka belakang rgba untuk bikin transparan */
    background: rgba(10, 10, 14, 0.2); 
    backdrop-filter: blur(20px); 
    -webkit-backdrop-filter: blur(20px); 
    z-index: -1;
}

.call-ui-overlay {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    pointer-events: none; z-index: 50;
}
.call-ui-overlay > * { pointer-events: auto; }

/* DYNAMIC ISLAND STYLE (COPY DARI PERSONAL) */
.dynamic-header {
    position: absolute; top: 20px; left: 20px;
    display: flex; align-items: center; gap: 8px; z-index: 50;
    padding-top: env(safe-area-inset-top);
}
.island {
    height: 36px; display: flex; align-items: center; padding: 0 14px;
    background: rgba(15, 15, 15, 0.65); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px; color: white; font-weight: 600; font-size: 0.9rem;
    box-shadow: 0 8px 25px rgba(0,0,0,0.25); transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.name-island { max-width: 220px; }
.remote-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.timer-island { gap: 8px; min-width: 80px; justify-content: center; font-variant-numeric: tabular-nums; }
.rec-dot { width: 8px; height: 8px; background: #ff4444; border-radius: 50%; box-shadow: 0 0 10px #ff4444; animation: pulse 2s infinite; }

.top-right-actions {
    position: absolute; top: 20px; right: 20px; display: flex; gap: 10px; z-index: 50; padding-top: env(safe-area-inset-top);
}
.action-btn {
    border-radius: 50% !important; background: rgba(15, 15, 15, 0.65) !important; border: 1px solid rgba(255,255,255,0.1) !important;
    color: white !important; backdrop-filter: blur(10px); width: 44px; height: 44px; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
}
.action-btn:hover { background-color: rgba(255,255,255,0.3) !important; transform: scale(1.05); }

/* Grid Videos */
.video-grid-container {
    flex: 1; display: grid; gap: 12px; padding: 80px 16px 140px 16px;
    height: 100%; width: 100%; max-width: 1300px; margin: 0 auto; align-content: center; z-index: 10;
}
.grid-1 { grid-template-columns: 1fr; grid-template-rows: 1fr; }
.grid-2 { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); grid-template-rows: 1fr; }
.grid-4 { grid-template-columns: repeat(2, 1fr); grid-template-rows: repeat(2, 1fr); }
.grid-6 { grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(2, 1fr); }
.grid-many { grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); grid-auto-rows: 1fr; }

/* Item Video box */
.video-item {
    position: relative; border-radius: 16px; overflow: hidden; background-color: transparent;
    display: flex; justify-content: center; align-items: center; border: 1px solid rgba(255, 255, 255, 0.05);
}
.video-item :deep(video) { filter: drop-shadow(0 10px 40px rgba(0,0,0,0.5)); object-fit: cover !important; }
.video-item :deep(.avatar-circle img) { width: 120px !important; height: 120px !important; }
.video-item :deep(.status-text) { display: none; }

/* Dynamic Island per user */
.grid-island {
    position: absolute; bottom: 12px; left: 12px; height: 32px; display: flex; align-items: center; padding: 0 12px;
    background: rgba(15, 15, 15, 0.6); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px; color: white; font-weight: 500; font-size: 0.85rem; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease; z-index: 10;
}
.grid-island.is-muted { background: rgba(30, 10, 10, 0.7); border-color: rgba(255, 80, 80, 0.3); }

/* Animation Island Mic Off */
.icon-wrapper { display: flex; align-items: center; margin-left: 8px; }
.slide-icon-enter-active, .slide-icon-leave-active { transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); overflow: hidden; max-width: 20px; opacity: 1; }
.slide-icon-enter-from, .slide-icon-leave-to { max-width: 0; opacity: 0; margin-left: 0; }
@keyframes pulse { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.9); } 100% { opacity: 1; transform: scale(1); } }

/* Desktop Hover Minimize */
.desktop-minimize-wrapper { position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 250px; height: 60px; z-index: 100; display: flex; justify-content: center; align-items: flex-end; padding-bottom: 5px; cursor: pointer; }
.minimize-btn-content { display: flex; align-items: center; gap: 8px; background: rgba(30, 30, 30, 0.8); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); color: white; font-size: 0.9rem; font-weight: 500; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: all 0.2s ease; }
.minimize-btn-content:hover { background: rgba(50, 50, 50, 0.9); transform: scale(1.05); }
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-80px) translateX(-50%); opacity: 0; }

/* Control Bottom */
.bottom-bar { position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%); z-index: 60; }
.slide-up-enter-active, .slide-up-leave-active { transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(80px) translateX(-50%); opacity: 0; }

.minimize-island { width: 38px; padding: 0 !important; justify-content: center; cursor: pointer; background: rgba(255, 255, 255, 0.1); }
.minimize-island:active { transform: scale(0.92); background: rgba(255, 255, 255, 0.2); }
.custom-clickable { pointer-events: auto; }

/* Responsive HP Portrait */
@media (min-width: 768px) { .minimize-island { display: none !important; } }
@media (max-width: 768px) {
    .grid-2 { grid-template-columns: 1fr; grid-template-rows: repeat(2, 1fr); }
    .grid-4, .grid-6 { grid-template-columns: repeat(2, 1fr); }
    .video-grid-container { padding: 80px 8px 120px 8px; gap: 8px; }
    .bottom-bar { bottom: 20px; width: 90%; display: flex; justify-content: center; }
}
</style>