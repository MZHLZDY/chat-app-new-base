<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import { Minimize2 } from 'lucide-vue-next'; 
import { themeMode } from "@/layouts/default-layout/config/helper"; 
import { useAuthStore } from "@/stores/auth"; 
// --- IMPORT USEAGORA & STORE ---
import { useAgora } from "@/composables/useAgora";
import { useCallStore } from "@/stores/callStore";
import { usePersonalCall } from "@/composables/usePersonalCall";
import CallAvatar from '../shared/CallAvatar.vue';
import CallControls from '../shared/CallControls.vue';
import CallTimer from '../shared/CallTimer.vue';

interface Props {
  remoteName: string;
  remotePhoto: string;
  isMuted: boolean;
  isSpeakerOn: boolean;
  volumeLevel?: number; 
}

const props = withDefaults(defineProps<Props>(), {
  volumeLevel: 0,
});

const emit = defineEmits([
  'toggleMute', 
  'toggleSpeaker', 
  'endCall', 
  'minimize' 
]);

const currentThemeMode = computed(() => themeMode.value);
const authStore = useAuthStore();
const callStore = useCallStore();
const { endCall } = usePersonalCall();

// --- INISIALISASI USEAGORA ---
const { 
  joinChannel,
  leaveChannel,
  toggleAudio,
  toggleVideo,
  remoteUsers,
  remoteAudioTracks,
  localAudioTrack,
  localVideoTrack,
  isAudioEnabled,
  isJoined
} = useAgora();

// Data User Caller (Kita)
const localPhoto = computed(() => authStore.userPhotoUrl || null);
const localName = computed(() => authStore.user?.name || 'Me');

const isCameraOn = ref(false);
const simulatedVolume = ref(0);
let audioInterval: number | null = null;

// Cek apakah voice call aktif
const isVoiceCallActive = computed(() => 
    callStore.currentCall?.type === 'voice' && 
    callStore.callStatus === 'ongoing'
);

// Data call dari store
const currentCall = computed(() => callStore.currentCall);
const backendCall = computed(() => callStore.backendCall);

// Join channel saat modal muncul
onMounted(async () => {
    console.log('🎤 VoiceCallModal mounted');
    console.log('📦 isVoiceCallActive:', isVoiceCallActive.value);
    console.log('📦 currentUser ID:', authStore.user?.id);
    console.log('📦 agoraToken:', callStore.agoraToken ? 'AVAILABLE' : 'NULL');
    console.log('📦 channelName:', callStore.channelName);

    if (!isVoiceCallActive.value) {
        console.warn('⚠️ Voice call tidak aktif, skip join channel');
        return;
    }

    if (!callStore.agoraToken || !callStore.channelName) {
        console.error('❌ Token atau channel name tidak ada!');
        console.error('📦 agoraToken:', callStore.agoraToken);
        console.error('📦 channelName:', callStore.channelName);
        return;
    }

    if (!authStore.user?.id) {
        console.error('❌ User ID tidak ditemukan!');
        return;
    }

    // Cek apakah sudah join
    if (isJoined.value || callStore.hasJoinedAgora) {
        console.log('✅ Sudah bergabung ke channel Agora, skip joinChannel');
        // Pastikan microphone tetap ON
        if (localAudioTrack.value) {
            await localAudioTrack.value.setEnabled(true);
            console.log('🎤 Microphone enabled');
        }
        return;
    }

    try {
        console.log('🚀 Bergabung ke Agora Channel untuk voice call...');
        console.log('📦 Channel:', callStore.channelName);
        console.log('📦 UID:', authStore.user.id);

        await joinChannel(
            callStore.channelName,
            callStore.agoraToken,
            Number(authStore.user.id)
        );

        console.log('✅ Berhasil bergabung ke channel Agora untuk voice call');
        
        // Voice call: matikan kamera, nyalakan microphone
        if (localVideoTrack.value) {
            await localVideoTrack.value.setEnabled(false);
            console.log('📹 Camera disabled untuk voice call');
        }
        
        if (localAudioTrack.value) {
            await localAudioTrack.value.setEnabled(true);
            console.log('🎤 Microphone enabled untuk voice call');
        }
        
    } catch (error: any) {
        console.error('❌ Gagal bergabung ke channel Agora:', error);
        
        if (error.code === 'UID_CONFLICT') {
            alert('⚠️ Gagal bergabung ke panggilan: UID sudah digunakan di channel ini.');
        }
    }

    startSimulation();
    subscribeRemoteAudio();
});

const startSimulation = () => {
    audioInterval = window.setInterval(() => {
        const randomVol = Math.random() > 0.3 ? Math.random() * 60 : 10; 
        simulatedVolume.value = randomVol;
    }, 200);
};

const subscribeRemoteAudio = () => {
    if (remoteUsers.value.length > 0) {
        const user = remoteUsers.value[0];
        if (user && user.audioTrack) {
            console.log("🔊 VoiceCallModal: Subscribing to remote audio track");
            if (user.audioTrack.setVolume) {
                user.audioTrack.setVolume(100);
            }
            
            const uidStr = user.uid.toString();
            const audioTrack = remoteAudioTracks.value.get(uidStr);
            if (audioTrack) {
                console.log('🎤 VoiceCallModal: Found remote audio track, setting volume...');
                audioTrack.play();
                if (audioTrack.setVolume) {
                    audioTrack.setVolume(100);
                }
            }
        }
    }
};

// Handle end call seperti di video call
const handleEndCall = async () => {
    console.log('🔚 Tombol End Call diklik di VoiceCallModal');

    if (!backendCall.value) {
        console.warn('⚠️ backendCall tidak ada, melakukan cleanup secara paksa');
        await leaveChannel();
        callStore.clearCurrentCall();
        return;
    }

    try {
        // Leave Agora dulu
        console.log('👋 Meninggalkan channel Agora...');
        await leaveChannel();

        // Hit backend API
        console.log('📞 Memanggil API /call/end...')
        await endCall(backendCall.value.id);

        console.log('✅ Panggilan berhasil diakhiri');

    } catch (error) {
        console.error('Gagal untuk mengakhiri panggilan:', error);
        
        // Cleanup secara paksa
        callStore.clearCurrentCall();
    }
};

// Handle toggle mute
const handleToggleMute = () => {
    toggleAudio();
};

// Watch remote user (auto end jika remote disconnect)
watch(() => remoteUsers.value.length, (count, oldCount) => {
    console.log(`👥 Hitungan remote users berubah: ${oldCount} -> ${count}`);
    
    if (oldCount > 0 && count === 0 && isVoiceCallActive.value) {
        console.log('Remote user disconnect, panggilan otomatis ditutup dalam 5 detik...');
        setTimeout(() => {
            if (remoteUsers.value.length === 0) {
                console.log('Otomatis menutup panggilan (Remote disconnect)');
                handleEndCall();
            }
        }, 5000);
    }
});

// Watch untuk remote users
watch(() => remoteUsers.value.length, (count, oldCount) => {
    console.log(`👥 VoiceCallModal - Remote users count: ${oldCount} -> ${count}`);
    
    if (count > 0) {
        subscribeRemoteAudio();
    }
});

// Watch untuk remote audio tracks
watch(() => remoteAudioTracks.value.size, (size) => {
    console.log('🎤 VoiceCallModal - Remote audio tracks count:', size);
    if (size > 0) {
        subscribeRemoteAudio();
    }
});

onUnmounted(() => {
    if (audioInterval) clearInterval(audioInterval);
    console.log('🎤 VoiceCallModal unmounted');
});
</script>

<template>
  <div class="voice-call-overlay" :class="{ 'dark-mode': currentThemeMode === 'dark' }">
    
    <div class="glass-card-container">
      
      <div class="card-header-left">
        <div class="header-pill">
          <span class="recording-dot"></span>
          
          <CallTimer />
          
          <div class="separator"></div>

          <button @click="emit('minimize')" class="minimize-btn" title="Minimize">
            <Minimize2 :size="16" />
          </button>
        </div>
      </div>

      <div class="card-header-right">
        <div class="caller-pip">
          <CallAvatar 
            :photo-url="localPhoto" 
            :display-name="localName"
            :allow-auth-fallback="true"
            size="60px" 
            :is-calling="false"
          />
          <span class="pip-label"
          style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">You</span>
        </div>
      </div>

      <div class="card-body">
        <div class="visualizer-wrapper">
          <div 
            class="audio-ring ring-1"
            :style="{ transform: `scale(${1 + (simulatedVolume / 100) * 1.0})`, opacity: simulatedVolume > 10 ? 0.3 : 0.05 }"
          ></div>
          <div 
            class="audio-ring ring-2"
            :style="{ transform: `scale(${1 + (simulatedVolume / 100) * 0.6})`, opacity: simulatedVolume > 10 ? 0.5 : 0.1 }"
          ></div>

          <CallAvatar 
            :photo-url="props.remotePhoto" 
            :display-name="props.remoteName"
            size="140px" 
            :is-calling="false" 
          />
        </div>

        <div class="user-info">
          <h2 class="user-name">{{ props.remoteName }}</h2>
          <span class="connection-status"
          style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Connected</span>
        </div>
      </div>

      <div class="card-footer">
        <CallControls 
          call-type="voice"
          :is-muted="props.isMuted"
          :is-speaker-on="props.isSpeakerOn" 
          @toggle-mute="emit('toggleMute')"
          @toggle-speaker="emit('toggleSpeaker')"
          @end-call="emit('endCall')"
        />
      </div>

    </div> </div>
</template>

<style scoped>
/* --- LAYOUT UTAMA --- */
.voice-call-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  /* Latar belakang global (Wallpaper Desktop simulasi) */
  background: rgba(255, 255, 255, 0.270) !important; backdrop-filter: blur(1.5px);
  background-size: cover;
  z-index: 9999;
  display: flex;
  justify-content: center;
  align-items: center;
  /* Blur background agar fokus ke Card */
  backdrop-filter: blur(8px); 
}

/* --- GLASS CARD DESIGN --- */
.glass-card-container {
  position: relative;
  width: 90%;
  max-width: 850px;
  height: 80vh;
  max-height: 600px;
  
  /* Efek Kaca (Glassmorphism) */
  background: rgba(255, 255, 255, 0.600); /* Transparan */
  border: 1px solid rgba(255, 255, 255, 0.3); /* Border tipis putih */
  border-radius: 40px; /* Sudut sangat bulat sesuai sketsa */
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); /* Shadow dalam */
  backdrop-filter: blur(15px); /* Blur isi card */
  -webkit-backdrop-filter: blur(20px);
  
  display: flex;
  flex-direction: column;
  overflow: hidden; /* Agar anak elemen tidak keluar border radius */
  animation: popIn 0.3s ease-out;
}

.dark-mode .glass-card-container {
  background: rgba(105, 104, 104, 0.5);
}

@keyframes popIn {
  from { transform: scale(0.9); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

/* --- 1. HEADER KIRI (Timer + Minimize) --- */
.card-header-left {
  position: absolute;
  top: 25px;
  left: 30px;
  z-index: 20;
}

.header-pill {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(0, 0, 0, 0.2);
  padding: 6px 14px;
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.recording-dot {
  width: 8px;
  height: 8px;
  background-color: #ef4444; /* Merah */
  border-radius: 50%;
  animation: pulse-red 1.5s infinite;
}

.separator {
  width: 1px;
  height: 16px;
  background: rgba(255, 255, 255, 0.3);
}

.minimize-btn {
  background: rgba(105, 104, 104, 0.5);
  border: none;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 50%;
  transition: background 0.2s;
}

.minimize-btn:hover {
  background: rgba(12, 207, 221, 0.301);
}

.dark-mode .minimize-btn {
  background: rgba(255, 255, 255, 0.3);
}

.dark-mode .minimize-btn:hover {
  background: rgba(12, 207, 221, 0.301);
}

/* --- 2. HEADER KANAN (Caller Avatar) --- */
.card-header-right {
  position: absolute;
  top: 25px;
  right: 30px;
  z-index: 20;
}

.caller-pip {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: rgba(255, 255, 255, 0.15);
  padding: 6px;
  border-radius: 16px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.pip-label {
  font-size: 0.7rem;
  color: #0959ee;
  margin-top: 4px;
  font-weight: 500;
}

.dark-mode .pip-label {
  color: white;
}

/* --- 3. BODY (Center Avatar) --- */
.card-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  margin-top: -20px; /* Offset sedikit ke atas */
}

.visualizer-wrapper {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  width: 250px;
  height: 250px;
}

.audio-ring {
  position: absolute;
  border-radius: 50%;
  background-color: rgba(105, 104, 104, 0.5);
  z-index: 1;
  pointer-events: none;
  transition: transform 0.1s ease-out, opacity 0.1s ease;
}
.ring-1 { width: 180px; height: 180px; }
.ring-2 { width: 220px; height: 220px; background-color: rgba(105, 104, 104, 0.5); }

.dark-mode .audio-ring {
  background-color: rgba(255, 255, 255, 0.3);
}

.dark-mode .ring-2 {
  background-color: rgba(255, 255, 255, 0.3);
}

.user-info {
  text-align: center;
  color: white;
  margin-top: 10px;
  z-index: 10;
}

.user-name {
  font-size: 1.8rem;
  font-weight: 700;
  color: #0959ee;
  text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.dark-mode .user-name {
  color: #10a4fa;
}

.connection-status {
  font-size: 0.9rem;
  opacity: 0.8;
  background: rgba(105, 104, 104, 0.5);
  padding: 4px 10px;
  border-radius: 8px;
  margin-top: 5px;
  display: inline-block;
}

.dark-mode .connection-status {
  background: rgba(255,255,255,0.1)
}

/* --- 4. FOOTER (Controls) --- */
.card-footer {
  padding-bottom: 30px;
  display: flex;
  justify-content: center;
}

@keyframes pulse-red {
  0% { opacity: 1; }
  50% { opacity: 0.4; }
  100% { opacity: 1; }
}

/* --- RESPONSIVE MOBILE (Full Screen) --- */
@media (max-width: 600px) {
  .glass-card-container {
    width: 100%;
    height: 100%;        
    max-width: none;     
    max-height: none;    
    border-radius: 0;    
    border: none;       
    box-shadow: none;    
  }
  
  /* Penyesuaian jarak elemen agar tidak terlalu mepet tepi layar HP */
  .card-header-left { 
    top: 20px; 
    left: 20px; 
  }
  
  .card-header-right { 
    top: 20px; 
    right: 20px; 
  }
  
  /* Perkecil sedikit ukuran font dan avatar agar proporsional di HP */
  .user-name { 
    font-size: 1.6rem; 
  }
  
  .visualizer-wrapper {
    transform: scale(0.85); /* Perkecil sedikit visualizer & avatar utama */
  }

  .card-footer {
    padding-bottom: 40px; /* Beri jarak lebih untuk area jempol di bawah */
  }
}
</style>