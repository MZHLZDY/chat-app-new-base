<script setup lang="ts">
import { ref, onMounted, onUnmounted, watchEffect } from 'vue';
import CallAvatar from '../shared/CallAvatar.vue';
import CallControls from '../shared/CallControls.vue';
import CallTimer from '../shared/CallTimer.vue';

interface Props {
  remoteName: string;
  remotePhoto: string;
  isMuted: boolean;
  isSpeakerOn: boolean;
  
  // Tingkat volume audio (0 - 100)
  // Nanti nilai ini didapat dari useAgora/WebRTC analysis
  volumeLevel?: number; 
}

const props = withDefaults(defineProps<Props>(), {
  volumeLevel: 0, // Default hening
});

const emit = defineEmits([
  'toggleMute', 
  'toggleSpeaker', 
  'endCall', 
  'switchCallType'
]);

// --- SIMULASI AUDIO (Hanya untuk Demo UI) ---
// Di real app, hapus bagian ini dan gunakan props.volumeLevel dari backend/SDK
const simulatedVolume = ref(0);
let audioInterval: number | null = null;

const startSimulation = () => {
  audioInterval = window.setInterval(() => {
    // Membuat nilai acak antara 0 - 60 untuk simulasi orang bicara
    const randomVol = Math.random() > 0.3 ? Math.random() * 60 : 5; 
    simulatedVolume.value = randomVol;
  }, 100);
};

onMounted(() => {
  startSimulation();
});

onUnmounted(() => {
  if (audioInterval) clearInterval(audioInterval);
});
// ---------------------------------------------
</script>

<template>
  <div class="voice-call-overlay">
    <div class="glass-card">
      
      <div class="header-section">
        <CallTimer />
      </div>

      <div class="main-visual">
        <div 
          class="audio-ring ring-1"
          :style="{ transform: `scale(${1 + (simulatedVolume / 100) * 1.5})`, opacity: simulatedVolume > 10 ? 0.4 : 0.1 }"
        ></div>
        
        <div 
          class="audio-ring ring-2"
          :style="{ transform: `scale(${1 + (simulatedVolume / 100) * 1.0})`, opacity: simulatedVolume > 10 ? 0.6 : 0.1 }"
        ></div>

        <div class="avatar-z-index">
          <CallAvatar 
            :photo-url="props.remotePhoto" 
            :display-name="props.remoteName"
            size="150px" 
            :is-calling="false" 
          />
        </div>
      </div>

      <div class="info-section">
        <h2 class="remote-name">{{ props.remoteName }}</h2>
        <p class="call-status">00:12 • Connected</p>
      </div>

      <div class="controls-section">
        <CallControls 
          :is-muted="props.isMuted"
          :is-speaker-on="props.isSpeakerOn"
          :is-voice-call="true" 
          @toggle-mute="emit('toggleMute')"
          @toggle-speaker="emit('toggleSpeaker')"
          @switch-call-type="emit('switchCallType')"
          @end-call="emit('endCall')"
        />
      </div>

    </div>
  </div>
</template>

<style scoped>
.voice-call-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  /* Gradient background konsisten */
  background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.glass-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  
  width: 100%;
  max-width: 400px;
  height: 80vh; /* Lebih tinggi untuk proporsi voice call */
  max-height: 700px;
  
  padding: 40px 20px;
  
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-radius: 30px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

/* Header */
.header-section {
  margin-bottom: 20px;
}

/* Main Visual (Avatar + Audio Rings) */
.main-visual {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  width: 250px;
  height: 250px;
}

/* Layering agar Avatar tetap di paling atas, ring di belakang */
.avatar-z-index {
  position: relative;
  z-index: 10;
}

/* Logic Audio Rings */
.audio-ring {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%); /* Center absolute */
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.3);
  transition: transform 0.1s ease-out, opacity 0.2s ease; /* Transisi cepat agar responsif suara */
  z-index: 1;
}

/* Ring 1 (Outer/Larger) */
.ring-1 {
  width: 150px;
  height: 150px;
  /* Style transform dikontrol via inline style di template */
}

/* Ring 2 (Inner/Smaller) */
.ring-2 {
  width: 150px;
  height: 150px;
  background-color: rgba(255, 255, 255, 0.5);
}

/* User Info */
.info-section {
  text-align: center;
  color: white;
  margin-top: -20px; /* Tarik sedikit ke atas mendekati avatar */
}

.remote-name {
  font-size: 1.8rem;
  font-weight: 700;
  margin-bottom: 5px;
  text-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.call-status {
  font-size: 0.9rem;
  opacity: 0.7;
}

/* Controls Wrapper */
.controls-section {
  width: 100%;
  display: flex;
  justify-content: center;
}

/* Responsivitas Mobile */
@media (max-width: 480px) {
  .glass-card {
    height: 100vh;
    border-radius: 0;
    max-width: 100%;
    border: none;
    background: rgba(20, 30, 60, 0.6); /* Sedikit lebih gelap di mobile */
  }
}
</style>