<script setup lang="ts">
import { 
  Mic, 
  MicOff, 
  PhoneForwarded, 
  Volume2, 
  VolumeOff, 
  Camera,      // Import Icon Kamera
  CameraOff    // Import Icon Kamera Off
} from 'lucide-vue-next';

interface Props {
  isMuted: boolean;
  isSpeakerOn: boolean;
  isCameraOn?: boolean;
  callType: 'voice' | 'video';
}

const props = withDefaults(defineProps<Props>(), {
  isMuted: false,
  isSpeakerOn: false,
  isCameraOn: false, // Default mati untuk Voice Call
  callType: 'voice',
});

// Update emits
const emit = defineEmits([
  'toggleMute', 
  'toggleSpeaker', 
  'toggleCamera',
  'endCall', 
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
      v-if="props.callType === 'voice'"
      @click="emit('toggleSpeaker')" 
      class="control-btn" 
      :class="{ 'active': props.isSpeakerOn }"
      title="Speaker"
    >
      <component :is="props.isSpeakerOn ? Volume2 : VolumeOff" :size="24" />
    </button>

    <button 
      v-if="props.callType === 'video'"
      @click="emit('toggleCamera')" 
      class="control-btn"
      :class="{ 'active': !props.isCameraOn }"
      title="Toggle Camera"
    >
      <component :is="props.isCameraOn ? Camera : CameraOff" :size="24" />
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

.control-btn.active {
  background-color: #fff;
  color: #333;
}

.end-call-btn {
  background-color: #ff4d4d;
}

.end-call-btn:hover {
  background-color: #ff3333;
}

.rotate-icon {
  transform: rotate(135deg);
}

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