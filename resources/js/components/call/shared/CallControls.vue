<script setup lang="ts">
import { computed } from 'vue';
import { 
  Mic, 
  MicOff, 
  PhoneForwarded, 
  Volume2, 
  VolumeOff, 
  Camera,      
  CameraOff,
  PhoneOff     // Import Icon baru untuk End Call For All
} from 'lucide-vue-next';
import { themeMode } from "@/layouts/default-layout/config/helper"; 

interface Props {
  isMuted: boolean;
  isSpeakerOn: boolean;
  isCameraOn?: boolean;
  callType: 'voice' | 'video';
  isGroupCall?: boolean; // Penanda group call
  isHost?: boolean;      // Penanda jika user ini adalah host
}

const currentThemeMode = computed(() => themeMode.value);

const props = withDefaults(defineProps<Props>(), {
  isMuted: false,
  isSpeakerOn: false,
  isCameraOn: false, 
  callType: 'voice',
  isGroupCall: false,
  isHost: false,
});

// Update emits dengan event baru
const emit = defineEmits([
  'toggleMute', 
  'toggleSpeaker', 
  'toggleCamera',
  'endCall', 
  'endCallForAll' // Emit baru untuk bubarkan panggilan
]);
</script>

<template>
  <div class="call-controls-container" :class="{ 'dark-mode': currentThemeMode === 'dark' }">
    
    <button 
      @click="emit('toggleMute')" 
      class="control-btn" 
      :class="{ 'active': props.isMuted }"
      title="Mute/Unmute"
    >
      <component :is="props.isMuted ? MicOff : Mic" :size="24" />
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
      title="Leave Call"
    >
      <PhoneForwarded :size="24" />
    </button>

    <button 
      v-if="props.isGroupCall && props.isHost"
      @click="emit('endCallForAll')" 
      class="control-btn end-all-btn"
      title="End Call for All (Bubarkan)"
    >
      <PhoneOff :size="24" />
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
  background-color: rgba(105, 104, 104, 0.5);
  color: white;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  transition: all 0.3s ease;
}

.dark-mode .control-btn {
  background-color: rgba(255, 255, 255, 0.2);
}

.control-btn:hover {
  background-color: rgba(105, 104, 104, 0.8);
  transform: scale(1.05);
}

.control-btn.active {
  background-color: white;
  color: black;
}

/* Style tombol Leave Call biasa (Merah Standard) */
.end-call-btn {
  background-color: #ff3b30 !important;
  color: white !important;
  box-shadow: 0 4px 15px rgba(255, 59, 48, 0.4);
}
.end-call-btn:hover {
  background-color: #ff1f1f !important;
}

/* Style tombol Bubarkan (Merah Lebih Gelap/Tegas) */
.end-all-btn {
  background-color: #ba000d !important; 
  color: white !important;
  box-shadow: 0 4px 15px rgba(186, 0, 13, 0.4);
}
.end-all-btn:hover {
  background-color: #9a0007 !important;
}
</style>