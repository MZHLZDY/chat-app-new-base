<script setup lang="ts">
import { computed } from 'vue';
import { themeMode } from "@/layouts/default-layout/config/helper"; 
import { PhoneOff } from 'lucide-vue-next'; // Icon telepon tutup
import CallAvatar from '../shared/CallAvatar.vue';

interface Props {
  calleeName: string;
  calleePhoto: string;
  // Status bisa: 'calling', 'ringing', 'rejected', 'busy', 'no-answer'
  callStatus: string; 
}

const props = withDefaults(defineProps<Props>(), {
  callStatus: 'calling',
});

const emit = defineEmits(['cancel']);

const currentThemeMode = computed(() => themeMode.value);

// Logic: Animasi pulse hanya aktif jika status masih calling/ringing
// Jika ditolak ('rejected') atau sibuk, animasi berhenti.
const isPulsing = computed(() => {
  const activeStatuses = ['calling', 'ringing', 'connecting'];
  return activeStatuses.includes(props.callStatus.toLowerCase());
});

// Logic: Teks status yang ditampilkan di UI
const statusText = computed(() => {
  switch (props.callStatus.toLowerCase()) {
    case 'calling': return 'Memanggil...';
    case 'ringing': return 'Berdering...';
    case 'rejected': return 'Panggilan Ditolak';
    case 'busy': return 'Sedang Sibuk';
    case 'no-answer': return 'Tidak Dijawab';
    default: return 'Menghubungkan...';
  }
});

// Logic: Warna teks status (Kuning/Merah jika ditolak agar user sadar)
const isErrorStatus = computed(() => {
  return ['rejected', 'busy', 'no-answer'].includes(props.callStatus.toLowerCase());
});
</script>

<template>
  <div class="calling-overlay" :class="{ 'dark-mode': currentThemeMode === 'dark' }">
    <div class="glass-card" >
      
      <div class="avatar-container">
        <CallAvatar 
          :photo-url="props.calleePhoto" 
          :display-name="props.calleeName"
          size="140px" 
          :is-calling="isPulsing"
          :allow-auth-fallback="false" 
        />
      </div>

      <div class="info-section">
        <h2 class="callee-name">{{ props.calleeName }}</h2>
        
        <transition name="fade" mode="out-in">
          <p 
            :key="statusText" 
            class="status-text"
            :class="{ 'status-error': isErrorStatus }"
          >
            {{ statusText }}
          </p>
        </transition>
      </div>

      <div class="action-section">
        <button 
          @click="emit('cancel')" 
          class="cancel-btn"
          title="Batalkan Panggilan"
        >
          <PhoneOff :size="32" />
        </button>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* Reuse background style agar konsisten dengan IncomingModal */
.calling-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  /* Gradient blur yang sama */
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
  gap: 30px; /* Jarak antar elemen */
  padding: 140px 300px;
  background: rgba(255, 255, 255, 0.600);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 24px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
  min-width: 320px;
  max-width: 90%;
  animation: zoom-in 0.4s ease-out;
}

.dark-mode .glass-card {
  background: rgba(105, 104, 104, 0.5);
}

/* Typography */
.info-section {
  text-align: center;
  color: rgb(134, 129, 129);
  min-height: 80px; /* Menjaga layout tidak lompat saat teks berubah */
}

.callee-name {
  font-size: 1.8rem;
  font-weight: 700;
  margin: 0 0 8px 0;
  color: #0959ee;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.dark-mode .callee-name {
  color: #10a4fa;
}

.status-text {
  font-size: 1rem;
  opacity: 0.8;
  letter-spacing: 0.5px;
  color: grey;
  margin: 0;
  font-weight: 500;
}

.dark-mode .status-text {
  color: white;
}

/* Modifikasi warna teks jika ditolak */
.status-text.status-error {
  color: #ffcccc; /* Putih kemerahan */
  font-weight: 600;
  opacity: 1;
}

/* Cancel Button (Single Red Button) */
.cancel-btn {
  width: 70px;
  height: 70px;
  border-radius: 50%;
  border: none;
  background-color: #ff3b30; /* Merah */
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

.cancel-btn:active {
  transform: scale(0.95);
}

/* Animations */
@keyframes zoom-in {
  from { opacity: 0; transform: scale(0.9); }
  to { opacity: 1; transform: scale(1); }
}

/* Vue Transition untuk teks */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 580px) {
  .glass-card {
    width: 100%;
    height: 100%;
    border-radius: 0;
    justify-content: center;
  }
  
  .caller-name {
    font-size: 1.5rem;
  }
}
</style>