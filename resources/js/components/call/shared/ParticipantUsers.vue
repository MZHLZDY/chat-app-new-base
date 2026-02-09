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
  <div class="flex gap-3 w-full max-w-md mx-auto mb-4">

    <div 
      class="flex-1 bg-green-900/40 border border-green-500/50 rounded-lg p-3 flex flex-col items-center justify-center relative group cursor-pointer transition-all hover:bg-green-900/60"
      title="Participants currently in call"
    >
      <div class="text-green-400 mb-1">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
      </div>
      <span class="text-2xl font-bold text-green-400">{{ joinedCount }}</span>
      <span class="text-[10px] uppercase tracking-wider text-green-300/70">Joined</span>

      <div class="absolute top-full mt-2 w-full bg-black/90 text-xs text-white p-2 rounded hidden group-hover:block z-50">
        <ul class="list-disc pl-4">
          <li v-for="user in joinedUsers" :key="user.id">{{ user.name }}</li>
          <li v-if="joinedCount === 0" class="text-gray-500">No one yet</li>
        </ul>
      </div>
    </div>

    <div 
      class="flex-1 bg-yellow-900/40 border border-yellow-500/50 rounded-lg p-3 flex flex-col items-center justify-center relative group cursor-pointer transition-all hover:bg-yellow-900/60"
      title="Participants invited but not answered"
    >
      <div class="text-yellow-400 mb-1">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path><path d="M22 2v6"></path><path d="M19 5H22"></path></svg>
      </div>
      <span class="text-2xl font-bold text-yellow-400">{{ ringingCount }}</span>
      <span class="text-[10px] uppercase tracking-wider text-yellow-300/70">Ringing</span>

       <div class="absolute top-full mt-2 w-full bg-black/90 text-xs text-white p-2 rounded hidden group-hover:block z-50">
        <ul class="list-disc pl-4">
          <li v-for="user in ringingUsers" :key="user.id">{{ user.name }}</li>
          <li v-if="ringingCount === 0" class="text-gray-500">None</li>
        </ul>
      </div>
    </div>

    <div 
      class="flex-1 bg-red-900/40 border border-red-500/50 rounded-lg p-3 flex flex-col items-center justify-center relative group cursor-pointer transition-all hover:bg-red-900/60"
      title="Participants declined or left"
    >
      <div class="text-red-400 mb-1">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7 2 2 0 0 1 1.72 2v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-3.33-2.67m-2.67-3.34a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91"></path><line x1="23" y1="1" x2="1" y2="23"></line></svg>
      </div>
      <span class="text-2xl font-bold text-red-400">{{ inactiveCount }}</span>
      <span class="text-[10px] uppercase tracking-wider text-red-300/70">Left/Busy</span>

       <div class="absolute top-full mt-2 w-full bg-black/90 text-xs text-white p-2 rounded hidden group-hover:block z-50">
        <ul class="list-disc pl-4">
          <li v-for="user in inactiveUsers" :key="user.id">{{ user.name }}</li>
           <li v-if="inactiveCount === 0" class="text-gray-500">None</li>
        </ul>
      </div>
    </div>

  </div>
</template>