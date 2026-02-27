<script setup lang="ts">
import { computed } from 'vue';
import { themeMode } from "@/layouts/default-layout/config/helper"; 
import { PhoneOff } from 'lucide-vue-next'; 
import CallAvatar from '../shared/CallAvatar.vue';
import ParticipantUsers from '../shared/ParticipantUsers.vue'; // Import komponen kotak warna-warni
import { useCallStore } from '@/stores/callStore';

interface Participant {
  id: number;
  name: string;
  avatar?: string;
  status: 'joined' | 'ringing' | 'declined' | 'left';
}

interface Props {
  groupName: string;
  groupPhoto: string;
  participants: Participant[]; // Data peserta untuk ParticipantUsers
  callStatus: string; 
}

const callStore = useCallStore();
const store = useCallStore();

const props = withDefaults(defineProps<Props>(), {
  callStatus: 'calling',
  groupName: 'Group Call',
  participants: () => [],
});

const emit = defineEmits(['cancel']);

const currentThemeMode = computed(() => themeMode.value);

// Logic: Animasi pulse aktif saat calling/ringing
const isPulsing = computed(() => {
  const activeStatuses = ['calling', 'ringing', 'connecting', 'waiting'];
  return activeStatuses.includes(props.callStatus.toLowerCase());
});

// Logic: Teks status
const statusText = computed(() => {
  switch (props.callStatus.toLowerCase()) {
    case 'calling': return 'Memanggil Anggota Grup...';
    case 'ringing': return 'Menunggu jawaban...';
    case 'waiting': return 'Menunggu orang lain bergabung...';
    case 'rejected': return 'Panggilan Ditolak';
    case 'busy': return 'Grup Sibuk';
    default: return 'Menghubungkan...';
  }
});
</script>

<template>
  <div class="calling-overlay" :class="{ 'dark-mode': currentThemeMode === 'dark' }">
    <div class="glass-card" >
      
      <div class="avatar-container">
        <CallAvatar 
          :photo-url="props.groupPhoto" 
          :display-name="props.groupName"
          size="140px" 
          :is-calling="isPulsing"
          :allow-auth-fallback="false" 
        />
      </div>

      <div class="info-section w-full flex flex-col items-center">
        <h1 class="group-name">{{ props.groupName }}</h1>
        
        <transition name="fade" mode="out-in">
          <p :key="statusText" class="status-text">
            {{ statusText }}
          </p>  
        </transition>

        <!-- <h4 class="call-timeout">
           Ditutup dalam {{ store.timerCount }} detik 
        </h4> -->

        <div class="mt-6 w-full max-w-md transform scale-90">
             <ParticipantUsers :participants="props.participants" />
        </div>
      </div>

      <div class="action-section">
        <button 
          @click="emit('cancel')" 
          class="cancel-btn"
          title="Batalkan Panggilan Grup"
        >
          <PhoneOff :size="32" />
        </button>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* Reuse background style agar konsisten */
.calling-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(255, 255, 255, 0.270) !important; backdrop-filter: blur(1.5px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

/* Glassmorphism Card */
.glass-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 20px; 
  /* Padding disesuaikan sedikit agar muat ParticipantUsers */
  padding: 80px 100px; 
  background: rgba(255, 255, 255, 0.600);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 24px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
  min-width: 400px;
  max-width: 90%;
  animation: zoom-in 0.4s ease-out;
}

.dark-mode .glass-card {
  background: rgba(105, 104, 104, 0.5);
}

.group-name {
  font-size: 1.8rem;
  font-weight: 700;
  margin: 0 0 8px 0;
  color: #0959ee;
  text-align: center;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.dark-mode .group-name {
  color: #10a4fa;
}

.status-text {
  font-size: 1rem;
  opacity: 0.8;
  color: grey;
  font-weight: 500;
  margin-bottom: 4px;
}

.dark-mode .status-text {
  color: white;
}

.call-timeout {
  font-size: 0.9rem;
  opacity: 0.8;
  color: rgb(247, 133, 3);
  margin-bottom: 8px;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.dark-mode .call-timeout {
  color: rgb(247, 190, 3);
}

/* Cancel Button */
.cancel-btn {
  width: 70px;
  height: 70px;
  border-radius: 50%;
  border: none;
  background-color: #ff3b30;
  color: white;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(255, 59, 48, 0.4);
}

.cancel-btn:hover {
  background-color: #ff1f1f;
  transform: scale(1.05);
}

@keyframes zoom-in {
  from { opacity: 0; transform: scale(0.9); }
  to { opacity: 1; transform: scale(1); }
}

/* Media Query untuk Responsivitas */
@media (max-width: 600px) {
  .glass-card {
    width: 100%;
    height: 100%;
    border-radius: 0;
    padding: 20px;
  }
}
</style>