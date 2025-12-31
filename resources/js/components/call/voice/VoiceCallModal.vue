<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Minimize2 } from 'lucide-vue-next'; 
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
  // 'toggleCamera', // Event baru diteruskan ke parent
  'endCall', 
  'minimize' 
]);

// State Lokal untuk UI Tombol Kamera
// Kita set false karena default Voice Call kamera mati
const isCameraOn = ref(false);

// const handleToggleCamera = () => {
//   isCameraOn.value = !isCameraOn.value;
//   // Emit ke Parent (dimana useVoiceCall/useAgora berada) untuk eksekusi logika SDK
//   emit('toggleCamera', isCameraOn.value); 
// };

// --- SIMULASI AUDIO (Visualizer) ---
const simulatedVolume = ref(0);
let audioInterval: number | null = null;
const startSimulation = () => {
  audioInterval = window.setInterval(() => {
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
</script>

<template>
  <div class="voice-call-overlay">
    <div class="glass-card">
      
      <div class="header-section">
        <button @click="emit('minimize')" class="minimize-btn" title="Minimize Call">
          <Minimize2 :size="24" color="white" />
        </button>
        <CallTimer />
        <div class="spacer-right"></div>
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
        <p class="call-status">Connected</p>
      </div>

      <div class="controls-section">
        <CallControls 
          :is-muted="props.isMuted"
          :is-speaker-on="props.isSpeakerOn"
          :is-camera-on="isCameraOn" 
          @toggle-mute="emit('toggleMute')"
          @toggle-speaker="emit('toggleSpeaker')"
          @end-call="emit('endCall')"
        />
      </div>

    </div>
  </div>
</template>

<style scoped>
/* Style tetap sama seperti sebelumnya */
.voice-call-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
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
  height: 80vh;
  max-height: 700px;
  padding: 30px 20px;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-radius: 30px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.header-section {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.minimize-btn {
  background: rgba(255, 255, 255, 0.15);
  border: none;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  transition: all 0.2s ease;
}
.minimize-btn:hover { background: rgba(255, 255, 255, 0.3); transform: scale(1.1); }
.spacer-right { width: 40px; height: 40px; }

.main-visual {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  width: 250px;
  height: 250px;
}
.avatar-z-index { position: relative; z-index: 10; }
.audio-ring {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.3);
  transition: transform 0.1s ease-out, opacity 0.2s ease;
  z-index: 1;
}
.ring-1 { width: 150px; height: 150px; }
.ring-2 { width: 150px; height: 150px; background-color: rgba(255, 255, 255, 0.5); }

.info-section { text-align: center; color: white; margin-top: -20px; }
.remote-name { font-size: 1.8rem; font-weight: 700; margin-bottom: 5px; text-shadow: 0 2px 5px rgba(0,0,0,0.2); }
.call-status { font-size: 0.9rem; opacity: 0.7; }
.controls-section { width: 100%; display: flex; justify-content: center; }

@media (max-width: 480px) {
  .glass-card { height: 100vh; border-radius: 0; max-width: 100%; border: none; background: rgba(20, 30, 60, 0.6); }
}
</style>