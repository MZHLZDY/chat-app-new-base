<script setup lang="ts">
import { computed } from 'vue';

// Definisikan Tipe Data User untuk TypeScript
interface CallParticipant {
  id: number;
  name: string;
  avatar?: string;
  status: 'joined' | 'ringing' | 'declined' | 'left'; // Status kunci
}

// Menerima Props berupa Array semua peserta
const props = defineProps<{
  participants: CallParticipant[]
}>();

// ------------------------------------------
// LOGIC: Filter user berdasarkan status
// ------------------------------------------

// 1. KOTAK HIJAU: Yang sedang join
const joinedUsers = computed(() => {
  return props.participants.filter(p => p.status === 'joined');
});
const joinedCount = computed(() => joinedUsers.value.length);

// 2. KOTAK KUNING: Yang masih ringing (pending)
const ringingUsers = computed(() => {
  return props.participants.filter(p => p.status === 'ringing');
});
const ringingCount = computed(() => ringingUsers.value.length);

// 3. KOTAK MERAH: Yang declined atau left
const inactiveUsers = computed(() => {
  return props.participants.filter(p => p.status === 'declined' || p.status === 'left');
});
const inactiveCount = computed(() => inactiveUsers.value.length);

</script>

<template>
  <div 
    class="w-full max-w-sm mx-auto my-4"
    style="display: flex; flex-direction: row; justify-content: center; gap: 12px;"
  >

    <div 
      class="flex-1 py-2 px-1 bg-green-500/10 border border-green-500/40 rounded-xl transition-all hover:bg-green-500/20"
      style="display: flex; flex-direction: column; align-items: center; justify-content: center;"
    >
      <div class="text-green-500 mb-1">
         <UserCheck :size="20" />
      </div>
      <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ joinedCount }}</span>
      <span class="text-[10px] text-gray-500 uppercase tracking-wider mt-1">Joined</span>
    </div>

    <div 
      class="flex-1 py-2 px-1 bg-yellow-500/10 border border-yellow-500/40 rounded-xl transition-all hover:bg-yellow-500/20"
      style="display: flex; flex-direction: column; align-items: center; justify-content: center;"
    >
      <div class="text-yellow-500 mb-1">
         <PhoneCall :size="20" />
      </div>
      <span class="text-lg font-bold text-yellow-600 dark:text-yellow-400">{{ ringingCount }}</span>
      <span class="text-[10px] text-gray-500 uppercase tracking-wider mt-1">Ringing</span>
    </div>

    <div 
      class="flex-1 py-2 px-1 bg-red-500/10 border border-red-500/40 rounded-xl transition-all hover:bg-red-500/20"
      style="display: flex; flex-direction: column; align-items: center; justify-content: center;"
    >
      <div class="text-red-500 mb-1">
         <PhoneOff :size="20" />
      </div>
      <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ inactiveCount }}</span>
      <span class="text-[10px] text-gray-500 uppercase tracking-wider mt-1">Missed</span>
    </div>

  </div>
</template>