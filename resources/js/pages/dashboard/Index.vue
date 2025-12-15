<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from "@/stores/auth"; 
import { themeMode } from "@/layouts/default-layout/config/helper"; 
import { Phone, MessagesSquare } from 'lucide-vue-next';

// 2. Gunakan themeMode sebagai Computed Property
// Status tema akan otomatis mengikuti perubahan dari header/navbar
const currentThemeMode = computed(() => themeMode.value);
// END: Perubahan untuk Tema Dinamis
// ===================================================================

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

// Data Statis untuk Dashboard Chat (Bisa diganti dinamis nanti jika ada API-nya)
const messageCount = ref(75);
const onlineContacts = ref(40);
const notifications = ref('90+');

</script>

<template>
  <div class="welcome-container" :class="{ 'light-mode': currentThemeMode === 'light' }">
  <div class="dashboard-content-wrapper">
        <div class="glass-card welcome-card">
        <div class="illustration-area chat-theme">
            <div class="chat-illustration">
                <MessagesSquare />
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
                <p class="number-text" :title="currentUser?.phone">
                  <Phone class="w-2 h-2"/> : {{ currentUser?.phone || 'xxxxxxx' }}
                </p>
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
/* Semua nilai ini akan menjadi default/Dark Mode */
:root {
    --chat-gradient-start: #1D4ED8; 
    --chat-gradient-end: #0D9488; 
    --main-accent-color: #60A5FA; 
    
    --bg-color: #2d3036; /* Latar Belakang Utama Dark Mode */
    --text-color: #F9FAFB; /* Teks Utama Dark Mode (Putih/Terang) */
    --text-muted: #9CA3AF;

    --glass-bg-opacity: rgba(255, 255, 255, 0.15); /* Background Card Dark Mode - Lebih terang */
    --glass-border-color: rgba(255, 255, 255, 0.2); /* Border Card Dark Mode - Lebih terlihat */
    --circle-bg: #64748B; /* Warna Latar Belakang Lingkaran di Dark Mode - Lebih terang */
    --icon-color: #60A5FA; /* Warna icon di Dark Mode */
}

/* ================================================= */
/* 2. OVERRIDE LIGHT MODE */
/* ================================================= */
.welcome-container.light-mode {
    --bg-color: #F8F9FA; /* Latar Belakang Utama Light Mode */
    --text-color: #1E293B; /* Teks Utama Light Mode (Gelap) */
    --text-muted: #64748B;
    --glass-bg-opacity: rgba(255, 255, 255, 0.85); 
    --glass-border-color: rgba(0, 0, 0, 0.05);
    --circle-bg: #E2E8F0; /* Latar Belakang Lingkaran di Light Mode */
    
    color: var(--text-color); 
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
  transition: background 0.3s, color 0.3s; 
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
    display: flex;
    align-items: center;
    gap: 10px;
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
.explore-button:hover { /* Hover di Dark Mode */
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

/* Avatar sudah dihapus, style ini tidak lagi dipakai untuk avatar huruf inisial */
.avatar {
  display: none; 
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
  transition: background 0.3s; /* Tambahkan transisi halus */
}

.circle span {
    color: var(--text-color);
    font-weight: 600;
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