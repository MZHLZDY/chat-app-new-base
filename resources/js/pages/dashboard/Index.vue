<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from "@/stores/auth"; // 1. Import Store Auth
import { Phone } from 'lucide-vue-next';

// 2. Inisialisasi Store untuk mengambil data user yang login
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

// 3. Logika untuk Waktu Dinamis
const currentTime = ref(new Date());
let timer: number | undefined;

onMounted(() => {
  // Update waktu setiap detik
  timer = setInterval(() => {
    currentTime.value = new Date();
  }, 1000);
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

// 4. Logika untuk Mode (Default ke Light Mode sesuai screenshot)
const isLightMode = ref(true); // Default mode terang

// 5. Data Statis untuk Dashboard Chat (Bisa diganti dinamis nanti jika ada API-nya)
const messageCount = ref(75);
const onlineContacts = ref(40);
const notifications = ref('90+');

</script>

<template>
  <div class="welcome-container" :class="{ 'light-mode': isLightMode }">
    
    <div class="dashboard-content-wrapper">
        <div class="glass-card welcome-card">
        <div class="illustration-area chat-theme">
            <div class="chat-illustration">
                <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-message-square">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </div>
            
            <h2>HELLO {{ currentUser?.name?.toUpperCase() || 'USER' }}!</h2>
            
            <h4 class="text-blue-500 dark:text-blue-400 mt-1">
              Selamat datang di pusat obrolan Anda!
            </h4>
        </div>

        <button class="explore-button chat-button">LIHAT OBROLAN</button>
        </div>

        <div class="side-panel">
            <div class="glass-card profile-card">
                <center>
                  <p class="number-text" :title="currentUser?.phone">
                    <Phone class="w-2 h-2"/> : {{ currentUser?.phone || 'xxxxxxx' }}
                  </p>
                </center>
            </div>

            <div class="glass-card time-card">
                <h1>{{ formattedTime }}</h1>
                <p>{{ formattedDate }}</p>
            </div>

            <div class="glass-card progress-card">
                <div class="progress-item">
                    <div class="circle" :style="{'--p': 75, '--c': '#4ADE80', '--b': 'var(--circle-bg)'}">
                        <span>{{ messageCount }}</span>
                    </div>
                    <p>Pesan Baru</p>
                </div>
                <div class="progress-item">
                    <div class="circle" :style="{'--p': 40, '--c': '#60A5FA', '--b': 'var(--circle-bg)'}">
                        <span>{{ onlineContacts }}</span>
                    </div>
                    <p>Kontak Aktif</p>
                </div>
                <div class="progress-item">
                    <div class="circle" :style="{'--p': 90, '--c': '#F97316', '--b': 'var(--circle-bg)'}">
                        <span>{{ notifications }}</span>
                    </div>
                    <p>Notifikasi</p>
                </div>
            </div>
        </div>
    </div>
  </div>
</template>

<style scoped>
/* ================================================= */
/* 1. DEFINISI VARIABEL WARNA GLOBAL (DEFAULT: DARK MODE) */
/* ================================================= */
:root {
    --chat-gradient-start: #1D4ED8; 
    --chat-gradient-end: #0D9488; 
    --main-accent-color: #60A5FA; 
    
    --glass-bg-opacity: rgba(255, 255, 255, 0.08);
    --glass-border-color: rgba(255, 255, 255, 0.1);
    --circle-bg: #202940; 
}

/* ================================================= */
/* 2. OVERRIDE LIGHT MODE (Sesuai Screenshot) */
/* ================================================= */
.welcome-container.light-mode {
    --bg-color: #F8F9FA; 
    --text-color: #1E293B;
    --text-muted: #64748B;
    --glass-bg-opacity: rgba(255, 255, 255, 0.85); 
    --glass-border-color: rgba(0, 0, 0, 0.05);
    --circle-bg: #E2E8F0; 
    
    color: #22201f; 
}


/* ================================================= */
/* 3. PENGATURAN UMUM & DIMENSI */
/* ================================================= */

.welcome-container {
  background: var(--bg-color); 
  color: var(--text-color);
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  min-height: 100vh;
  padding: 0;
  
  display: flex;
  flex-direction: column; 
  align-items: center; 
  padding-top: 50px; 
}

.dashboard-content-wrapper {
    display: flex;
    gap: 30px; 
    padding: 30px;
    width: 90%; 
    max-width: 1000px; 
    box-sizing: border-box;
    align-items: flex-start;
}

/* 4. Gaya Glassmorphism */
.glass-card {
  background: var(--glass-bg-opacity);
  backdrop-filter: blur(10px); 
  border: 1px solid var(--glass-border-color);
  border-radius: 20px;
  padding: 30px;
  box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
  margin: 0; 
  transition: all 0.3s ease-in-out;
}

/* 5. Gaya Area Utama Welcome */
.welcome-card {
  flex-grow: 1; 
  max-width: 500px;
  height: 500px; 
  display: flex;
  flex-direction: column;
  justify-content: space-around; 
  align-items: center;
  text-align: center;
}

.number-text {
    font-size: 0.95em;
    font-weight: 500;
    margin: 0;
    white-space: nowrap;      /* Mencegah teks turun ke baris baru */
    overflow: hidden;         /* Menyembunyikan teks yang kepanjangan */
    text-overflow: ellipsis;  /* Menambahkan '...' jika teks terpotong */
    max-width: 190px;         /* Batas lebar teks email */
}

.chat-illustration {
    width: 100px;
    height: 100px;
    margin: 0 auto 30px;
    font-weight: 800;
}

.chat-illustration svg {
    width: 100%;
    height: 100%;
    stroke: var(--text-color); 
    stroke-width: 3;
    fill: none;
    filter: none; 
}

.welcome-card h2 {
  font-size: 2.2em;
  font-weight: 700;
  margin-bottom: 5px;
  color: var(--main-accent-color); 
}

.welcome-card p {
  font-size: 1.1em;
  color: var(--text-muted); 
}

/* Tombol Aksi */
.explore-button {
  background: none; 
  border: 2px solid var(--main-accent-color); 
  color: var(--main-accent-color); 
  padding: 12px 30px;
  border-radius: 50px;
  font-weight: 600;
  cursor: pointer;
  letter-spacing: 1px;
  transition: all 0.3s;
  margin-top: 20px;
}
.welcome-container.light-mode .explore-button:hover {
    background: var(--main-accent-color);
    color: white;
}

/* 6. Gaya Panel Samping */
.side-panel {
  display: flex;
  flex-direction: column;
  width: 300px;
  gap: 20px;
  height: 500px; 
  flex-shrink: 0; 
}

.profile-card {
  height: 70px;
  flex-shrink: 0; 
  display: flex;
  align-items: center;
}

.time-card {
  height: 120px;
  flex-shrink: 0;
}

.progress-card {
    flex-grow: 1; 
    display: flex;
    flex-direction: row;
    justify-content: space-around;
    align-items: center;
}

.avatar {
  width: 40px;
  height: 40px;
  background-color: var(--main-accent-color); 
  color: var(--text-color); 
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-weight: bold;
  font-size: 1.2em;
  margin-right: 15px;
}
.welcome-container.light-mode .avatar {
    color: white; 
}

.time-card h1 {
  font-size: 2.5em;
  margin: 0;
  color: var(--text-color);
}

.time-card p {
  color: var(--text-muted);
  margin-top: 5px;
}

/* 7. Gaya Progres Lingkaran */
.progress-item p {
    color: var(--text-muted);
}

.circle {
  --p: 0; 
  --c: #4ADE80; 
  --b: var(--circle-bg); 
  --w: 70px; 
  
  width: var(--w);
  height: var(--w);
  border-radius: 50%;
  background: 
    radial-gradient(closest-side, var(--bg-color) 79%, transparent 80% 100%),
    conic-gradient(var(--c) calc(var(--p) * 1%), var(--b) 0);
  display: grid;
  place-items: center;
  font-size: 0.9em;
  margin: 0 auto;
}

.circle span {
    color: var(--text-color);
    font-weight: 600;
}

.welcome-container.light-mode .circle span {
    color: var(--text-color);
}

/* Media Query */
@media (max-width: 900px) {
  .dashboard-content-wrapper {
    flex-direction: column;
    padding: 20px;
    gap: 15px;
    width: 100%;
    max-width: none;
  }
  .welcome-card, .side-panel {
    width: 100%;
    max-width: 100%;
    height: auto;
    flex-shrink: 1;
  }
  .welcome-card {
      padding: 25px;
  }
  .side-panel {
      gap: 15px;
  }
  .progress-card {
    flex-wrap: wrap;
    justify-content: space-evenly;
  }
}
</style>