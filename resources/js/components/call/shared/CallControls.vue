<script setup lang="ts">
import { 
  Mic, 
  MicOff, 
  PhoneForwarded, 
  Volume2, 
  VolumeOff, 
  Video, 
  Phone 
} from 'lucide-vue-next';

interface Props {
  isMuted: boolean;
  isSpeakerOn: boolean;
  isVoiceCall: boolean; // True jika sedang voice call, False jika video call
}

const props = defineProps<Props>();

// Mendefinisikan emit agar parent bisa menangani logika fungsinya
const emit = defineEmits([
  'toggleMute', 
  'toggleSpeaker', 
  'endCall', 
  'switchCallType'
]);
</script>

<template>
  <div class="call-controls-container">
    
    <button 
      @click="emit('toggleMute')" 
      class="control-btn" 
      :class="{ 'active': props.isMuted }"
      title="Mute/Unmute"
    >
      <component :is="props.isMuted ? MicOff : Mic" :size="24" />
    </button>

    <button 
      @click="emit('toggleSpeaker')" 
      class="control-btn" 
      :class="{ 'active': props.isSpeakerOn }"
      title="Speaker"
    >
      <component :is="props.isSpeakerOn ? Volume2 : VolumeOff" :size="24" />
    </button>

    <button 
      @click="emit('switchCallType')" 
      class="control-btn"
      title="Switch Call Type"
    >
      <component :is="props.isVoiceCall ? Video : Phone" :size="24" />
    </button>

    <button 
      @click="emit('endCall')" 
      class="control-btn end-call-btn"
      title="End Call"
    >
      <PhoneForwarded :size="24" class="rotate-icon" />
    </button>

  </div>
</template>

<style scoped>
.call-controls-container {
  display: flex;
  gap: 20px;
  justify-content: center;
  align-items: center;
  padding: 20px;
  background-color: rgba(0, 0, 0, 0.2);
  border-radius: 50px;
  backdrop-filter: blur(10px);
  width: fit-content;
  margin: 0 auto;
}

/* Base Button Style */
.control-btn {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  border: none;
  background-color: rgba(255, 255, 255, 0.2);
  color: white;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  transition: all 0.3s ease;
}

.control-btn:hover {
  background-color: rgba(255, 255, 255, 0.3);
  transform: scale(1.05);
}

/* Active State (misal saat Mute On) */
.control-btn.active {
  background-color: #fff;
  color: #333;
}

/* End Call Button Specific (Red) */
.end-call-btn {
  background-color: #ff4d4d;
}

.end-call-btn:hover {
  background-color: #ff3333;
}

/* Rotasi sedikit ikon PhoneForwarded agar terlihat seperti gagang telepon ditutup */
.rotate-icon {
  transform: rotate(135deg);
}

/* Responsivitas kecil */
@media (max-width: 480px) {
  .call-controls-container {
    gap: 12px;
    padding: 15px;
  }
  .control-btn {
    width: 45px;
    height: 45px;
  }
}
</style>