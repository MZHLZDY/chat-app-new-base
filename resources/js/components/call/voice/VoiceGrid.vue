<script setup lang="ts">
import { computed } from 'vue';
import CallAvatar from '../shared/CallAvatar.vue';
import { MicOff, PhoneCall } from 'lucide-vue-next'; // Tambah PhoneCall

interface Participant {
  id: number;
  name: string;
  avatar?: string;
  isSpeaking: boolean;
  isMuted: boolean;
  status: 'joined' | 'ringing' | 'declined' | 'left' | 'missed'; // Pastikan ada missed
}

const props = defineProps<{
  participants: Participant[];
}>();

// Emit event 'recall' ke parent dengan membawa user.id
const emit = defineEmits(['recall']);

// Tampilkan peserta yang bergabung, keluar, atau menolak.
// (Yang berstatus 'ringing' saat awal dipanggil mungkin disembunyikan atau ditampilkan, sesuaikan kebutuhan)
const displayParticipants = computed(() => {
    return props.participants.filter(p => ['joined', 'left', 'declined', 'missed'].includes(p.status));
});
</script>

<template>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
    
    <div 
      v-for="user in displayParticipants" 
      :key="user.id"
      class="relative group"
      :class="{ 'opacity-60 grayscale': user.status !== 'joined' }"
    >
      <div 
        class="aspect-square rounded-2xl bg-white/5 border border-white/10 backdrop-blur-sm flex flex-col items-center justify-center p-4 transition-all duration-300 relative"
        :class="{ 
            'border-green-500/50 bg-green-500/10 shadow-[0_0_15px_rgba(34,197,94,0.3)]': user.isSpeaking && user.status === 'joined',
            'hover:bg-white/10': !user.isSpeaking && user.status === 'joined'
        }"
      >
        
        <div class="relative">
             <CallAvatar 
                :photoUrl="user.avatar" 
                :displayName="user.name"
                :isCalling="user.isSpeaking && user.status === 'joined'"
                size="80px"  
                pulseColor="rgba(34, 197, 94, 0.6)"
             />
             
             <div v-if="user.isMuted && user.status === 'joined'" class="absolute bottom-0 right-0 bg-red-500/90 p-1.5 rounded-full shadow-lg">
                <MicOff :size="14" class="text-white" />
             </div>
        </div>

        <button
          v-if="user.status !== 'joined'"
          @click.stop="emit('recall', user.id)"
          class="absolute top-3 right-3 p-2 bg-blue-500 rounded-full text-white shadow-lg hover:bg-blue-600 hover:scale-110 transition-all duration-200 z-10"
          title="Recall Participant"
        >
          <PhoneCall :size="16" />
        </button>

        <div class="mt-4 text-center">
          <p class="text-sm font-medium text-white/90 truncate w-full px-2">{{ user.name }}</p>
          <p class="text-xs mt-1 capitalize" :class="user.status === 'joined' ? 'text-green-400' : 'text-red-400'">
             {{ user.status === 'joined' ? (user.isSpeaking ? 'Speaking' : 'Joined') : user.status }}
          </p>
        </div>
        
      </div>
    </div>

  </div>
</template>

<style scoped>
/* Transisi mulus untuk efek redup */
.group {
  transition: all 0.3s ease;
}
</style>