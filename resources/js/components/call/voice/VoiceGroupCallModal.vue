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
const { leaveGroupVoiceCall, endGroupVoiceCallForAll, handleGroupParticipantRecalled, toggleMute, isAudioEnabled } = useVoiceGroupCall();

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
    
    <div class="glass-card-fullscreen flex flex-col justify-between">
        
      <div class="absolute inset-0 z-0 pointer-events-none overflow-hidden">
          <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-primary/20 rounded-full blur-[120px]"></div>
          <div class="absolute bottom-[-10%] right-[-10%] w-[400px] h-[400px] bg-danger/10 rounded-full blur-[100px]"></div>
      </div>

      <div class="w-full flex justify-center pt-8 z-10 relative">
        <center>
          <div class="relative w-12 h-12 flex-shrink-0">
            <div class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-success rounded-full border-2 border-white dark:border-gray-800"></div>
          </div>
          <div class="flex flex-col text-left">
            <h1 class="text-lg font-bold text-gray-900 dark:text-white leading-tight drop-shadow-sm">
              {{ callStore.backendGroupCall?.group?.name || 'Group Call' }}
            </h1>
            <CallTimer :startTime="startTime" class="text-sm font-medium text-gray-700 dark:text-gray-300 drop-shadow-sm" />
          </div>
        </center>
      </div>

      <div class="flex-1 overflow-y-auto custom-scrollbar relative">
        <div class="min-h-full flex flex-col p-4 sm:p-6">
          <div class="flex-1 flex items-start w-full">
      
            <VoiceGrid 
             class="w-full my-auto custom-voice-grid" 
             :participants="formattedParticipants" 
             @recall="handleGroupParticipantRecalled" 
            />
      
            
          </div>
        </div>
      </div>

      <div class="w-full h-24 bg-gray-900/95 backdrop-blur-md border-t border-gray-800 flex items-center justify-between px-8 z-50 relative shrink-0">
        
        <div class="w-1/3 flex justify-start">
           <div class="w-full max-w-[320px]">
             <ParticipantUsers :participants="formattedParticipants" />
           </div>
        </div>

        <div class="w-1/3 flex justify-center">
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

        <div class="w-1/3"></div>

      </div>

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

/* 1. Styling elemen root dari VoiceGrid */
.custom-voice-grid {
  max-width: 200px; /* Batasi lebar maksimal grid */
  margin-left: auto;
  margin-right: auto;
  /* Tambahkan style lain khusus untuk container luarnya */
}

/* 2. Styling elemen ANAK di dalam VoiceGrid (Gunakan :deep) */
/* Kalau kamu mau ubah ukuran kotak avatar dari luar tanpa menyentuh file VoiceGrid.vue */
.custom-voice-grid :deep(.aspect-square) {
  border-radius: 2rem !important; /* Paksa ubah border radius */
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
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