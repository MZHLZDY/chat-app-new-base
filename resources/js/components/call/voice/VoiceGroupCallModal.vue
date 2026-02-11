<script setup lang="ts">
import { ref, defineAsyncComponent } from 'vue';
import VoiceGrid from './VoiceGrid.vue';
import CallTimer from '../shared/CallTimer.vue';
import CallControls from '../shared/CallControls.vue';
import ParticipantUsers from '../shared/ParticipantUsers.vue'; // Asumsi file ini sudah ada dari langkah sebelumnya
import { Settings, UserPlus, Shield } from 'lucide-vue-next'; // Icon untuk Host Features

// --- DUMMY DATA (Nanti diganti data dari Store/Composable) ---
const startTime = new Date();
// const participants = ref([
//     { id: 1, name: 'Anda (Host)', avatar: null, isSpeaking: false, isMuted: false, status: 'joined' },
//     { id: 2, name: 'Budi Santoso', avatar: null, isSpeaking: true, isMuted: false, status: 'joined' }, // Sedang bicara
//     { id: 3, name: 'Siti Aminah', avatar: null, isSpeaking: false, isMuted: true, status: 'joined' },  // Muted
//     { id: 4, name: 'Joko Anwar', avatar: null, isSpeaking: false, isMuted: false, status: 'ringing' }, // Belum angkat
//     { id: 5, name: 'Rina Nose', avatar: null, isSpeaking: false, isMuted: false, status: 'joined' },
//     { id: 6, name: 'Dedi Corbuzier', avatar: null, isSpeaking: false, isMuted: false, status: 'declined' },
//     { id: 7, name: 'Participants 7', avatar: null, isSpeaking: false, isMuted: false, status: 'joined' },
//     { id: 8, name: 'Participants 8', avatar: null, isSpeaking: false, isMuted: false, status: 'joined' },
//     { id: 9, name: 'Participants 9', avatar: null, isSpeaking: false, isMuted: false, status: 'joined' },
//     { id: 10, name: 'Participants 10', avatar: null, isSpeaking: false, isMuted: false, status: 'joined' },
// ]);

// Actions
const handleEndCall = () => console.log('End Call Clicked');
const toggleMute = () => console.log('Mute Clicked');
const toggleSpeaker = () => console.log('Speaker Clicked');

</script>

<template>
  <div class="fixed inset-0 z-50 h-screen w-screen flex flex-col bg-gray-900 overflow-hidden font-sans text-white">
    
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-blue-600/20 rounded-full blur-[100px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-purple-600/20 rounded-full blur-[100px]"></div>
    </div>

    <div class="relative z-10 flex-none h-20 px-6 flex items-center justify-between border-b border-white/5 bg-white/5 backdrop-blur-md">
      <div class="flex flex-col">
        <h2 class="text-lg font-bold tracking-wide">Tim Development</h2>
        <span class="text-xs text-gray-400">Weekly Sync • Voice Channel</span>
      </div>

      <div class="bg-black/30 rounded-full px-1">
          <CallTimer :startTime="startTime" />
      </div>
    </div>

    <div class="relative z-10 flex-1 overflow-y-auto custom-scrollbar p-4 md:p-8">
      <div class="max-w-7xl mx-auto">
          <!-- <VoiceGrid /> -->
       </div>
    </div>

    <div class="relative z-10 flex-none bg-black/40 backdrop-blur-xl border-t border-white/10 px-6 py-4">
      <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
        
        <div class="w-full md:w-1/3 flex justify-center md:justify-start order-2 md:order-1">
             <div class="scale-90 origin-left">
                <!-- <ParticipantUsers /> -->
            </div>
        </div>

        <div class="w-full md:w-1/3 flex justify-center order-1 md:order-2">
           <CallControls 
             :isMuted="false"
             :isSpeakerOn="true"
             callType="voice"
             @toggleMute="toggleMute"
             @toggleSpeaker="toggleSpeaker"
             @endCall="handleEndCall"
           />
        </div>

        <div class="w-full md:w-1/3 flex justify-center md:justify-end gap-3 order-3">
             <button class="host-btn" title="Settings">
                <Settings :size="20" />
             </button>
             <button class="host-btn" title="Invite User">
                <UserPlus :size="20" />
             </button>
             <button class="host-btn bg-red-500/20 text-red-400 border-red-500/50 hover:bg-red-500/40" title="Mute All">
                <Shield :size="20" />
                <span class="ml-2 text-sm hidden lg:block">Mute All</span>
             </button>
        </div>

      </div>
    </div>

  </div>
</template>

<style scoped>
/* Scrollbar Customization agar terlihat elegan di mode gelap */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.05);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.4);
}

/* Styling Tombol Host */
.host-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: white;
    transition: all 0.2s;
}
.host-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}
</style>