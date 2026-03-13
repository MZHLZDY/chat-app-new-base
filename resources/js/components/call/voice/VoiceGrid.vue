<script setup lang="ts">
import { computed } from 'vue';
import CallAvatar from '../shared/CallAvatar.vue';
import { MicOff, PhoneCall } from 'lucide-vue-next'; 

interface Participant {
  id: number;
  name: string;
  avatar?: string;
  isSpeaking: boolean;
  isMuted: boolean;
  status: 'joined' | 'ringing' | 'declined' | 'left' | 'missed'; 
}

const props = defineProps<{
  participants: Participant[];
}>();

const emit = defineEmits(['recall']);

const displayParticipants = computed(() => {
    return props.participants.filter(p => ['joined', 'ringing', 'left', 'declined', 'missed'].includes(p.status));
});
</script>

<template>
  <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 w-full mx-auto">
    
    <div 
      v-for="user in displayParticipants" 
      :key="user.id"
      class="relative group transition-all duration-300 w-44 h-44 sm:w-56 sm:h-56 flex-shrink-0" 
      :class="{ 'opacity-60 scale-[0.97]': user.status !== 'joined' }"
    >
      
      <div class="w-full h-full rounded-[1.5rem] sm:rounded-[2rem] bg-white/10 dark:bg-black/20 border border-white/20 dark:border-white/10 backdrop-blur-md shadow-lg flex flex-col items-center justify-center p-3 transition-colors hover:bg-white/20 dark:hover:bg-black/40">
       
        <div class="relative mb-3">
          <center>
             <CallAvatar 
                :photoUrl="user.avatar" 
                :displayName="user.name"
                :isCalling="user.isSpeaking && user.status === 'joined'"
                size="70px"  
                pulseColor="rgba(34, 197, 94, 0.6)"
             />
          </center>
          <div v-if="user.isMuted && user.status === 'joined'" class="absolute bottom-0 right-0 bg-red-500 p-1.5 rounded-full shadow-lg">
             <MicOff :size="14" class="text-white" />
          </div>
        </div>

        <div class="text-center w-full z-10 mt-1">
          <p class="text-sm sm:text-base font-bold text-gray-900 dark:text-white truncate w-full px-2 drop-shadow-sm">{{ user.name }}</p>
          <p class="text-[10px] sm:text-xs font-semibold mt-1 tracking-wider uppercase" 
             :class="[
                user.status === 'joined' ? 'text-success' : '',
                user.status === 'ringing' ? 'text-warning' : '',
                ['left', 'declined', 'missed'].includes(user.status) ? 'text-danger' : ''
             ]">
             {{ user.status }}
          </p>
        </div>

        <div v-if="user.status !== 'joined'" class="absolute inset-0 flex items-center justify-center bg-black/40 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-20 rounded-[1.5rem] sm:rounded-[2rem]">
            <button
              @click.stop="emit('recall', user.id)"
              class="flex flex-col items-center justify-center gap-1 p-2 bg-white/20 hover:bg-white/30 backdrop-blur-md rounded-xl text-white shadow-xl transition-transform transform hover:scale-105"
              title="Recall Participant"
            >
              <div class="bg-primary p-2 rounded-full shadow-md">
                 <PhoneCall :size="18" class="text-white" />
              </div>
              <span class="text-[10px] font-medium tracking-wide">Recall</span>
            </button>
        </div>

      </div>
    </div>

  </div>
</template>

<style scoped>
/* Target langsung class bawaan dari Tailwind yang ada di template */
.aspect-square {
  max-width: 200px; /* Batasi maksimal lebarnya di SINI, bukan di grid utamanya */
  margin: 0 auto; /* Pastikan selalu di tengah */
  border-radius: 2rem !important; /* Memaksa border-radius */
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
}
</style>