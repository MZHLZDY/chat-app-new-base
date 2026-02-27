<script setup lang="ts">
import { computed } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useVoiceGroupCall } from '@/composables/useVoiceGroupCall';
import { Maximize2, Mic, MicOff, PhoneForwarded } from 'lucide-vue-next';
import CallTimer from '../shared/CallTimer.vue';
import CallAvatar from '../shared/CallAvatar.vue';

const callStore = useCallStore();
const { toggleMute, isAudioEnabled, leaveGroupVoiceCall } = useVoiceGroupCall();

// Mengambil data partisipan yang sudah join
const activeParticipants = computed(() => {
    return callStore.groupParticipants.filter(p => p.status === 'joined');
});

// Menampilkan maksimal 3 avatar, sisanya berupa angka "+X"
const displayParticipants = computed(() => activeParticipants.value.slice(0, 3));
const remainingCount = computed(() => Math.max(0, activeParticipants.value.length - 3));

// Fungsi untuk kembali ke tampilan layar penuh
const maximizeCall = () => {
    callStore.toggleMinimize();
};

// Fungsi untuk keluar dari panggilan
const handleLeaveCall = () => {
    if (callStore.currentCall) {
        leaveGroupVoiceCall(callStore.currentCall.id);
    }
};
</script>

<template>
    <div 
        v-if="callStore.currentCall && callStore.isInCall && callStore.isMinimized && callStore.isGroupCall" 
        class="floating-call-widget"
    >
        <div class="glass-container">
            <div class="call-info" @click="maximizeCall">
                <div class="call-title">
                    <span class="group-name">{{ callStore.currentCall.group?.name || 'Group Call' }}</span>
                    <CallTimer :start-time="callStore.currentCall.startedAt" class="timer-text" />
                </div>
                
                <div class="avatar-stack">
                    <div 
                        v-for="user in displayParticipants" 
                        :key="user.id"
                        class="stacked-avatar"
                    >
                        <CallAvatar 
                            :photo-url="user.user?.photo || user.user?.profile_photo_url" 
                            :display-name="user.user?.name"
                            size="35px" 
                        />
                    </div>
                    <div v-if="remainingCount > 0" class="stacked-avatar more-count">
                        +{{ remainingCount }}
                    </div>
                </div>
            </div>

            <div class="controls-divider"></div>

            <div class="call-controls">
                <button 
                    @click="toggleMute" 
                    class="control-btn mute-btn" 
                    :class="{ 'muted': !isAudioEnabled }"
                    title="Toggle Mute"
                >
                    <component :is="!isAudioEnabled ? MicOff : Mic" :size="18" />
                </button>

                <button 
                    @click="maximizeCall" 
                    class="control-btn maximize-btn"
                    title="Kembali ke Layar Penuh"
                >
                    <Maximize2 :size="18" />
                </button>

                <button 
                    @click="handleLeaveCall" 
                    class="control-btn end-btn"
                    title="Akhiri Panggilan"
                >
                    <PhoneForwarded :size="18" />
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.floating-call-widget {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 9999;
    animation: slide-up 0.3s ease-out;
    cursor: default;
}

.glass-container {
    background: rgba(30, 41, 59, 0.85); /* Tema gelap/glassmorphism */
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    min-width: 220px;
}

/* Bagian Atas: Info & Avatar (Bisa di-klik untuk Maximize) */
.call-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    border-radius: 12px;
    transition: background 0.2s;
}

.call-info:hover {
    background: rgba(255, 255, 255, 0.05);
}

.call-title {
    display: flex;
    flex-direction: column;
}

.group-name {
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    max-width: 100px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.timer-text {
    color: #10B981; /* Warna hijau indikator aktif */
    font-size: 0.8rem;
    font-weight: 500;
}

/* Avatar Stack Effect */
.avatar-stack {
    display: flex;
    align-items: center;
}

.stacked-avatar {
    margin-left: -10px;
    border: 2px solid #1E293B;
    border-radius: 50%;
    background-color: #334155;
}

.stacked-avatar:first-child {
    margin-left: 0;
}

.more-count {
    width: 35px;
    height: 35px;
    display: flex;
    justify-content: center;
    align-items: center;
    color: white;
    font-size: 0.8rem;
    font-weight: 600;
}

.controls-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.1);
    width: 100%;
}

/* Kontrol Bawah */
.call-controls {
    display: flex;
    justify-content: space-around;
    align-items: center;
}

.control-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    color: white;
    transition: all 0.2s;
    background: rgba(255, 255, 255, 0.1);
}

.control-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

.mute-btn.muted {
    background: rgba(239, 68, 68, 0.2);
    color: #EF4444; /* Red */
}

.end-btn {
    background: #EF4444; /* Red */
}

.end-btn:hover {
    background: #DC2626;
}

@keyframes slide-up {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>