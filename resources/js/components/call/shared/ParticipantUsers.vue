<script setup lang="ts">
import { computed } from 'vue';
import { UserCheck, PhoneCall, PhoneOff } from 'lucide-vue-next';

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
  <div class="d-flex justify-content-center gap-3 w-100 mx-auto my-4">

    <div class="d-flex flex-column align-items-center justify-content-center flex-grow-1 py-3 px-2 rounded bg-light-success border border-success border-dashed transition-all">
      <div class="text-success mb-1">
         <UserCheck :size="20" />
      </div>
      <span class="fs-3 fw-bolder text-success">{{ joinedCount }}</span>
      <span class="text-muted text-uppercase mt-1" style="font-size: 10px; letter-spacing: 1px;">Joined</span>
    </div>

    <div class="d-flex flex-column align-items-center justify-content-center flex-grow-1 py-3 px-2 rounded bg-light-warning border border-warning border-dashed transition-all">
      <div class="text-warning mb-1">
         <PhoneCall :size="20" />
      </div>
      <span class="fs-3 fw-bolder text-warning">{{ ringingCount }}</span>
      <span class="text-muted text-uppercase mt-1" style="font-size: 10px; letter-spacing: 1px;">Ringing</span>
    </div>

    <div class="d-flex flex-column align-items-center justify-content-center flex-grow-1 py-3 px-2 rounded bg-light-danger border border-danger border-dashed transition-all">
      <div class="text-danger mb-1">
         <PhoneOff :size="20" /> 
      </div>
      <span class="fs-3 fw-bolder text-danger">{{ inactiveCount }}</span>
      <span class="text-muted text-uppercase mt-1" style="font-size: 10px; letter-spacing: 1px;">Declined</span>
    </div>

  </div>
</template>