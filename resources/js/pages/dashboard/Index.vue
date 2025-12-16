<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from "@/stores/auth"; 
import { themeMode } from "@/layouts/default-layout/config/helper"; 
import { Phone, MessagesSquare } from 'lucide-vue-next';
import ApiService from "@/core/services/ApiService";

// Status tema akan otomatis mengikuti perubahan dari header/navbar
const currentThemeMode = computed(() => themeMode.value);

// Inisialisasi Store untuk mengambil data user yang login
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

// Logika untuk Waktu Dinamis
const currentTime = ref(new Date());
let timer: number | undefined;

onMounted(() => {
  // Update waktu setiap detik
  timer = setInterval(() => {
    currentTime.value = new Date();
  }, 1000);
  
  // Load dashboard stats
  loadDashboardStats();
});

onUnmounted(() => {
  // Bersihkan timer saat komponen dihancurkan
  if (timer !== undefined) {
    clearInterval(timer);
  }
});

const formattedTime = computed(() => {
  // Menggunakan 'en-US' untuk format AM/PM
  return currentTime.value.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
});

const formattedDate = computed(() => {
  // Menggunakan 'id-ID' untuk format tanggal lengkap
  return currentTime.value.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
});

// Data Dinamis untuk Dashboard Chat
const unreadMessages = ref(0); // Hijau - Pesan belum dibaca (personal + group)
const totalContacts = ref(0);   // Biru - Total kontak/user personal
const totalGroups = ref(0);     // Oranye - Total grup

const isLoading = ref(false);

// Fungsi untuk load data dari API
const loadDashboardStats = async () => {
  try {
    isLoading.value = true;
    
    // Panggil API untuk mendapatkan statistik dashboard
    const response = await ApiService.get('/dashboard/stats');
    
    if (response.data) {
      unreadMessages.value = response.data.unread_messages || 0;
      totalContacts.value = response.data.total_contacts || 0;
      totalGroups.value = response.data.total_groups || 0;
    }
  } catch (error) {
    console.error('Error loading dashboard stats:', error);
    // Set default values jika error
    unreadMessages.value = 0;
    totalContacts.value = 0;
    totalGroups.value = 0;
  } finally {
    isLoading.value = false;
  }
};

// Format angka untuk display (jika lebih dari 99 tampilkan 99+)
const formatCount = (count: number): string => {
  return count > 99 ? '99+' : count.toString();
};
</script>

<template>
  <div class="dashboard-container" :class="{ 'dark-mode': currentThemeMode === 'dark' }">
    <div class="dashboard-grid">
      
      <!-- Bagian Kiri: Welcome Card -->
      <div class="welcome-card">
        <div class="welcome-content">
          <div class="icon-wrapper">
            <MessagesSquare />
          </div>
          
          <h2 class="welcome-title">HELLO {{ currentUser?.name?.toUpperCase() || 'USER' }}!</h2>
          
          <h4 class="welcome-subtitle">
            Selamat datang di pusat obrolan Anda!
          </h4>
        </div>

        <button href="@/roles/Index.vue" class="btn-chat-private">LIHAT CHAT PRIBADI</button>
        <button href="" class="btn-chat-group">LIHAT CHAT GRUP</button>
      </div>

      <!-- Bagian Kanan: Info Cards -->
      <div class="info-section">
        
        <!-- Card Phone -->
        <div class="info-card phone-card">
          <p class="phone-text" :title="currentUser?.phone">
            <Phone class="phone-icon"/> : {{ currentUser?.phone || 'xxxxxxx' }}
          </p>
        </div>

        <!-- Card Time & Date -->
        <div class="info-card time-card">
          <h1 class="time-display">{{ formattedTime }}</h1>
          <p class="date-display">{{ formattedDate }}</p>
        </div>

        <!-- Card Statistics -->
        <div class="info-card stats-card">
          <div class="stat-item">
            <div class="stat-circle stat-circle-green" :class="{ 'loading': isLoading }">
              <span v-if="!isLoading">{{ formatCount(unreadMessages) }}</span>
              <span v-else class="loading-spinner"></span>
            </div>
            <p class="stat-label">Pesan Belum Dibaca</p>
          </div>
          
          <div class="stat-item">
            <div class="stat-circle stat-circle-blue" :class="{ 'loading': isLoading }">
              <span v-if="!isLoading">{{ formatCount(totalContacts) }}</span>
              <span v-else class="loading-spinner"></span>
            </div>
            <p class="stat-label">Kontak Personal</p>
          </div>
          
          <div class="stat-item">
            <div class="stat-circle stat-circle-orange" :class="{ 'loading': isLoading }">
              <span v-if="!isLoading">{{ formatCount(totalGroups) }}</span>
              <span v-else class="loading-spinner"></span>
            </div>
            <p class="stat-label">Total Grup</p>
          </div>
        </div>
        
      </div>
      
    </div>
  </div>
</template>

<style scoped>
/* Base Container */
.dashboard-container {
  min-height: 100vh;
  padding: 2rem;
  background-color: #f5f5f5;
  transition: background-color 0.3s ease;
}

/* Dark Mode Background */
.dashboard-container.dark-mode {
  background-color: #1e1e2d;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

/* Welcome Card - Kiri */
.welcome-card {
  background: white;
  border-radius: 1rem;
  padding: 3rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.dark-mode .welcome-card {
  background: #2b2b40;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.welcome-content {
  text-align: center;
}

.icon-wrapper {
  display: inline-flex;
  padding: 1.5rem;
  background: #f0f0f0;
  border-radius: 50%;
  margin-bottom: 2rem;
  transition: background-color 0.3s ease;
}

.dark-mode .icon-wrapper {
  background: #3a3a52;
}

.icon-wrapper svg {
  width: 3rem;
  height: 3rem;
  color: #333;
  transition: color 0.3s ease;
}

.dark-mode .icon-wrapper svg {
  color: #a1a5b7;
}

.welcome-title {
  font-size: 2rem;
  font-weight: bold;
  margin-bottom: 1rem;
  color: #0959ee;
  transition: color 0.3s ease;
}

.dark-mode .welcome-title {
  color: #10a4fa;
}

.welcome-subtitle {
  font-size: 1.125rem;
  color: #666;
  margin-bottom: 2rem;
  transition: color 0.3s ease;
}

.dark-mode .welcome-subtitle {
  color: #a1a5b7;
}

.btn-chat-private {
  padding: 1rem 2rem;
  background: #fa930c;
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  justify-content: center;
}

.btn-chat-private:hover {
  background: #bd3f05;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn-chat-group {
  padding: 1rem 2rem;
  background: #16b407;
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  justify-content: center;
}

.btn-chat-group:hover {
  background: #167908;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.dark-mode .btn-chat {
  background: #4a4a68;
}

.dark-mode .btn-chat:hover {
  background: #5a5a7a;
}

/* Info Section - Kanan */
.info-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.info-card {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.dark-mode .info-card {
  background: #2b2b40;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

/* Phone Card */
.phone-card {
  display: flex;
  align-items: center;
  justify-content: center;
}

.phone-text {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1rem;
  color: #333;
  transition: color 0.3s ease;
}

.dark-mode .phone-text {
  color: #ffffff;
}

.phone-icon {
  width: 1.25rem;
  height: 1.25rem;
}

/* Time Card */
.time-card {
  text-align: center;
}

.time-display {
  font-size: 2.5rem;
  font-weight: bold;
  margin-bottom: 0.5rem;
  color: #0959ee;
  transition: color 0.3s ease;
}

.dark-mode .time-display {
  color: #10a4fa;
}

.date-display {
  font-size: 1rem;
  color: #666;
  transition: color 0.3s ease;
}

.dark-mode .date-display {
  color: #a1a5b7;
}

/* Stats Card */
.stats-card {
  display: flex;
  justify-content: space-around;
  align-items: center;
  gap: 1rem;
}

.stat-item {
  text-align: center;
}

.stat-circle {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 0.75rem;
  font-size: 1.5rem;
  font-weight: bold;
  border: 4px solid;
  transition: all 0.3s ease;
}

.stat-circle-green {
  border-color: #4ade80;
  color: #4ade80;
  background: rgba(74, 222, 128, 0.1);
}

.dark-mode .stat-circle-green {
  background: rgba(74, 222, 128, 0.15);
}

.stat-circle-blue {
  border-color: #60a5fa;
  color: #60a5fa;
  background: rgba(96, 165, 250, 0.1);
}

.dark-mode .stat-circle-blue {
  background: rgba(96, 165, 250, 0.15);
}

.stat-circle-orange {
  border-color: #fb923c;
  color: #fb923c;
  background: rgba(251, 146, 60, 0.1);
}

.dark-mode .stat-circle-orange {
  background: rgba(251, 146, 60, 0.15);
}

.stat-label {
  font-size: 0.875rem;
  color: #666;
  transition: color 0.3s ease;
}

.dark-mode .stat-label {
  color: #a1a5b7;
}

/* Loading State */
.stat-circle.loading {
  opacity: 0.6;
}

.loading-spinner {
  width: 24px;
  height: 24px;
  border: 3px solid rgba(255, 255, 255, 0.3);
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Hover Effects */
.info-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.dark-mode .info-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
}

.stat-circle:hover {
  transform: scale(1.1);
}

/* Responsive */
@media (max-width: 1024px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .dashboard-container {
    padding: 1rem;
  }

  .welcome-card {
    padding: 2rem;
  }

  .welcome-title {
    font-size: 1.5rem;
  }

  .stats-card {
    flex-direction: column;
  }
  
  .stat-circle {
    width: 100px;
    height: 100px;
  }
}
</style>