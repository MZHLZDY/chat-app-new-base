<script setup lang="ts">
import { computed } from 'vue';
import CallAvatar from '../shared/CallAvatar.vue';
import { MicOff, Wifi } from 'lucide-vue-next';

interface Participant {
  id: number;
  name: string;
  avatar?: string;
  isSpeaking: boolean;
  isMuted: boolean;
  status: 'joined' | 'ringing' | 'declined' | 'left';
}

const props = defineProps<{
  participants: Participant[];
}>();

// Filter hanya user yang statusnya 'joined' untuk ditampilkan di Grid utama
// (User yang 'ringing' atau 'declined' statusnya dilihat di ParticipantUsers.vue di footer)
const activeParticipants = computed(() => {
    return props.participants.filter(p => p.status === 'joined');
});

</script>

<template>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
    
    <div 
      v-for="user in activeParticipants" 
      :key="user.id"
      class="relative group"
    >
      <div 
        class="aspect-square rounded-2xl bg-white/5 border border-white/10 backdrop-blur-sm flex flex-col items-center justify-center p-4 transition-all duration-300"
        :class="{ 
            'border-green-500/50 bg-green-500/10 shadow-[0_0_15px_rgba(34,197,94,0.3)]': user.isSpeaking,
            'hover:bg-white/10': !user.isSpeaking 
        }"
      >
        
        <div class="relative">
             <CallAvatar 
                :photoUrl="user.avatar" 
                :displayName="user.name"
                :isCalling="user.isSpeaking"
                size="80px"  
                pulseColor="rgba(34, 197, 94, 0.6)"
             />
             
             <div v-if="user.isMuted" class="absolute bottom-0 right-0 bg-red-500/90 p-1.5 rounded-full shadow-lg">
                <MicOff :size="14" class="text-white" />
             </div>
        </div>

        <div class="mt-4 text-center w-full">
            <h3 class="text-sm font-semibold text-white truncate px-2">{{ user.name }}</h3>
             <p v-if="user.isSpeaking" class="text-[10px] text-green-400 animate-pulse font-medium">Speaking...</p>
        </div>

        <div class="absolute top-3 right-3 opacity-50">
             <Wifi :size="16" class="text-white" />
        </div>

      </div>

    </div>
  </div>
</template>