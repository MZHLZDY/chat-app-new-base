<script setup lang="ts">
import { computed } from 'vue';
import { Phone, PhoneOff } from 'lucide-vue-next';
import { themeMode } from "@/layouts/default-layout/config/helper"; 
import { useCallStore } from '@/stores/callStore';
import CallAvatar from '../shared/CallAvatar.vue';
import ParticipantUsers from '../shared/ParticipantUsers.vue'; // Import

interface Participant {
  id: number;
  name: string;
  avatar?: string;
  status: 'joined' | 'ringing' | 'declined' | 'left';
}

interface Props {
  groupName: string;
  groupPhoto: string;
  inviterName?: string; // Nama orang yang memulai call (opsional)
  participants: Participant[]; 
  callStatus?: string; 
}

const callStore = useCallStore();
const store = useCallStore();

const props = withDefaults(defineProps<Props>(), {
  callStatus: 'Panggilan Grup Masuk...',
  inviterName: 'Someone',
  participants: () => [],
});

const emit = defineEmits(['accept', 'reject']);

const currentThemeMode = computed(() => themeMode.value);

</script>

<template>
  <div class="incoming-overlay" :class="{ 'dark-mode': currentThemeMode === 'dark' }">
    <div class="glass-card">
      
      <div class="avatar-section">
        <CallAvatar 
          :photo-url="props.groupPhoto" 
          :display-name="props.groupName"
          size="140px" 
          :is-calling="true" 
          :allow-auth-fallback="false"
        />
      </div>

      <div class="info-section w-full flex flex-col items-center">  
        <h1 class="group-name">{{ props.groupName }}</h1>
        
        <p class="inviter-text">invited by <span class="font-bold">{{ props.inviterName }}</span></p>
        <p class="status-text">{{ props.callStatus }}</p>
        
        <h4 class="call-timeout">Ditutup dalam {{ store.timerCount }} detik</h4>

        <div class="mt-6 w-full max-w-md transform scale-90">
             <ParticipantUsers :participants="props.participants" />
        </div>
      </div>

      <div class="action-buttons">
        <button 
          @click="emit('reject')" 
          class="action-btn reject-btn"
          title="Tolak / Abaikan"
        >
          <PhoneOff :size="32" />
        </button>

        <button 
          @click="emit('accept')" 
          class="action-btn accept-btn"
          title="Gabung ke Grup"
        >
          <Phone :size="32" />
        </button>
      </div>

    </div>
  </div>
</template>

<style scoped>
.incoming-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(255, 255, 255, 0.274) !important; backdrop-filter: blur(1.5px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.glass-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 20px;
  /* Padding sedikit dikecilkan dari original agar muat komponen baru */
  padding: 80px 100px;
  background: rgba(255, 255, 255, 0.600);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 24px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
  min-width: 400px;
  max-width: 90%;
  animation: slide-up-fade 0.5s ease-out;
}
  
.dark-mode .glass-card {
  background: rgba(105, 104, 104, 0.5);
}

.group-name {
  font-size: 1.8rem;
  font-weight: 700;
  color: #0959ee;
  margin: 0;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
  text-align: center;
}

.dark-mode .group-name {
  color: #10a4fa;
}

.inviter-text {
    font-size: 1rem;
    color: #555;
    margin-bottom: 4px;
}
.dark-mode .inviter-text {
    color: #ddd;
}

.status-text {
  font-size: 0.9rem;
  opacity: 0.8;
  color: gray;
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

/* Buttons */
.action-buttons {
  display: flex;
  gap: 40px;
  margin-top: 10px;
}

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

.reject-btn {
  background-color: #ff3b30;
}

.accept-btn {
  background-color: #4cd964;
  animation: pulse-green 2s infinite;
}

@keyframes slide-up-fade {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse-green {
  0% { box-shadow: 0 0 0 0 rgba(76, 217, 100, 0.7); }
  70% { box-shadow: 0 0 0 15px rgba(76, 217, 100, 0); }
  100% { box-shadow: 0 0 0 0 rgba(76, 217, 100, 0); }
}

@media (max-width: 600px) {
  .glass-card {
    width: 100%;
    height: 100%;
    border-radius: 0;
    padding: 20px;
    background: rgba(0, 0, 0, 0.2); 
  }
}
</style>