<script setup lang="ts">
import { ref, computed } from 'vue';
import { themeMode } from "@/layouts/default-layout/config/helper"; 
import VoiceGrid from './VoiceGrid.vue';
import CallTimer from '../shared/CallTimer.vue';
import CallControls from '../shared/CallControls.vue';
import ParticipantUsers from '../shared/ParticipantUsers.vue';
import { Settings, UserPlus, XCircle } from 'lucide-vue-next'; 

import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from "@/stores/authStore"; 
import { useVoiceGroupCall } from '@/composables/useVoiceGroupCall'; 

const currentThemeMode = computed(() => themeMode.value); 

const callStore = useCallStore();
const authStore = useAuthStore();
const startTime = new Date();

// Destructure fungsi dari composable
const { leaveGroupVoiceCall, endGroupVoiceCallForAll, toggleMute, isAudioEnabled } = useVoiceGroupCall();

// Cek Host
const isUserHost = computed(() => {
    return authStore.user?.id === callStore.backendGroupCall?.host_id;
});

// Format participant
const formattedParticipants = computed(() => {
    return callStore.groupParticipants.map((p: any) => {
        const user = p.user || {};
        return {
            id: p.user_id || user.id || p.id,
            name: user.name || 'Unknown',
            avatar: user.photo || user.avatar || user.profile_photo_url || '',
            status: p.status || 'ringing',
            isSpeaking: false, 
            isMuted: false,    
        };
    });
});

// ACTION HANDLERS
const handleLeaveCall = async () => {
    const callId = callStore.currentCall?.id || callStore.backendGroupCall?.id;
    if (callId) {
        await leaveGroupVoiceCall(callId);
    }
};

const handleEndCallForAll = async () => {
    const callId = callStore.currentCall?.id || callStore.backendGroupCall?.id;
    if (callId) {
        await endGroupVoiceCallForAll(callId);
    }
};

const handleToggleMute = () => {
    toggleMute();
};

const handleToggleSpeaker = () => {
    console.log('Toggle Speaker clicked');
};
</script>

<template>
  <div class="voice-group-overlay" :class="{ 'dark-mode': currentThemeMode === 'dark' }">
    
    <div class="glass-card-fullscreen">
        
      <div class="absolute inset-0 z-0 pointer-events-none overflow-hidden">
          <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-primary/20 rounded-full blur-[120px]"></div>
          <div class="absolute bottom-[-10%] right-[-10%] w-[400px] h-[400px] bg-danger/10 rounded-full blur-[100px]"></div>
      </div>

      <header class="shrink-0 p-6 flex items-start justify-between relative z-10 w-full max-w-7xl mx-auto">
        <div class="flex items-center gap-4 bg-white/30 dark:bg-black/30 backdrop-blur-xl px-4 py-2.5 rounded-2xl border border-white/40 dark:border-white/10 shadow-sm">
          <div class="relative w-12 h-12 flex-shrink-0">
            <img :src="callStore.backendGroupCall?.group?.avatar || 'https://via.placeholder.com/150'" class="w-full h-full rounded-full object-cover shadow-md" alt="Group" />
            <div class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-success rounded-full border-2 border-white dark:border-gray-800"></div>
          </div>
          <div class="flex flex-col">
            <h1 class="text-lg font-bold text-gray-900 dark:text-white leading-tight drop-shadow-sm">{{ callStore.backendGroupCall?.group?.name || 'Group Call' }}</h1>
            <CallTimer :startTime="startTime" class="text-sm font-medium text-gray-700 dark:text-gray-300 drop-shadow-sm" />
          </div>
        </div>
        
        <!-- <div class="flex items-center gap-2 bg-white/30 dark:bg-black/30 backdrop-blur-xl px-4 py-2 rounded-full border border-white/40 dark:border-white/10 shadow-sm">
          <button class="w-10 h-10 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-200 hover:text-primary hover:bg-white/50 dark:hover:bg-black/50 transition-all">
            <Settings :size="20" />
          </button>
          <button class="w-10 h-10 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-200 hover:text-primary hover:bg-white/50 dark:hover:bg-black/50 transition-all">
            <UserPlus :size="20" />
          </button>
          <div class="w-px h-6 bg-black/20 dark:bg-white/20 mx-1"></div>
          <button class="w-10 h-10 rounded-full flex items-center justify-center text-white bg-danger hover:bg-danger/80 transition-all duration-300 shadow-md" @click="handleLeaveCall" title="Minimize / Leave">
            <XCircle :size="20" />
          </button>
        </div> -->
      </header>

      <main class="flex-1 flex flex-col w-full px-6 overflow-hidden relative z-10">
        <div class="relative z-10 flex-1 w-full pb-4 overflow-y-auto custom-scrollbar flex items-center justify-center">
          <div class="w-full max-w-7xl h-full flex flex-col items-center justify-center">
             <VoiceGrid :participants="formattedParticipants" class="flex-1 w-full" />
          </div>
        </div>
      </main>

      <footer class="pb-10 pt-4 flex items-center justify-center shrink-0 relative z-10 w-full">
          <div class="bg-white/40 dark:bg-black/50 backdrop-blur-2xl border border-white/50 dark:border-white/10 rounded-[2rem] px-8 py-4 shadow-2xl flex items-center justify-center">
            <ParticipantUsers :participants="formattedParticipants" class="flex justify-center gap-2" />
            <CallControls 
                :is-muted="!isAudioEnabled" 
                :is-speaker-on="false"
                call-type="voice"
                :is-group-call="true"
                :is-host="isUserHost"
                @toggle-mute="handleToggleMute"
                @toggle-speaker="handleToggleSpeaker"
                @end-call="handleLeaveCall"
                @end-call-for-all="handleEndCallForAll"
            />
          </div>
      </footer>

    </div>
  </div>
</template>

<style scoped>
.voice-group-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(255, 255, 255, 0.15) !important; 
  backdrop-filter: blur(10px);
  z-index: 99999;
  display: flex;
  justify-content: center;
  align-items: center;
}

.glass-card-fullscreen {
  position: relative;
  width: 100%;
  height: 100%;
  
  /* Latar lebih transparan sedikit agar blur dari overlay terasa */
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.3));
  backdrop-filter: blur(25px); 
  -webkit-backdrop-filter: blur(25px);
  
  display: flex;
  flex-direction: column;
  overflow: hidden; 
  animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.dark-mode .voice-group-overlay {
  background: rgba(0, 0, 0, 0.3) !important;
}

.dark-mode .glass-card-fullscreen {
  background: linear-gradient(135deg, rgba(30, 30, 30, 0.7), rgba(15, 15, 15, 0.5));
}

@keyframes fadeIn {
  from { opacity: 0; transform: scale(1.02); }
  to { opacity: 1; transform: scale(1); }
}

/* Scrollbar Customization */
.custom-scrollbar { scrollbar-width: thin; scrollbar-color: rgba(0, 0, 0, 0.15) transparent; }
.dark-mode .custom-scrollbar { scrollbar-color: rgba(255, 255, 255, 0.15) transparent; }
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(0, 0, 0, 0.15); border-radius: 10px; }
.dark-mode .custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.15); }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(0, 0, 0, 0.25); }
.dark-mode .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.25); }
</style>