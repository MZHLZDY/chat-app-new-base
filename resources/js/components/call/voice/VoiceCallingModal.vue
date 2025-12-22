<script setup lang="ts">
import { computed } from 'vue';
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
  <div class="calling-overlay">
    <div class="glass-card">
      
      <div class="avatar-container">
        <CallAvatar 
          :photo-url="props.calleePhoto" 
          :display-name="props.calleeName"
          size="140px" 
          :is-calling="isPulsing" 
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
  /* Gradient biru yang sama */
  background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
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
  padding: 60px 40px;
  
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 24px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
  
  min-width: 320px;
  max-width: 90%;
  animation: zoom-in 0.4s ease-out;
}

/* Typography */
.info-section {
  text-align: center;
  color: white;
  min-height: 80px; /* Menjaga layout tidak lompat saat teks berubah */
}

.callee-name {
  font-size: 1.8rem;
  font-weight: 700;
  margin: 0 0 8px 0;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.status-text {
  font-size: 1rem;
  opacity: 0.8;
  letter-spacing: 0.5px;
  margin: 0;
  font-weight: 500;
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
</style>