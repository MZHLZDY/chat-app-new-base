<script setup lang="ts">
import { Phone, PhoneOff } from 'lucide-vue-next';
import CallAvatar from '../shared/CallAvatar.vue';

interface Props {
  callerName: string;
  callerPhoto: string;
  callStatus?: string; // Opsional: misal "Connecting..." atau "Incoming Voice Call"
}

const props = withDefaults(defineProps<Props>(), {
  callStatus: 'Incoming Voice Call...',
});

const emit = defineEmits(['accept', 'reject']);
</script>

<template>
  <div class="incoming-overlay">
    <div class="glass-card">
      
      <div class="avatar-section">
        <CallAvatar 
          :photo-url="props.callerPhoto" 
          :display-name="props.callerName"
          size="140px" 
          :is-calling="true" 
        />
      </div>

      <div class="info-section">
        <p class="status-text">{{ props.callStatus }}</p>
        <h2 class="caller-name">{{ props.callerName }}</h2>
      </div>

      <div class="action-buttons">
        <button 
          @click="emit('reject')" 
          class="action-btn reject-btn"
          title="Decline"
        >
          <PhoneOff :size="32" />
        </button>

        <button 
          @click="emit('accept')" 
          class="action-btn accept-btn"
          title="Accept"
        >
          <Phone :size="32" />
        </button>
      </div>

    </div>
  </div>
</template>

<style scoped>
/* Latar belakang full screen (bisa diganti gradient sesuai tema aplikasi) */
.incoming-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); /* Blue gradient mirip video */
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

/* Container Glassmorphism Utama */
.glass-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 30px;
  padding: 50px 40px;
  
  /* Efek Kaca */
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 24px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
  
  min-width: 320px;
  max-width: 90%;
  animation: slide-up-fade 0.5s ease-out;
}

/* Typography */
.info-section {
  text-align: center;
  color: white;
}

.status-text {
  font-size: 0.9rem;
  opacity: 0.8;
  margin-bottom: 8px;
  letter-spacing: 0.5px;
}

.caller-name {
  font-size: 1.8rem;
  font-weight: 700;
  margin: 0;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Action Buttons Container */
.action-buttons {
  display: flex;
  gap: 40px;
  margin-top: 10px;
}

/* Base Button Style */
.action-btn {
  width: 70px;
  height: 70px;
  border-radius: 50%;
  border: none;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  color: white;
  transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.action-btn:hover {
  transform: scale(1.1);
  filter: brightness(1.1);
}

.action-btn:active {
  transform: scale(0.95);
}

/* Warna Spesifik */
.reject-btn {
  background-color: #ff3b30; /* iOS Red Style */
}

.accept-btn {
  background-color: #4cd964; /* iOS Green Style */
  animation: pulse-green 2s infinite; /* Animasi tambahan biar user notice tombol angkat */
}

/* Animations */
@keyframes slide-up-fade {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes pulse-green {
  0% {
    box-shadow: 0 0 0 0 rgba(76, 217, 100, 0.7);
  }
  70% {
    box-shadow: 0 0 0 15px rgba(76, 217, 100, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(76, 217, 100, 0);
  }
}

/* Responsivitas */
@media (max-width: 480px) {
  .glass-card {
    width: 100%;
    height: 100%;
    border-radius: 0;
    justify-content: center;
    background: rgba(0, 0, 0, 0.2); /* Sedikit lebih gelap di mobile agar kontras */
  }
  
  .caller-name {
    font-size: 1.5rem;
  }
}
</style>