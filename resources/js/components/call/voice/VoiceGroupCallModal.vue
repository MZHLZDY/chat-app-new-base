<script setup lang="ts">
import { ref, computed } from 'vue';
import { themeMode } from "@/layouts/default-layout/config/helper"; 

// VoiceGrid.vue sudah TIDAK dipakai, komponennya langsung kita import ke sini
import CallTimer from '../shared/CallTimer.vue';
import CallControls from '../shared/CallControls.vue';
import ParticipantUsers from '../shared/ParticipantUsers.vue';
import CallAvatar from '../shared/CallAvatar.vue'; 
import { Settings, UserPlus, XCircle, MicOff, PhoneCall } from 'lucide-vue-next'; 

import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from "@/stores/authStore"; 
import { useVoiceGroupCall } from '@/composables/useVoiceGroupCall'; 

const currentThemeMode = computed(() => themeMode.value); 

const callStore = useCallStore();
const authStore = useAuthStore();
const startTime = new Date();

// Destructure fungsi dari composable
const { leaveGroupVoiceCall, 
        endGroupVoiceCallForAll, 
        toggleMute, 
        handleGroupParticipantRecalled, 
        isAudioEnabled 
} = useVoiceGroupCall();
 
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
            // Cek p.name (dari Firebase) juga sebagai fallback
            name: user.name || p.name || 'Unknown',
            avatar: user.photo || user.avatar || user.profile_photo_url || p.photo || '',
            status: p.status || 'ringing',
            isSpeaking: false, 
            isMuted: false,    
        };
    });
});

// Logic dari VoiceGrid.vue dipindah ke sini
const displayParticipants = computed(() => {
    return formattedParticipants.value.filter((p: any) => 
        ['joined', 'ringing', 'left', 'declined', 'missed'].includes(p.status)
    );
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

      <div class="flex-grow-1 w-100 d-flex align-items-center justify-content-center p-6 overflow-y-auto custom-scrollbar relative z-10">
        
        <div class="d-flex flex-row flex-wrap justify-content-center align-items-center w-100 mx-auto" style="gap: 1.5rem; max-width: 1000px;">
            
            <div 
              v-for="user in displayParticipants" 
              :key="user.id"
              class="position-relative transition-all duration-300"
              style="width: 200px; height: 200px; flex: 0 0 200px;" 
              :class="{ 'opacity-60 scale-[0.97]': user.status !== 'joined' }"
            >
              
              <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 shadow-lg" style="border-radius: 2rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
               
                <div class="position-relative mb-3 d-flex align-items-center justify-content-center">
                   <CallAvatar 
                      :photoUrl="user.avatar" 
                      :displayName="user.name"
                      :isCalling="user.isSpeaking && user.status === 'joined'"
                      size="75px"  
                      pulseColor="rgba(34, 197, 94, 0.6)"
                   />
                   <div v-if="user.isMuted && user.status === 'joined'" class="position-absolute bottom-0 end-0 bg-danger p-1.5 rounded-circle shadow-lg">
                      <MicOff :size="14" class="text-white" />
                   </div>
                </div>

                <div class="text-center w-100 z-10 mt-1">
                  <p class="fs-6 fw-bold user-text text-truncate w-100 px-2 m-0 drop-shadow-sm">{{ user.name }}</p>
                  <p class="fs-7 fw-semibold mt-1 tracking-wider text-uppercase m-0" 
                     :class="[
                        user.status === 'joined' ? 'text-success' : '',
                        user.status === 'ringing' ? 'text-warning' : '',
                        ['left', 'declined', 'missed'].includes(user.status) ? 'text-danger' : ''
                     ]">
                     {{ user.status }}
                  </p>
                </div>

                <div v-if="user.status !== 'joined'" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-20" style="backdrop-filter: blur(2px); border-radius: 2rem;">
                    <button
                      @click.stop="handleGroupParticipantRecalled(user.id)"
                      class="d-flex flex-column align-items-center justify-content-center gap-1 p-2 bg-white/20 hover:bg-white/30 rounded shadow-lg text-white border-0"
                      style="backdrop-filter: blur(10px); transition: transform 0.2s;"
                      title="Recall Participant"
                      onmouseover="this.style.transform='scale(1.05)'"
                      onmouseout="this.style.transform='scale(1)'"
                    >
                      <div class="bg-primary p-2 rounded-circle shadow-sm">
                         <PhoneCall :size="18" class="text-white" />
                      </div>
                      <span class="font-medium tracking-wide" style="font-size: 10px;">Recall</span>
                    </button>
                </div>

              </div>
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

.user-text {
  color: rgb(73, 26, 245);
}

.dark-mode .voice-group-overlay {
  background: rgba(0, 0, 0, 0.3) !important;
}

.dark-mode .glass-card-fullscreen {
  background: linear-gradient(135deg, rgba(30, 30, 30, 0.7), rgba(15, 15, 15, 0.5));
}

.dark-mode .user-text {
  color: rgb(26, 157, 245);
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